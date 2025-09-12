<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Auth;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Keboola\AzureCostExtractor\Exception\AccessTokenRefreshException;

class RefreshTokenProvider implements TokenProvider
{
    private const AUTHORIZE_ENDPOINT = '/oauth2/v2.0/authorize';
    private const TOKEN_ENDPOINT = '/oauth2/v2.0/token';
    private const SCOPES = ['offline_access', 'https://management.core.windows.net/user_impersonation'];

    private string $appId;

    private string $appSecret;

    private TokenDataManager $dataManager;
    private string $authorityUrl;

    public function __construct(string $appId, string $appSecret, TokenDataManager $dataManager, string $authorityUrl)
    {
        $this->appId = $appId;
        $this->appSecret = $appSecret;
        $this->dataManager = $dataManager;
        $this->authorityUrl = $authorityUrl;
    }

    public function get(): AccessTokenInterface
    {
        $provider = $this->createOAuthProvider($this->appId, $this->appSecret);
        $tokens = $this->dataManager->load();

        // It is needed to always refresh token, because original token expires after 1 hour
        $newToken = null;
        $exception = null;

        // Try token from stored state, and from the configuration.
        foreach ($tokens as $token) {
            try {
                $newToken = $provider->getAccessToken(
                    'refresh_token',
                    ['refresh_token' => $token->getRefreshToken()]
                );
                break;
            } catch (IdentityProviderException $exception) {
                // try next token
            }
        }

        if (!$newToken) {
            throw new AccessTokenRefreshException(
                'Microsoft OAuth API token refresh failed, ' .
                'please reset authorization in the extractor configuration.',
                0,
                $exception
            );
        }

        $this->dataManager->store($newToken);
        return $newToken;
    }

    private function createOAuthProvider(string $appId, string $appSecret): GenericProvider
    {
        return new GenericProvider([
            'clientId' => $appId,
            'clientSecret' => $appSecret,
            'urlAuthorize' => $this->authorityUrl . self::AUTHORIZE_ENDPOINT,
            'urlAccessToken' => $this->authorityUrl . self::TOKEN_ENDPOINT,
            'urlResourceOwnerDetails' => '',
            'scopes' => implode(' ', self::SCOPES),
        ]);
    }
}
