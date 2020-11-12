<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor;

use ArrayObject;
use Keboola\AzureCostExtractor\OAuth\TokenDataManager;
use Keboola\AzureCostExtractor\OAuth\TokenProvider;
use Keboola\Component\BaseComponent;
use Psr\Log\LoggerInterface;

class Component extends BaseComponent
{
    private Extractor $extractor;

    private ArrayObject $stateObject;

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
        $config = $this->getConfig();
        $this->stateObject = new ArrayObject($this->getInputState());
        $tokenDataManager = new TokenDataManager($config->getOAuthApiData(), $this->stateObject);
        $tokenProvider = new TokenProvider(
            $config->getOAuthApiAppKey(),
            $config->getOAuthApiAppSecret(),
            $tokenDataManager
        );
        $clientFactory = new ClientFactory($tokenProvider, $config->getSubscriptionId());
        $this->extractor = new Extractor($this->getLogger(), $clientFactory->create());
    }

    public function execute(): void
    {
        try {
            parent::execute();
        } finally {
            $this->writeOutputStateToFile($this->stateObject->getArrayCopy());
        }
    }

    protected function run(): void
    {
    }

    public function getConfig(): Config
    {
        /** @var Config $config */
        $config = parent::getConfig();
        return $config;
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
