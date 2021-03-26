<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Tests;

use ArrayObject;
use GuzzleHttp\Client;
use Keboola\AzureCostExtractor\Api\ClientFactory;
use Keboola\AzureCostExtractor\Auth\RefreshTokenProvider;
use Keboola\AzureCostExtractor\Auth\ServicePrincipalTokenProvider;
use Keboola\AzureCostExtractor\Auth\TokenDataManager;
use Keboola\AzureCostExtractor\Auth\TokenProvider;
use PHPUnit\Framework\TestCase;

abstract class BaseTest extends TestCase
{
    protected ArrayObject $state;

    protected function setUp(): void
    {
        parent::setUp();
        $this->state = new ArrayObject();
    }

    protected function createClient(): Client
    {
        $subscriptionId = (string) getenv('TEST_SUBSCRIPTION_ID');
        $factory = new ClientFactory($this->createRefreshTokenProvider(), $subscriptionId);
        return $factory->create();
    }

    protected function createRefreshTokenProvider(?array $oauthData = null): TokenProvider
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
        return new RefreshTokenProvider($appId, $appSecret, $dataManager);
    }

    protected function createServicePrincipalTokenProvider(): TokenProvider
    {
        $tenant = (string) getenv('SERVICE_PRINCIPAL_TENANT');
        $username = (string) getenv('SERVICE_PRINCIPAL_USERNAME');
        $password = (string) getenv('SERVICE_PRINCIPAL_PASSWORD');
        return new ServicePrincipalTokenProvider($tenant, $username, $password);
    }
}
