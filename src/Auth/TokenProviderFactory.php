<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Auth;

use ArrayObject;
use Keboola\AzureCostExtractor\Config;
use Psr\Log\LoggerInterface;

class TokenProviderFactory
{
    private Config $config;

    private LoggerInterface $logger;

    private ArrayObject $stateObject;

    public function __construct(Config $config, LoggerInterface $logger, ArrayObject $stateObject)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->stateObject = $stateObject;
    }

    public function create(): TokenProvider
    {
        // Service principal login
        if ($this->config->hasServicePrincipal()) {
            $this->logger->info('Using Service Principal authentication.');
            return new ServicePrincipalTokenProvider(
                $this->config->getServicePrincipalTenant(),
                $this->config->getServicePrincipalUsername(),
                $this->config->getServicePrincipalPassword()
            );
        }

        // OAuth Refresh Token login
        $this->logger->info('Using OAuth Refresh Token authentication.');
        $tokenDataManager = new TokenDataManager($this->config->getOAuthApiData(), $this->stateObject);
        $authorityUrl = $this->config->getOAuthAuthorityUrl();
        return new RefreshTokenProvider(
            $this->config->getOAuthApiAppKey(),
            $this->config->getOAuthApiAppSecret(),
            $tokenDataManager,
            $authorityUrl
        );
    }
}