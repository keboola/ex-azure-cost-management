<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Exports;

use Generator;
use GuzzleHttp\Psr7\Request;

class RequestsGenerator
{
    public const API_VERSION = '2019-11-01';

    /**
     * @param iterable|Export[] $exports
     * @return Generator|ExportRequest[]
     */
    public function generateRequests(iterable $exports): Generator
    {
        $method = 'POST';
        $uri = 'query?api-version=' . self::API_VERSION;
        $headers = ['Content-Type' => 'application/json'];

        foreach ($exports as $export) {
            $request = new Request($method, $uri, $headers, json_encode($export->getBody()));
            yield new ExportRequest($export, $request);
        }
    }
}
