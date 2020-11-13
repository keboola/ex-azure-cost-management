<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor;

use Keboola\AzureCostExtractor\Csv\ResponseWriter;
use Psr\Log\LoggerInterface;
use Keboola\AzureCostExtractor\Api\RequestFactory;
use Keboola\AzureCostExtractor\Api\Api;

class Extractor
{
    private LoggerInterface $logger;

    private Api $api;

    private ResponseWriter $responseWriter;

    private RequestFactory $requestFactory;

    public function __construct(
        LoggerInterface $logger,
        Api $api,
        ResponseWriter $responseWriter,
        RequestFactory $requestFactory
    ) {
        $this->logger = $logger;
        $this->api = $api;
        $this->responseWriter = $responseWriter;
        $this->requestFactory = $requestFactory;
    }

    public function extract(): void
    {
        $responses = $this->api->send($this->requestFactory->create());
        foreach ($responses as $response) {
            $this->responseWriter->writeResponse($response);
        }

        $this->responseWriter->writeManifest();
    }
}
