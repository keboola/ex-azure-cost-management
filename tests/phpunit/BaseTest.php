<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Tests;

use ArrayObject;
use Keboola\AzureCostExtractor\OAuth\TokenDataManager;
use Keboola\AzureCostExtractor\OAuth\TokenProvider;
use PHPUnit\Framework\TestCase;

abstract class BaseTest extends TestCase
{
    protected ArrayObject $state;

    protected function setUp(): void
    {
        parent::setUp();
        $this->state = new ArrayObject();
    }

    protected function createTokenProvider(?array $oauthData = null): TokenProvider
    {
        $appId = (string) getenv('OAUTH_APP_ID');
        $appSecret = (string) getenv('OAUTH_APP_SECRET');
        $accessToken = (string) getenv('OAUTH_ACCESS_TOKEN');
        $refreshToken = (string) getenv('OAUTH_REFRESH_TOKEN');
        $oauthData = $oauthData ?? [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
        ];
        $dataManager = new TokenDataManager($oauthData, $this->state);
        return new TokenProvider($appId, $appSecret, $dataManager);
    }
}
