<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Api;

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

    public function __construct(LoggerInterface $logger, Client $client)
    {
        $this->logger = $logger;
        $this->client = $client;
    }

    public function send(ExportRequest $request): ResponseInterface
    {
        return $this->doSend($request);
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
        $error =
            $this->getMessageFromResponse($exception->getResponse()) ?:
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

        // Convert to ExportRequestException
        $reqException = new ExportRequestException($msg, $exception->getCode(), $exception);

        // Wrap to UserException according http code
        if (
            // Unauthorized 401
            $exception->getCode() === 401 ||
            // Forbidden 403
            $exception->getCode() === 403 ||
            // Server error 5xx
            ($exception->getCode() >= 500 && $exception->getCode() < 600)
        ) {
            return new UserException($reqException->getMessage(), $reqException->getCode(), $reqException);
        }

        return $reqException;
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

        if (!isset($responseBody['error']['code']) || !isset($responseBody['error']['message'])) {
            return null;
        }

        return sprintf('error_code="%s", message="%s"', $responseBody['error']['code'], $responseBody['error']['message']);
    }

    private function createRetryProxy(): void
    {
    }
}
