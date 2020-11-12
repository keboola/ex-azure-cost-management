<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Tests;

use ArrayObject;
use Keboola\AzureCostExtractor\Exception\AccessTokenRefreshException;
use Keboola\AzureCostExtractor\OAuth\TokenDataManager;
use Keboola\Component\JsonHelper;
use PHPUnit\Framework\Assert;

class OAuthTest extends BaseTest
{
    public function testEmptyState(): void
    {
        // State is empty
        $this->state = new ArrayObject();
        $originAccessToken = (string) getenv('OAUTH_ACCESS_TOKEN');
        $originRefreshToken = (string) getenv('OAUTH_REFRESH_TOKEN');

        // Refresh tokens
        $tokenProvider = $this->createTokenProvider();
        $newAccessToken = $tokenProvider->get();

        // We have a new access token
        Assert::assertNotEmpty($newAccessToken->getToken());
        Assert::assertNotSame($originAccessToken, $newAccessToken->getToken());
        Assert::assertNotSame($originRefreshToken, $newAccessToken->getRefreshToken());

        // And tokens are stored to state
        $state = $this->state->getArrayCopy();
        $dataRaw = $state[TokenDataManager::STATE_AUTH_DATA_KEY];
        $data = JsonHelper::decode($dataRaw);
        Assert::assertNotEmpty($data['access_token']);
        Assert::assertNotEmpty($data['refresh_token']);
        Assert::assertNotSame($originAccessToken, $data['access_token']);
        Assert::assertNotSame($originRefreshToken, $data['refresh_token']);
    }

    public function testEmptyStateInvalidTokens(): void
    {
        $tokenProvider = $this->createTokenProvider([
            'access_token' => 'invalid',
            'refresh_token' => 'invalid',
        ]);

        $this->expectException(AccessTokenRefreshException::class);
        $this->expectExceptionMessage(
            'Microsoft OAuth API token refresh failed, please reset authorization in the extractor configuration.'
        );
        $tokenProvider->get();
    }

    public function testState(): void
    {
        // State contains valid tokens, from the previous run
        $originAccessToken = (string) getenv('OAUTH_ACCESS_TOKEN');
        $originRefreshToken = (string) getenv('OAUTH_REFRESH_TOKEN');
        $this->state = new ArrayObject([
            TokenDataManager::STATE_AUTH_DATA_KEY => json_encode([
                'access_token' => $originAccessToken,
                'refresh_token' => $originRefreshToken,
            ]),
        ]);

        // And configuration contains expired old tokens, but they are not used
        $tokenProvider = $this->createTokenProvider([
            'access_token' => 'old',
            'refresh_token' => 'old',
        ]);
        $newAccessToken = $tokenProvider->get();

        // We have a new access token
        Assert::assertNotEmpty($newAccessToken->getToken());
        Assert::assertNotSame($originAccessToken, $newAccessToken->getToken());
        Assert::assertNotSame($originRefreshToken, $newAccessToken->getRefreshToken());

        // And tokens are stored to state
        $state = $this->state->getArrayCopy();
        $dataRaw = $state[TokenDataManager::STATE_AUTH_DATA_KEY];
        Assert::assertIsString($dataRaw);
        $data = JsonHelper::decode((string) $dataRaw);
        Assert::assertNotEmpty($data['access_token']);
        Assert::assertNotEmpty($data['refresh_token']);
        Assert::assertNotSame($originAccessToken, $data['access_token']);
        Assert::assertNotSame($originRefreshToken, $data['refresh_token']);
    }

    public function testStateInvalidTokens(): void
    {
        $this->state = new ArrayObject([
            TokenDataManager::STATE_AUTH_DATA_KEY => json_encode([
                'access_token' => 'invalid',
                'refresh_token' => 'invalid',
            ]),
        ]);
        $tokenProvider = $this->createTokenProvider([
            'access_token' => 'invalid',
            'refresh_token' => 'invalid',
        ]);

        $this->expectException(AccessTokenRefreshException::class);
        $this->expectExceptionMessage(
            'Microsoft OAuth API token refresh failed, please reset authorization in the extractor configuration.'
        );
        $tokenProvider->get();
    }
}
