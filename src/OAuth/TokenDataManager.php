<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\OAuth;

use ArrayObject;
use Keboola\Component\JsonHelper;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Keboola\AzureCostExtractor\Exception\AccessTokenInitException;

class TokenDataManager
{
    public const STATE_AUTH_DATA_KEY = '#refreshed_auth_data'; // # -> must be encrypted!

    private array $configAuthData;

    private ArrayObject $state;

    public function __construct(array $configAuthData, ArrayObject $state)
    {
        $this->configAuthData = $configAuthData;
        $this->state = $state;

        // Check required keys
        $missingKeys = array_diff(['access_token', 'refresh_token'], array_keys($this->configAuthData));
        if ($missingKeys) {
            throw new AccessTokenInitException(
                sprintf('Missing key "%s" in OAuth data array.', implode('", "', $missingKeys))
            );
        }
    }

    public function load(): array
    {
        // Load tokens from state.json
        $authDataJson = $this->state[self::STATE_AUTH_DATA_KEY] ?? null;
        if (is_string($authDataJson)) {
            return JsonHelper::decode($authDataJson);
        }

        // Or use default from the configuration
        return $this->configAuthData;
    }

    public function store(AccessTokenInterface $newToken): void
    {
        // See AccessToken::jsonSerialize
        $this->state[self::STATE_AUTH_DATA_KEY] = json_encode($newToken);
    }
}
