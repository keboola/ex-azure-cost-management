<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Auth;

use Keboola\AzureCostExtractor\Config;
use Keboola\AzureCostExtractor\Exception\AccessTokenInitException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;

class ServicePrincipalTokenProvider implements TokenProvider
{
    private const TOKEN_ENDPOINT = '/oauth2/v2.0/token';
    private const SCOPE = 'https://management.core.windows.net/.default';

    private string $tenantId;
    private string $username;
    private string $password;

    public function __construct(string $tenantId, string $username, string $password)
    {
        $this->tenantId = $tenantId;
        $this->username = $username;
        $this->password = $password;
    }

    public function get(): AccessTokenInterface
    {
        // OAuth 2.0 client credentials flow
        $provider = $this->createOAuthProvider();

        // It is needed to always refresh token, because original token expires after 1 hour
        try {
            $newToken = $provider->getAccessToken('client_credentials', ['scope' => self::SCOPE]);
        } catch (IdentityProviderException $e) {
            throw new AccessTokenInitException(
                'Service Principal OAuth login failed: ' . $e->getMessage(),
                0,
                $e
            );
        }

        return $newToken;
    }

    private function createOAuthProvider(): GenericProvider
    {
        return new GenericProvider([
            'clientId' => $this->username,
            'clientSecret' => $this->password,
            'urlAuthorize' => '',
            'urlAccessToken' => sprintf('%s/%s', Config::OAUTH_BASE_URL, $this->tenantId) . self::TOKEN_ENDPOINT,
            'urlResourceOwnerDetails' => '',
        ]);
    }
}