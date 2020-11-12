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

    private Client $client;

    public function __construct(Config $config, LoggerInterface $logger, Client $client)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->client = $client;
    }

    public function create(): Api
    {
        return new Api($this->logger, $this->client, $this->config);
    }
}
