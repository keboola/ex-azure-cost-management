<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Api;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

class ApiFactory
{
    private LoggerInterface $logger;

    private Client $client;

    public function __construct(LoggerInterface $logger, Client $client)
    {
        $this->logger = $logger;
        $this->client = $client;
    }

    public function create(): Api
    {
        return new Api($this->logger, $this->client);
    }
}
