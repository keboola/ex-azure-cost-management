<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Tests;

use Keboola\AzureCostExtractor\ClientFactory;
use GuzzleHttp\Client;
use PHPUnit\Framework\Assert;

class ClientTest extends BaseTest
{
    public function testClient(): void
    {
        # https://docs.microsoft.com/en-us/rest/api/cost-management/dimensions/list
        $client = $this->createClient();
        $response = $client->get('dimensions?api-version=2019-11-01&$top=1');

        Assert::assertSame(200, $response->getStatusCode());
        Assert::assertGreaterThan(1, $response->getBody()->getSize());
    }

    private function createClient(): Client
    {
        $subscriptionId = (string) getenv('TEST_SUBSCRIPTION_ID');
        $factory = new ClientFactory($this->createTokenFactory(), $subscriptionId);
        return $factory->create();
    }
}
