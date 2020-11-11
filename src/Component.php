<?php

declare(strict_types=1);

namespace AzureCostExtractor;

use Keboola\Component\BaseComponent;
use Psr\Log\LoggerInterface;

class Component extends BaseComponent
{
    private Extractor $extractor;

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
        $config = $this->getConfig();
        $accessTokenFactory = new AccessTokenFactory(
            $config->getOAuthApiAppKey(),
            $config->getOAuthApiAppSecret(),
            $config->getOAuthApiData()
        );
        $clientFactory = new ClientFactory($accessTokenFactory);
        $this->extractor = new Extractor($this->getLogger(), $clientFactory->create());
    }

    protected function run(): void
    {
        // @TODO implement
    }

    protected function getConfigClass(): string
    {
        return Config::class;
    }

    protected function getConfigDefinitionClass(): string
    {
        return ConfigDefinition::class;
    }
}
