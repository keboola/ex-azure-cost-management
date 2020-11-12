<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor;

use Psr\Log\LoggerInterface;
use Keboola\AzureCostExtractor\Api\Api;
use Keboola\AzureCostExtractor\Exports\ExportsGenerator;
use Keboola\AzureCostExtractor\Exports\RequestsGenerator;

class Extractor
{
    private LoggerInterface $logger;

    private Api $api;

    private ExportsGenerator $exportsGenerator;

    private RequestsGenerator $requestsGenerator;

    public function __construct(LoggerInterface $logger, Api $api)
    {
        $this->logger = $logger;
        $this->api = $api;
        $this->exportsGenerator = new ExportsGenerator();
        $this->requestsGenerator = new RequestsGenerator();
    }

    public function extract(): void
    {
        $requests = $this->requestsGenerator->generateRequests(
            $this->exportsGenerator->generateExports()
        );

        foreach ($requests as $request) {
            $response = $this->api->send($request);
            var_dump(json_decode($response->getBody()->getContents()));
        }
    }
}
