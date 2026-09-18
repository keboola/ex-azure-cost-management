<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Api;

use Generator;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Keboola\AzureCostExtractor\Config;
use Throwable;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Exception\RequestException;
use Retry\BackOff\ExponentialBackOffPolicy;
use Retry\Policy\SimpleRetryPolicy;
use Retry\RetryProxy;
use Keboola\AzureCostExtractor\Exception\ExportRequestRetryException;
use Keboola\AzureCostExtractor\Exception\ExportRequestException;
use Keboola\Component\JsonHelper;
use Keboola\Component\UserException;

class Api
{
    /**
     * Azure Cost Management throttles at several scopes and reports the wait time in a
     * different header for each scope. Only the entity scope used to be read, so a throttle at
     * any other scope looked like "429 without Retry-After" and fell back to blind exponential
     * backoff, which is often far shorter than the wait Azure actually asked for.
     *
     * All scopes are read and the longest requested wait wins, because a response can carry
     * several scopes at once with different values. Note the name is "clienttype", not "client".
     * @see https://learn.microsoft.com/en-us/azure/cost-management-billing/automate/get-small-usage-datasets-on-demand
     */
    private const RETRY_AFTER_HEADERS = [
        'x-ms-ratelimit-microsoft.costmanagement-entity-retry-after',
        'x-ms-ratelimit-microsoft.costmanagement-clienttype-retry-after',
        'x-ms-ratelimit-microsoft.costmanagement-client-retry-after',
        'x-ms-ratelimit-microsoft.costmanagement-tenant-retry-after',
        'x-ms-ratelimit-microsoft.costmanagement-qpu-retry-after',
        'Retry-After',
    ];

    /** Added to the wait the API asks for, as a safety margin. */
    private const RATE_LIMIT_WAIT_SAFETY_SECONDS = 3;

    /**
     * Every 429 waits at least this long. Azure Cost Management enforces its limits over
     * windows of roughly 30-60 seconds, so a shorter wait is throttled again and only burns
     * a retry. This is also the wait used when no scope reports a value we can read.
     */
    private const MIN_RATE_LIMIT_WAIT_SECONDS = 30;

    /**
     * A single 429 never waits longer than this, whatever the API asks for. Azure can report a
     * wait tied to an hourly quota; without a cap one throttled request could hold the job for
     * hours. Waiting less than asked is safe - the request is simply throttled again and waits
     * again, within the retry budget below.
     */
    private const MAX_RATE_LIMIT_WAIT_SECONDS = 120;

    /**
     * Lower bound of the 429 retry budget. A rate limit is not an error in the extractor, so it
     * gets its own budget instead of consuming the generic "maxTries" retries. A user who needs
     * more headroom can raise "maxTries" above this value.
     */
    private const MIN_RATE_LIMIT_RETRIES = 7;

    private LoggerInterface $logger;

    private Config $config;

    private ClientFactory $clientFactory;

    private Client $client;

    public function __construct(LoggerInterface $logger, Config $config, ClientFactory $clientFactory)
    {
        $this->logger = $logger;
        $this->config = $config;
        $this->clientFactory = $clientFactory;
        $this->login();
    }

    /**
     * Send request and load next pages, if "nextLink" is present in the response.
     * Returns decoded JSON body
     * @param Request $request
     * @return Generator|array[]
     */
    public function send(Request $request): Generator
    {
        $page = 1;
        while (true) {
            // Send request
            $response = $this->sendOneRequest($request);
            $body = JsonHelper::decode($response->getBody()->getContents());
            yield $body;

            // Load next page
            $nextLink = $body['properties']['nextLink'] ?? null;
            if ($nextLink) {
                $page++;
                $request = $request->withUri(new Uri($nextLink));
                $this->logger->info(sprintf('Loading the next results, page %s.', $page));
            } else {
                break;
            }
        }
    }

    public function sendOneRequest(Request $request): ResponseInterface
    {
        try {
            /** @var ResponseInterface $response */
            $response = $this->createRetryProxy()->call(function () use ($request) {
                return $this->doSendOneRequest($request);
            });
            return $response;
        } catch (ExportRequestException $e) {
            // A 429 that survived every retry is an upstream throttle, not a bug in the extractor.
            // Surface it as a user error (exit code 1) with an actionable message, instead of
            // an opaque application error (exit code 2).
            if ($e->getCode() === 429) {
                throw new UserException(
                    sprintf(
                        'Azure Cost Management API rate limit was reached and did not clear after %d tries. '
                        . 'These limits are shared by all requests in your Azure tenant. Please run this '
                        . 'configuration less often, schedule it apart from your other Azure Cost Management '
                        . 'configurations, or increase the "maxTries" parameter. Details: %s',
                        $this->getRateLimitRetryBudget(),
                        $e->getMessage()
                    ),
                    $e->getCode(),
                    $e
                );
            }

            throw $this->isUserException($e) ? new UserException($e->getMessage(), $e->getCode(), $e) : $e;
        }
    }

    /**
     * Seam for the tests, so they can assert the requested waits without really sleeping.
     */
    protected function waitForRateLimit(int $seconds): void
    {
        sleep($seconds);
    }

    private function doSendOneRequest(Request $request): ResponseInterface
    {
        $maxRateLimitRetries = $this->getRateLimitRetryBudget();
        $rateLimitRetries = 0;
        while (true) {
            try {
                return $this->client->send($request);
            } catch (RequestException $e) {
                // All errors other than a rate limit go through normal exception processing
                if ($e->getCode() !== 429) {
                    throw $this->processException($request, $e);
                }

                // A rate limit is handled here, in its own retry budget, so it does not consume
                // the generic "maxTries" retries of the retry proxy.
                $rateLimitRetries++;
                if ($rateLimitRetries > $maxRateLimitRetries) {
                    // The budget is used up. Throw an exception the retry proxy does NOT retry,
                    // otherwise it would run this whole loop again for every remaining try.
                    // sendOneRequest() turns this into a UserException.
                    throw new ExportRequestException(
                        $this->formatErrorMessage($request, $e),
                        $e->getCode(),
                        $e
                    );
                }

                $waitSeconds = $this->resolveRateLimitWaitSeconds($e->getResponse());
                $this->logger->info(sprintf(
                    'Rate limit exceeded (429), waiting %d seconds before retry (attempt %d/%d). %s',
                    $waitSeconds,
                    $rateLimitRetries,
                    $maxRateLimitRetries,
                    $this->formatRateLimitHeaders($e->getResponse())
                ));
                $this->waitForRateLimit($waitSeconds);
            }
        }
    }

    private function processException(Request $request, RequestException $exception): Throwable
    {
        $msg = $this->formatErrorMessage($request, $exception);

        // In case of error 401 try to log in again, the token maybe expired
        if ($exception->getCode() === 401) {
            $this->logger->info('Unauthorized, trying to log in again.');

            // Failed login throw a user error
            $this->login();

            // If login passed -> retry
            return new ExportRequestRetryException($msg, $exception->getCode(), $exception);
        }

        if ($this->isRetryException($exception)) {
            return new ExportRequestRetryException($msg, $exception->getCode(), $exception);
        }

        return new ExportRequestException($msg, $exception->getCode(), $exception);
    }

    private function formatErrorMessage(Request $request, RequestException $exception): string
    {
        // Rewind body stream
        $requestBody = $request->getBody();
        $requestBody->rewind();

        // Format error from the response, or use exception message
        $error = $this->getMessageFromResponse($exception->getResponse()) ?:
            sprintf('message=%s', $exception->getMessage());

        // Format full exception message
        return sprintf(
            'Export "%s" failed: http_code="%d", %s, request_body="%s", uri="%s"',
            $this->config->getDestination(),
            $exception->getCode(),
            $error,
            $requestBody->getContents(),
            $exception->getRequest()->getUri()
        );
    }

    private function getMessageFromResponse(?ResponseInterface $response): ?string
    {
        if (!$response) {
            return null;
        }

        try {
            $responseBody = JsonHelper::decode($response->getBody()->getContents());
        } catch (Throwable $e) {
            // Ignore invalid JSON and other errors
            return null;
        }

        if (!isset($responseBody['error']['code'])) {
            return null;
        }

        if (!isset($responseBody['error']['message'])) {
            return null;
        }

        return sprintf(
            'error_code="%s", message="%s"',
            $responseBody['error']['code'],
            $responseBody['error']['message']
        );
    }

    private function isUserException(ExportRequestException $e): bool
    {
        return
            // Bad Request 400 (eg. bad date), Unauthorized 401, Forbidden 403, Not Found 404, Conflict 409
            in_array($e->getCode(), [400, 401, 403, 404, 409], true) ||
            // Server error 5xx
            ($e->getCode() >= 500 && $e->getCode() < 600);
    }


    private function isRetryException(RequestException $e): bool
    {
        // Don't retry Bad Request 400, Unauthorized 401, Forbidden 403, Not Found 404
        if (in_array($e->getCode(), [400, 401,403,404], true)) {
            return false;
        }

        return true;
    }

    private function formatRateLimitHeaders(?ResponseInterface $response): string
    {
        if (!$response) {
            return '';
        }

        $rateLimitHeaders = [];
        foreach ($response->getHeaders() as $name => $values) {
            if (stripos($name, 'x-ms-ratelimit') === 0) {
                $rateLimitHeaders[] = sprintf('%s: %s', $name, implode(', ', $values));
            }
        }

        if (empty($rateLimitHeaders)) {
            return '';
        }

        return 'Rate limit headers: ' . implode('; ', $rateLimitHeaders);
    }

    /**
     * How long to wait before retrying a rate limited (429) request.
     *
     * The longest wait requested by any scope wins, plus a safety margin, bounded by
     * MIN_RATE_LIMIT_WAIT_SECONDS and MAX_RATE_LIMIT_WAIT_SECONDS. When no scope reports a
     * value that can be read as seconds, the minimum is used.
     */
    private function resolveRateLimitWaitSeconds(?ResponseInterface $response): int
    {
        $requestedSeconds = null;
        foreach (self::RETRY_AFTER_HEADERS as $headerName) {
            $values = $response !== null ? $response->getHeader($headerName) : [];
            if ($values === [] || !is_numeric($values[0])) {
                // The standard Retry-After header also allows an HTTP date, and the
                // "...-remaining-..." headers carry values like "DefaultQuota:3".
                // Neither is a number of seconds, so neither must be read as one.
                continue;
            }

            $seconds = (int) $values[0];
            $requestedSeconds = $requestedSeconds === null ? $seconds : max($requestedSeconds, $seconds);
        }

        if ($requestedSeconds === null) {
            return self::MIN_RATE_LIMIT_WAIT_SECONDS;
        }

        $waitSeconds = $requestedSeconds + self::RATE_LIMIT_WAIT_SAFETY_SECONDS;
        return min(
            max($waitSeconds, self::MIN_RATE_LIMIT_WAIT_SECONDS),
            self::MAX_RATE_LIMIT_WAIT_SECONDS
        );
    }

    private function getRateLimitRetryBudget(): int
    {
        return max(self::MIN_RATE_LIMIT_RETRIES, $this->config->getMaxTries());
    }

    private function createRetryProxy(): RetryProxy
    {
        $retryPolicy = new SimpleRetryPolicy($this->config->getMaxTries(), [ExportRequestRetryException::class]);
        $backOffPolicy = new ExponentialBackOffPolicy(5000, 2.0, 120000);
        return new RetryProxy(
            $retryPolicy,
            $backOffPolicy,
            $this->logger,
        );
    }

    private function login(): void
    {
        // (Re)Create client -> enforcing a new authorization
        $this->client = $this->clientFactory->create();
    }
}
