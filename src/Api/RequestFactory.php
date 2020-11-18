<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Api;

use GuzzleHttp\Psr7\Request;
use Keboola\AzureCostExtractor\Config;
use Keboola\AzureCostExtractor\ConfigDefinition;
use Keboola\Component\JsonHelper;

class RequestFactory
{
    public const API_VERSION = '2019-11-01';

    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function create(): Request
    {
        $method = 'POST';
        $uri = 'query?api-version=' . self::API_VERSION;
        $headers = ['Content-Type' => 'application/json'];
        return new Request($method, $uri, $headers, JsonHelper::encode($this->getBody()));
    }

    private function getBody(): array
    {
        $body = [
            'type' => $this->config->getType(),
            'timeframe' => $this->config->getTimeFrame(),
            'dataset' => [
                'granularity' => $this->config->getGranularity(),
                'grouping' => array_map(
                    fn(string $dimension) => [
                        'type' => 'Dimension',
                        'name' => $dimension,
                    ],
                    $this->config->getGroupingDimensions()
                ),
                'aggregation' => [
                    $this->config->getAggregation() => [
                        'name' => $this->config->getAggregation(),
                        'function' => 'Sum',
                    ],
                ],
            ],
        ];

        if ($this->config->getTimeFrame() === ConfigDefinition::TIME_FRAME_CUSTOM) {
            $body['timePeriod'] = [
                'from' => $this->config->getTimeDimensionStart(),
                'to' => $this->config->getTimeDimensionEnd(),
            ];
        }

        return $body;
    }
}
