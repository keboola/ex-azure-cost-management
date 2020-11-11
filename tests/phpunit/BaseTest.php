<?php

declare(strict_types=1);

namespace AzureCostExtractor\Tests;

use AzureCostExtractor\AccessTokenFactory;
use PHPUnit\Framework\TestCase;

abstract class BaseTest extends TestCase
{
    protected function createTokenFactory(): AccessTokenFactory
    {
        $appId = (string) getenv('OAUTH_APP_ID');
        $appSecret = (string) getenv('OAUTH_APP_SECRET');
        $accessToken = (string) getenv('OAUTH_ACCESS_TOKEN');
        $refreshToken = (string) getenv('OAUTH_REFRESH_TOKEN');
        $oauthData = [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
        ];
        return new AccessTokenFactory($appId, $appSecret, $oauthData);
    }
}
