<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Keboola\AzureCostExtractor\Api\Api;
use Keboola\AzureCostExtractor\Api\ClientFactory;
use Keboola\AzureCostExtractor\Config;
use Keboola\AzureCostExtractor\ConfigDefinition;
use Keboola\Component\UserException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Covers how the extractor reacts to HTTP 429 responses from the Azure Cost Management API.
 * These tests are hermetic - the Guzzle client is backed by a MockHandler, no network is used.
 */
class ApiRateLimitTest extends TestCase
{
    private const THROTTLED_BODY = '{"error":{"code":"429","message":"Too many requests. Please retry."}}';

    private const OK_BODY = '{"properties":{"rows":[],"columns":[]}}';

    private const ENTITY_RETRY_AFTER = 'x-ms-ratelimit-microsoft.costmanagement-entity-retry-after';

    /**
     * The entity scope header was already honoured before this test existed.
     * It is asserted here so the previously working path stays covered.
     */
    public function testEntityScopeRetryAfterHeaderIsHonoured(): void
    {
        $api = $this->createApi([
            new Response(429, [self::ENTITY_RETRY_AFTER => '0'], self::THROTTLED_BODY),
            new Response(200, [], self::OK_BODY),
        ]);

        $response = $api->sendOneRequest(new Request('POST', 'query'));

        Assert::assertSame(200, $response->getStatusCode());
        Assert::assertSame(self::OK_BODY, $response->getBody()->getContents());
    }

    /**
     * Azure throttles per entity, but also per QPU, per tenant and per client, and reports the
     * wait time in a different header for each scope. A throttle at any scope other than entity
     * used to look like "429 without Retry-After" and fell through to blind exponential backoff,
     * which regularly ran out of tries and killed the job with an application error.
     *
     * @dataProvider provideNonEntityRetryAfterHeaders
     */
    public function testNonEntityScopeRetryAfterHeaderIsHonoured(string $header): void
    {
        $api = $this->createApi([
            new Response(429, [$header => '0'], self::THROTTLED_BODY),
            new Response(200, [], self::OK_BODY),
        ]);

        $response = $api->sendOneRequest(new Request('POST', 'query'));

        Assert::assertSame(200, $response->getStatusCode());
        Assert::assertSame(self::OK_BODY, $response->getBody()->getContents());
    }

    /**
     * @return array<string, array{string}>
     */
    public function provideNonEntityRetryAfterHeaders(): array
    {
        return [
            'qpu scope' => ['x-ms-ratelimit-microsoft.costmanagement-qpu-retry-after'],
            'tenant scope' => ['x-ms-ratelimit-microsoft.costmanagement-tenant-retry-after'],
            'client scope' => ['x-ms-ratelimit-microsoft.costmanagement-client-retry-after'],
            'standard header' => ['Retry-After'],
        ];
    }

    /**
     * A non numeric delay (the standard Retry-After header also allows an HTTP date) must not be
     * read as "0 seconds"; the request falls back to the exponential backoff of the retry proxy.
     */
    public function testNonNumericFallbackRetryAfterHeaderIsIgnored(): void
    {
        $api = $this->createApi([
            new Response(429, ['Retry-After' => 'Wed, 21 Oct 2026 07:28:00 GMT'], self::THROTTLED_BODY),
        ]);

        $this->expectException(UserException::class);
        $api->sendOneRequest(new Request('POST', 'query'));
    }

    /**
     * A rate limit that never clears is an upstream throttle, not a bug in the extractor.
     * It has to surface as a user error (exit code 1) with an actionable message, rather than
     * as an opaque application error (exit code 2) that pages the team.
     */
    public function testPersistentRateLimitIsReportedAsUserError(): void
    {
        $api = $this->createApi([
            new Response(429, [], self::THROTTLED_BODY),
        ]);

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
    }

    /**
     * The 429 handling must not change how any other failure is reported.
     * A 404 was, and stays, a user error carrying the plain API message.
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
    }

    /**
     * @param Response[] $responses
     */
    private function createApi(array $responses): Api
    {
        $client = new Client(['handler' => HandlerStack::create(new MockHandler($responses))]);

        $clientFactory = $this->createMock(ClientFactory::class);
        $clientFactory->method('create')->willReturn($client);

        return new Api(new NullLogger(), $this->createConfig(), $clientFactory);
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
