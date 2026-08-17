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
                        $this->config->getMaxTries(),
                        $e->getMessage()
                    ),
                    $e->getCode(),
                    $e
                );
            }

            throw $this->isUserException($e) ? new UserException($e->getMessage(), $e->getCode(), $e) : $e;
        }
    }

    private function doSendOneRequest(Request $request): ResponseInterface
    {
        $maxRateLimitRetries = 7;
        $rateLimitRetries = 0;
        while (true) {
            try {
                return $this->client->send($request);
            } catch (RequestException $e) {
                // Handle 429 with Retry-After header specially - don't count against retry limit
                if ($e->getCode() === 429) {
                    $retryAfter = $this->extractRetryAfterSeconds($e->getResponse());
                    if ($retryAfter !== null) {
                        $rateLimitRetries++;
                        if ($rateLimitRetries > $maxRateLimitRetries) {
                            throw $this->processException($request, $e);
                        }
                        $rateLimitInfo = $this->formatRateLimitHeaders($e->getResponse());
                        $this->logger->info(sprintf(
                            'Rate limit exceeded (429), waiting %d seconds before retry (attempt %d/%d). %s',
                            $retryAfter,
                            $rateLimitRetries,
                            $maxRateLimitRetries,
                            $rateLimitInfo
                        ));
                        sleep($retryAfter);
                        continue;
                    }
                }

                // All other errors go through normal exception processing
                throw $this->processException($request, $e);
            }
        }
    }

    private function processException(Request $request, RequestException $exception): Throwable
    {
        // Rewind body stream
        $requestBody = $request->getBody();
        $requestBody->rewind();

        // Format error from the response, or use exception message
        $error = $this->getMessageFromResponse($exception->getResponse()) ?:
            sprintf('message=%s', $exception->getMessage());

        // Format full exception message
        $msg = sprintf(
            'Export "%s" failed: http_code="%d", %s, request_body="%s", uri="%s"',
            $this->config->getDestination(),
            $exception->getCode(),
            $error,
            $requestBody->getContents(),
            $exception->getRequest()->getUri()
        );

        // In case of error 401 try to log in again, the token maybe expired
        if ($exception->getCode() === 401) {
            $this->logger->info('Unauthorized, trying to log in again.');

            // Failed login throw a user error
            $this->login();

            // If login passed -> retry
            return new ExportRequestRetryException($msg, $exception->getCode(), $exception);
        }

        if ($exception->getCode() === 429) {
            // 429 without Retry-After header - use exponential backoff (counts against maxTries)
            $rateLimitInfo = $this->formatRateLimitHeaders($exception->getResponse());
            $this->logger->info(sprintf(
                'Rate limit exceeded (429) without Retry-After header, will retry with backoff. %s',
                $rateLimitInfo
            ));
            return new ExportRequestRetryException($msg, $exception->getCode(), $exception);
        }

        if ($this->isRetryException($exception)) {
            return new ExportRequestRetryException($msg, $exception->getCode(), $exception);
        }

        return new ExportRequestException($msg, $exception->getCode(), $exception);
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
     * Azure Cost Management throttles per entity, but also per QPU, per tenant and per client,
     * and reports the wait time in a different header for each scope. Only the entity scope
     * was read, so a tenant/client/QPU throttle (the limits are shared across the whole
     * tenant) looked like "429 without Retry-After" and fell back to blind exponential
     * backoff, which is often far shorter than the wait Azure actually asked for.
     * @see https://learn.microsoft.com/en-us/azure/cost-management-billing/automate/get-small-usage-datasets-on-demand
     */
    private const FALLBACK_RETRY_AFTER_HEADERS = [
        'x-ms-ratelimit-microsoft.costmanagement-qpu-retry-after',
        'x-ms-ratelimit-microsoft.costmanagement-tenant-retry-after',
        'x-ms-ratelimit-microsoft.costmanagement-client-retry-after',
        'Retry-After',
    ];

    private function extractRetryAfterSeconds(?ResponseInterface $response): ?int
    {
        if (!$response) {
            return null;
        }

        // Check for the Azure Cost Management specific retry-after header
        $header = $response->getHeader('x-ms-ratelimit-microsoft.costmanagement-entity-retry-after');
        if (!empty($header)) {
            return (int) $header[0] + 3; // waiting for 3 more seconds for safety
        }

        // The entity header is absent, so this is a throttle at another scope.
        // Honour the first other scope header that carries a numeric delay,
        // instead of falling through to blind exponential backoff.
        foreach (self::FALLBACK_RETRY_AFTER_HEADERS as $headerName) {
            $header = $response->getHeader($headerName);
            if (!empty($header) && is_numeric($header[0])) {
                return (int) $header[0] + 3; // waiting for 3 more seconds for safety
            }
        }

        return null;
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
