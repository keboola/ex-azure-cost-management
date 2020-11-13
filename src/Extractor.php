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

    public function __construct(LoggerInterface $logger, Api $api, RequestFactory $requestFactory)
    {
        $this->logger = $logger;
        $this->api = $api;
        $this->requestFactory = $requestFactory;
    }

    public function extract(): void
    {
        $response = $this->api->send($this->requestFactory->create());
        var_export($response->getBody()->getContents());
    }
}
