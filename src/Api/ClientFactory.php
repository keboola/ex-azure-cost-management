<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Api;

use GuzzleHttp\Client;
use Keboola\AzureCostExtractor\OAuth\TokenProvider;

class ClientFactory
{
    private TokenProvider $tokenProvider;

    private string $subscriptionId;

    public function __construct(TokenProvider $tokenProvider, string $subscriptionId)
    {
        $this->tokenProvider = $tokenProvider;
        $this->subscriptionId = $subscriptionId;
    }

    public function create(): Client
    {
        $accessToken = $this->tokenProvider->get()->getToken();
        $scope = 'subscriptions/' . urlencode($this->subscriptionId);
        return new Client([
            'base_uri' => "https://management.azure.com/$scope/providers/Microsoft.CostManagement/",
            'headers' => [
                'Authorization' => "Bearer $accessToken",
            ],
        ]);
    }
}
