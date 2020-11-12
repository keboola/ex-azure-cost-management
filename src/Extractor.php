<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

class Extractor
{
    private LoggerInterface $logger;

    private Client $client;

    public function __construct(LoggerInterface $logger, Client $client)
    {
        $this->logger = $logger;
        $this->client = $client;
    }
}
