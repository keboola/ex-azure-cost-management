<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Keboola\AzureCostExtractor\Api\ClientFactory;
use Keboola\AzureCostExtractor\Config;
use Keboola\AzureCostExtractor\ConfigDefinition;
use Keboola\Component\UserException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Covers how the extractor reacts to HTTP 429 responses from the Azure Cost Management API.
 * These tests are hermetic - the Guzzle client is backed by a MockHandler, no network is used,
 * and the waits are recorded instead of slept, so the suite stays fast.
 */
class ApiRateLimitTest extends TestCase
{
    private const THROTTLED_BODY = '{"error":{"code":"429","message":"Too many requests. Please retry."}}';

    private const OK_BODY = '{"properties":{"rows":[],"columns":[]}}';

    private const ENTITY_RETRY_AFTER = 'x-ms-ratelimit-microsoft.costmanagement-entity-retry-after';

    private const CLIENTTYPE_RETRY_AFTER = 'x-ms-ratelimit-microsoft.costmanagement-clienttype-retry-after';

    /** The 429 retry budget, when "maxTries" is left at or below its default. */
    private const RATE_LIMIT_RETRIES = 7;

    private const MIN_WAIT = 30;

    private const MAX_WAIT = 120;

    public function testSuccessfulRequestNeverWaits(): void
    {
        $api = $this->createApi([new Response(200, [], self::OK_BODY)]);

        $response = $api->sendOneRequest(new Request('POST', 'query'));

        Assert::assertSame(200, $response->getStatusCode());
        Assert::assertSame([], $api->getWaits());
    }

    /**
     * The entity scope header was already honoured before this test existed.
     * It is asserted here so the previously working path stays covered.
     */
    public function testEntityScopeRetryAfterHeaderIsHonoured(): void
    {
        $api = $this->createApi([
            new Response(429, [self::ENTITY_RETRY_AFTER => '60'], self::THROTTLED_BODY),
            new Response(200, [], self::OK_BODY),
        ]);

        $response = $api->sendOneRequest(new Request('POST', 'query'));

        Assert::assertSame(200, $response->getStatusCode());
        // 60 seconds asked for, plus the 3 second safety margin.
        Assert::assertSame([63], $api->getWaits());
    }

    /**
     * Azure throttles per entity, but also per client type, per tenant and per QPU, and reports
     * the wait in a different header for each scope. A throttle at any scope other than entity
     * used to look like "429 without Retry-After" and fell through to blind exponential backoff,
     * which regularly ran out of tries and killed the job.
     *
     * @dataProvider provideNonEntityRetryAfterHeaders
     */
    public function testNonEntityScopeRetryAfterHeaderIsHonoured(string $header): void
    {
        $api = $this->createApi([
            new Response(429, [$header => '60'], self::THROTTLED_BODY),
            new Response(200, [], self::OK_BODY),
        ]);

        $response = $api->sendOneRequest(new Request('POST', 'query'));

        Assert::assertSame(200, $response->getStatusCode());
        Assert::assertSame([63], $api->getWaits());
    }

    /**
     * @return array<string, array{string}>
     */
    public function provideNonEntityRetryAfterHeaders(): array
    {
        return [
            // The scope actually seen on throttled production responses.
            'clienttype scope' => [self::CLIENTTYPE_RETRY_AFTER],
            'client scope' => ['x-ms-ratelimit-microsoft.costmanagement-client-retry-after'],
            'tenant scope' => ['x-ms-ratelimit-microsoft.costmanagement-tenant-retry-after'],
            'qpu scope' => ['x-ms-ratelimit-microsoft.costmanagement-qpu-retry-after'],
            'standard header' => ['Retry-After'],
        ];
    }

    /**
     * A response can carry several scopes at once with different values. Waiting only as long as
     * the entity scope asks for is throttled again straight away, so the longest wait must win.
     */
    public function testLongestRequestedWaitAcrossScopesWins(): void
    {
        $api = $this->createApi([
            new Response(
                429,
                [self::ENTITY_RETRY_AFTER => '21', self::CLIENTTYPE_RETRY_AFTER => '47'],
                self::THROTTLED_BODY
            ),
            new Response(200, [], self::OK_BODY),
        ]);

        $response = $api->sendOneRequest(new Request('POST', 'query'));

        Assert::assertSame(200, $response->getStatusCode());
        // 47 wins over 21, plus the 3 second safety margin.
        Assert::assertSame([50], $api->getWaits());
    }

    /**
     * Replays the header set seen on the throttled responses that were killing production jobs:
     * no entity scoped retry-after, a clienttype scoped one, and several "remaining" headers whose
     * values are not numbers of seconds and must not be mistaken for a delay.
     */
    public function testThrottledProductionResponseIsRetriedWithTheRequestedWait(): void
    {
        $throttledHeaders = [
            'x-ms-ratelimit-remaining-microsoft.costmanagement-entity-requests' => 'DefaultQuota:3',
            'x-ms-ratelimit-remaining-microsoft.costmanagement-tenant-requests' => 'DefaultQuota:19',
            self::CLIENTTYPE_RETRY_AFTER => '45',
            'x-ms-ratelimit-remaining-microsoft.costmanagement-clienttype-requests' => 'DefaultQuota:0',
            'x-ms-ratelimit-microsoft.costmanagement-qpu-consumed' => '1',
            'x-ms-ratelimit-microsoft.costmanagement-qpu-remaining' => 'QueriesPerHour:577,QueriesPerMin:59',
            'x-ms-ratelimit-remaining-subscription-resource-requests' => '1099',
        ];

        $api = $this->createApi([
            new Response(429, $throttledHeaders, self::THROTTLED_BODY),
            new Response(200, [], self::OK_BODY),
        ]);

        $response = $api->sendOneRequest(new Request('POST', 'query'));

        Assert::assertSame(200, $response->getStatusCode());
        Assert::assertSame([48], $api->getWaits());
    }

    /**
     * Azure enforces its cost management limits over windows of roughly 30-60 seconds. A shorter
     * wait is throttled again and only burns a retry, so every 429 waits at least the minimum.
     */
    public function testWaitIsNeverShorterThanTheMinimum(): void
    {
        $api = $this->createApi([
            new Response(429, [self::ENTITY_RETRY_AFTER => '5'], self::THROTTLED_BODY),
            new Response(200, [], self::OK_BODY),
        ]);

        $api->sendOneRequest(new Request('POST', 'query'));

        Assert::assertSame([self::MIN_WAIT], $api->getWaits());
    }

    /**
     * Azure can report a wait tied to an hourly quota. Without a cap a single throttled request
     * could hold the job for hours, so one wait is bounded. Waiting less than asked is safe:
     * the request is simply throttled again and waits again, within the retry budget.
     */
    public function testWaitIsCappedAtTheMaximum(): void
    {
        $api = $this->createApi([
            new Response(429, ['x-ms-ratelimit-microsoft.costmanagement-tenant-retry-after' => '3600'], '{}'),
            new Response(200, [], self::OK_BODY),
        ]);

        $api->sendOneRequest(new Request('POST', 'query'));

        Assert::assertSame([self::MAX_WAIT], $api->getWaits());
    }

    /**
     * A 429 with no readable wait used to fall through to the exponential backoff of the retry
     * proxy, which starts at 5 seconds - far inside the Azure limit window. It now waits the
     * minimum instead.
     *
     * @dataProvider provideResponsesWithoutAReadableWait
     * @param array<string, string> $headers
     */
    public function testRateLimitWithoutAReadableWaitUsesTheMinimum(array $headers): void
    {
        $api = $this->createApi([
            new Response(429, $headers, self::THROTTLED_BODY),
            new Response(200, [], self::OK_BODY),
        ]);

        $api->sendOneRequest(new Request('POST', 'query'));

        Assert::assertSame([self::MIN_WAIT], $api->getWaits());
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public function provideResponsesWithoutAReadableWait(): array
    {
        return [
            'no headers at all' => [[]],
            // The standard Retry-After header also allows an HTTP date.
            'http date' => [['Retry-After' => 'Wed, 21 Oct 2026 07:28:00 GMT']],
            // A "remaining" header is a quota, not a delay.
            'quota only' => [
                ['x-ms-ratelimit-remaining-microsoft.costmanagement-entity-requests' => 'DefaultQuota:3'],
            ],
        ];
    }

    /**
     * A rate limit is not an error in the extractor, so it has its own retry budget and does not
     * consume the generic "maxTries" retries. "maxTries" is 1 in these tests, yet the request is
     * still retried the full rate limit budget before it gives up.
     */
    public function testRateLimitRetriesDoNotConsumeMaxTries(): void
    {
        $responses = array_fill(0, self::RATE_LIMIT_RETRIES, new Response(429, [], self::THROTTLED_BODY));
        $responses[] = new Response(200, [], self::OK_BODY);
        $api = $this->createApi($responses);

        $response = $api->sendOneRequest(new Request('POST', 'query'));

        Assert::assertSame(200, $response->getStatusCode());
        Assert::assertCount(self::RATE_LIMIT_RETRIES, $api->getWaits());
    }

    /**
     * A rate limit that never clears is an upstream throttle, not a bug in the extractor.
     * It has to surface as a user error (exit code 1) with an actionable message, rather than
     * as an opaque application error (exit code 2) that pages the team.
     */
    public function testPersistentRateLimitIsReportedAsUserError(): void
    {
        // One more 429 than the budget allows, so the budget is used up.
        $api = $this->createApi(
            array_fill(0, self::RATE_LIMIT_RETRIES + 1, new Response(429, [], self::THROTTLED_BODY))
        );

        try {
            $api->sendOneRequest(new Request('POST', 'query'));
            Assert::fail('Expected a UserException to be thrown.');
        } catch (UserException $e) {
            Assert::assertStringContainsString('Azure Cost Management API rate limit was reached', $e->getMessage());
            Assert::assertStringContainsString('maxTries', $e->getMessage());
            // The original API error is kept, so the user still sees what actually failed.
            Assert::assertStringContainsString('Too many requests. Please retry.', $e->getMessage());
            Assert::assertSame(429, $e->getCode());
        }

        // The budget is used up exactly once - the retry proxy must not run the whole loop again.
        Assert::assertCount(self::RATE_LIMIT_RETRIES, $api->getWaits());
    }

    /**
     * The 429 handling must not change how any other failure is reported.
     * A 404 was, and stays, a user error carrying the plain API message, with no waiting.
     */
    public function testOtherErrorsAreUnaffected(): void
    {
        $api = $this->createApi([
            new Response(404, [], '{"error":{"code":"NotFound","message":"Subscription not found."}}'),
        ]);

        try {
            $api->sendOneRequest(new Request('POST', 'query'));
            Assert::fail('Expected a UserException to be thrown.');
        } catch (UserException $e) {
            Assert::assertStringContainsString('Subscription not found.', $e->getMessage());
            Assert::assertStringNotContainsString('rate limit', $e->getMessage());
            Assert::assertSame(404, $e->getCode());
        }

        Assert::assertSame([], $api->getWaits());
    }

    /**
     * A server error still goes through the retry proxy and is still reported as a user error.
     */
    public function testServerErrorIsUnaffected(): void
    {
        $api = $this->createApi([
            new Response(500, [], '{"error":{"code":"InternalServerError","message":"Server error."}}'),
        ]);

        try {
            $api->sendOneRequest(new Request('POST', 'query'));
            Assert::fail('Expected a UserException to be thrown.');
        } catch (UserException $e) {
            Assert::assertStringContainsString('Server error.', $e->getMessage());
            Assert::assertSame(500, $e->getCode());
        }

        Assert::assertSame([], $api->getWaits());
    }

    /**
     * @param Response[] $responses
     */
    private function createApi(array $responses): RecordingApi
    {
        $client = new Client(['handler' => HandlerStack::create(new MockHandler($responses))]);

        $clientFactory = $this->createMock(ClientFactory::class);
        $clientFactory->method('create')->willReturn($client);

        return new RecordingApi(new NullLogger(), $this->createConfig(), $clientFactory);
    }

    private function createConfig(): Config
    {
        return new Config(
            [
                'parameters' => [
                    'subscriptionId' => '1234',
                    // One try only, so the exponential backoff of the retry proxy does not slow the tests.
                    'maxTries' => 1,
                    'export' => [
                        'destination' => 'destination-table',
                        'groupingDimensions' => ['ServiceName'],
                    ],
                ],
            ],
            new ConfigDefinition()
        );
    }
}
