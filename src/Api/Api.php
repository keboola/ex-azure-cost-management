<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Api;

use Keboola\AzureCostExtractor\Exception\ExportRequestRetryException;
use Retry\BackOff\ExponentialBackOffPolicy;
use Retry\Policy\SimpleRetryPolicy;
use Retry\RetryProxy;
use Throwable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Keboola\AzureCostExtractor\Exception\ExportRequestException;
use Keboola\AzureCostExtractor\Exports\ExportRequest;
use Keboola\Component\JsonHelper;
use Keboola\Component\UserException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class Api
{
    private LoggerInterface $logger;

    private Client $client;

    private int $maxTries;

    public function __construct(LoggerInterface $logger, Client $client, int $maxTries)
    {
        $this->logger = $logger;
        $this->client = $client;
        $this->maxTries = $maxTries;
    }

    public function send(ExportRequest $request): ResponseInterface
    {
        try {
            /** @var ResponseInterface $response */
            $response = $this->createRetryProxy()->call(function () use ($request) {
                return $this->doSend($request);
            });
            return $response;
        } catch (ExportRequestException $e) {
            throw $this->isUserException($e) ? new UserException($e->getMessage(), $e->getCode(), $e) : $e;
        }
    }

    private function doSend(ExportRequest $request): ResponseInterface
    {
        try {
            return $this->client->send($request->getRequest());
        } catch (RequestException $e) {
            throw $this->processException($request, $e);
        }
    }

    private function processException(ExportRequest $request, RequestException $exception): Throwable
    {
        // Rewind body stream
        $requestBody = $request->getRequest()->getBody();
        $requestBody->rewind();

        // Format error from the response, or use exception message
        $error = $this->getMessageFromResponse($exception->getResponse()) ?:
            sprintf('message=%s', $exception->getMessage());

        // Format full exception message
        $msg = sprintf(
            'Export "%s" failed: http_code="%d", %s, request_body="%s", uri="%s"',
            $request->getExport()->getName(),
            $exception->getCode(),
            $error,
            $requestBody->getContents(),
            $exception->getRequest()->getUri()
        );

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

        return sprintf('error_code="%s", message="%s"', $responseBody['error']['code'], $responseBody['error']['message']);
    }

    private function isUserException(ExportRequestException $e): bool
    {
        return
            // Unauthorized 401, Forbidden 403, Not Found 404, Conflict 409
            in_array($e->getCode(), [401, 403, 404, 409], true) ||
            // Server error 5xx
            ($e->getCode() >= 500 && $e->getCode() < 600);
    }


    private function isRetryException(RequestException $e): bool
    {
        // Don't retry Unauthorized 401, Forbidden 403, Not Found 404
        if (in_array($e->getCode(), [401,403,404], true)) {
            return false;
        }

        return true;
    }

    private function createRetryProxy(): RetryProxy
    {
        $retryPolicy = new SimpleRetryPolicy($this->maxTries, [ExportRequestRetryException::class]);
        $backOffPolicy = new ExponentialBackOffPolicy();
        return new RetryProxy(
            $retryPolicy,
            $backOffPolicy,
            $this->logger,
        );
    }
}
