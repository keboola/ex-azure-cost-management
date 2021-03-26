<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Api;

use GuzzleHttp\Client;
use Keboola\AzureCostExtractor\Config;
use Psr\Log\LoggerInterface;

class ApiFactory
{
    private Config $config;

    private LoggerInterface $logger;

    private ClientFactory $clientFactory;

    public function __construct(LoggerInterface $logger, Config $config, ClientFactory $clientFactory)
    {
        $this->logger = $logger;
        $this->config = $config;
        $this->clientFactory = $clientFactory;
    }

    public function create(): Api
    {
        return new Api($this->logger, $this->config, $this->clientFactory);
    }
}
