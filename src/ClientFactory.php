<?php

declare(strict_types=1);

namespace AzureCostExtractor;

use GuzzleHttp\Client;

class ClientFactory
{
    private AccessTokenFactory $accessTokenFactory;

    public function __construct(AccessTokenFactory $accessTokenFactory)
    {
        $this->accessTokenFactory = $accessTokenFactory;
    }

    public function create(): Client
    {
        $accessToken = $this->accessTokenFactory->create()->getToken();
        $scope = 'subscriptions/{subscriptionId}';
        return new Client([
            'base_uri' => "https://management.azure.com/$scope/providers/Microsoft.CostManagement/",
            'headers' => [
                'Authorization' => "Bearer $accessToken",
            ],
        ]);
    }
}
