<?php

declare(strict_types=1);

namespace AzureCostExtractor;

use AzureCostExtractor\Exception\InvalidAuthDataException;
use Keboola\Component\Config\BaseConfig;
use Keboola\Component\JsonHelper;

class Config extends BaseConfig
{
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
