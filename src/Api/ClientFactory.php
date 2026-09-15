<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Api;

use GuzzleHttp\Client;
use Keboola\AzureCostExtractor\Auth\TokenProvider;

class ClientFactory
{
    /**
     * Prefix of the ClientType request header. Azure Cost Management applies a rate limit quota
     * per ClientType, and a request that sends no ClientType shares one quota with every other
     * caller that also sends none - which is what throttled this component in production. The
     * subscription id is appended, so one subscription cannot spend another subscription's quota.
     * @see https://learn.microsoft.com/en-us/answers/questions/1340993/exception-429-too-many-requests-for-azure-cost-man
     */
    private const CLIENT_TYPE_PREFIX = 'keboola.ex-azure-cost-management';

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
                'ClientType' => self::CLIENT_TYPE_PREFIX . '/' . $this->subscriptionId,
            ],
        ]);
    }
}
