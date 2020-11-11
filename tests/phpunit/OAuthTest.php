<?php

declare(strict_types=1);

namespace AzureCostExtractor\Tests;

use AzureCostExtractor\AccessTokenFactory;
use PHPUnit\Framework\Assert;

class OAuthTest extends BaseTest
{
    public function testRefreshToken(): void
    {
        $tokenFactory = $this->createTokenFactory();
        $newAccessToken = $tokenFactory->create();

        // We have a new access token
        Assert::assertNotEmpty($newAccessToken->getToken());
        Assert::assertNotSame((string) getenv('OAUTH_ACCESS_TOKEN'), $newAccessToken->getToken());
    }
}
