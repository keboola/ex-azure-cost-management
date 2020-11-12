<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Keboola\AzureCostExtractor\Exception\AccessTokenInitException;
use Keboola\AzureCostExtractor\Exception\AccessTokenRefreshException;

class AccessTokenFactory
{
    private const AUTHORITY_URL = 'https://login.microsoftonline.com/common';
    private const AUTHORIZE_ENDPOINT = '/oauth2/v2.0/authorize';
    private const TOKEN_ENDPOINT = '/oauth2/v2.0/token';
    private const SCOPES = ['offline_access', 'https://management.core.windows.net/user_impersonation'];

    private string $appId;

    private string $appSecret;

    private array $authData;

    public function __construct(string $appId, string $appSecret, array $authData)
    {
        $this->appId = $appId;
        $this->appSecret = $appSecret;
        $this->authData = $authData;

        // Check required keys
        $missingKeys = array_diff(['access_token', 'refresh_token'], array_keys($authData));
        if ($missingKeys) {
            throw new AccessTokenInitException(
                sprintf('Missing key "%s" in OAuth data array.', implode('", "', $missingKeys))
            );
        }
    }

    public function create(): AccessTokenInterface
    {
        $provider = $this->createOAuthProvider($this->appId, $this->appSecret);

        // It is needed to always refresh token, because original token expires after 1 hour
        try {
            $token = new AccessToken($this->authData);
            return $provider->getAccessToken('refresh_token', ['refresh_token' => $token->getRefreshToken()]);
        } catch (IdentityProviderException $e) {
            throw new AccessTokenRefreshException(
                'Microsoft OAuth API token refresh failed, ' .
                'please reset authorization in the extractor configuration.',
                0,
                $e
            );
        }
    }

    private function createOAuthProvider(string $appId, string $appSecret): GenericProvider
    {
        return new GenericProvider([
            'clientId' => $appId,
            'clientSecret' => $appSecret,
            'redirectUri' => '',
            'urlAuthorize' => self::AUTHORITY_URL . self::AUTHORIZE_ENDPOINT,
            'urlAccessToken' => self::AUTHORITY_URL . self::TOKEN_ENDPOINT,
            'urlResourceOwnerDetails' => '',
            'scopes' => implode(' ', self::SCOPES),
        ]);
    }
}
