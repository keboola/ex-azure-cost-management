<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor;

use GuzzleHttp\Client;

class ClientFactory
{
    private AccessTokenFactory $accessTokenFactory;

    private string $subscriptionId;

    public function __construct(AccessTokenFactory $accessTokenFactory, string $subscriptionId)
    {
        $this->accessTokenFactory = $accessTokenFactory;
        $this->subscriptionId = $subscriptionId;
    }

    public function create(): Client
    {
        $accessToken = $this->accessTokenFactory->create()->getToken();
        $scope = 'subscriptions/' . urlencode($this->subscriptionId);
        return new Client([
            'base_uri' => "https://management.azure.com/$scope/providers/Microsoft.CostManagement/",
            'headers' => [
                'Authorization' => "Bearer $accessToken",
            ],
        ]);
    }
}
