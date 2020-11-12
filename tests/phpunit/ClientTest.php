<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Tests;

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
}
