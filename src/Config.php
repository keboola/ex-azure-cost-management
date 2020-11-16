<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor;

use Keboola\AzureCostExtractor\Exception\InvalidAuthDataException;
use Keboola\Component\Config\BaseConfig;
use Keboola\Component\JsonHelper;

class Config extends BaseConfig
{
    public function getMaxTries(): int
    {
        return (int) $this->getValue(['parameters', 'maxTries']);
    }

    public function getDestination(): string
    {
        return $this->getValue(['parameters', 'export', 'destination']);
    }

    public function getSubscriptionId(): string
    {
        return $this->getValue(['parameters', 'subscriptionId']);
    }

    public function getType(): string
    {
        return $this->getValue(['parameters', 'export', 'type']);
    }

    public function getAggregation(): string
    {
        return $this->getValue(['parameters', 'export', 'aggregation']);
    }

    public function getGranularity(): string
    {
        return $this->getValue(['parameters', 'export', 'granularity']);
    }

    public function isIncrementalLoad(): bool
    {
        return $this->getValue(['parameters', 'export', 'incremental']);
    }

    public function getTimeFrame(): string
    {
        return $this->getValue(['parameters', 'export', 'timeDimension', 'timeFrame']);
    }

    public function getTimeDimensionStart(): string
    {
        return $this->getValue(['parameters', 'export', 'timeDimension', 'start']);
    }

    public function getTimeDimensionEnd(): string
    {
        return $this->getValue(['parameters', 'export', 'timeDimension', 'end']);
    }

    public function getGroupingDimensions(): array
    {
        return $this->getValue(['parameters', 'export', 'groupingDimensions']);
    }

    public function getOAuthApiData(): array
    {
        $data = parent::getOAuthApiData();

        if (empty($data)) {
            return [];
        }

        if (!is_string($data)) {
            throw new InvalidAuthDataException('Value of "authorization.oauth_api.credentials.#data".');
        }

        try {
            return JsonHelper::decode($data);
        } catch (\Throwable $e) {
            throw new InvalidAuthDataException(sprintf(
                'Value of "authorization.oauth_api.credentials.#data" must be valid JSON, sample: "%s"',
                substr($data, 0, 16)
            ));
        }
    }
}
