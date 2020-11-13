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

    private Config $config;

    private Api $api;

    private ResponseWriter $responseWriter;

    private RequestFactory $requestFactory;

    public function __construct(
        LoggerInterface $logger,
        Config $config,
        Api $api,
        ResponseWriter $responseWriter,
        RequestFactory $requestFactory
    ) {
        $this->logger = $logger;
        $this->config = $config;
        $this->api = $api;
        $this->responseWriter = $responseWriter;
        $this->requestFactory = $requestFactory;
    }

    public function extract(): void
    {
        $this->logger->info(sprintf('Export "%s" started.', $this->config->getConfigRowName()));

        try {
            $this->doExtract();
        } finally {
            $this->responseWriter->finish();
        }
    }

    private function doExtract(): void
    {
        $responses = $this->api->send($this->requestFactory->create());
        foreach ($responses as $response) {
            $count = $this->responseWriter->writeResponse($response);
            if ($count) {
                $this->logger->info(sprintf('Written "%s" rows to the CSV file.', $count));
            } else {
                $this->logger->info('No rows in the response.');
            }
        }
    }
}
