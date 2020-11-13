<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor;

use Psr\Log\LoggerInterface;
use Keboola\AzureCostExtractor\Api\RequestFactory;
use Keboola\AzureCostExtractor\Api\Api;

class Extractor
{
    private LoggerInterface $logger;

    private Api $api;

    private RequestFactory $requestFactory;

    private CsvResponseWriter $csvResponseWriter;

    public function __construct(
        LoggerInterface $logger,
        Api $api,
        RequestFactory $requestFactory,
        CsvResponseWriter $csvResponseWriter
    ) {
        $this->logger = $logger;
        $this->api = $api;
        $this->requestFactory = $requestFactory;
        $this->csvResponseWriter = $csvResponseWriter;
    }

    public function extract(): void
    {
        $responses = $this->api->send($this->requestFactory->create());
        foreach ($responses as $response) {
            $this->csvResponseWriter->write($response);
        }
    }
}
