<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Tests;

use Keboola\AzureCostExtractor\Api\ClientFactory;
use Keboola\AzureCostExtractor\Auth\TokenProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

class ClientFactoryTest extends TestCase
{
    /**
     * The client identifies itself with a ClientType request header, so Azure Cost Management
     * applies a rate limit quota dedicated to this component instead of the quota shared by every
     * caller that sends no ClientType. The subscription id keeps one subscription's quota separate
     * from another's.
     */
    public function testClientTypeAndAuthorizationHeadersAreSet(): void
    {
        $token = $this->createMock(AccessTokenInterface::class);
        $token->method('getToken')->willReturn('secret-access-token');

        $tokenProvider = $this->createMock(TokenProvider::class);
        $tokenProvider->method('get')->willReturn($token);

        $client = (new ClientFactory($tokenProvider, 'sub-1234'))->create();

        /** @var array<string, string> $headers */
        $headers = $client->getConfig('headers');

        Assert::assertSame('keboola.ex-azure-cost-management/sub-1234', $headers['ClientType']);
        Assert::assertSame('Bearer secret-access-token', $headers['Authorization']);
    }
}
