<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor;

use ArrayObject;
use Keboola\AzureCostExtractor\Auth\TokenProviderFactory;
use Keboola\AzureCostExtractor\Csv\ResponseWriterFactory;
use Psr\Log\LoggerInterface;
use Keboola\AzureCostExtractor\Api\ApiFactory;
use Keboola\AzureCostExtractor\Api\ClientFactory;
use Keboola\AzureCostExtractor\Api\RequestFactory;
use Keboola\Component\BaseComponent;

class Component extends BaseComponent
{
    private Extractor $extractor;

    private ArrayObject $stateObject;

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);

        $logger = $this->getLogger();
        $config = $this->getConfig();

        $this->stateObject = new ArrayObject($this->getInputState());
        $tokenProviderFactory = new TokenProviderFactory($config, $logger, $this->stateObject);
        $tokenProvider = $tokenProviderFactory->create();
        $clientFactory = new ClientFactory($tokenProvider, $config->getSubscriptionId());
        $apiFactory = new ApiFactory($logger, $config, $clientFactory);
        $requestFactory = new RequestFactory($config);
        $responseWriterFactory = new ResponseWriterFactory($this->getManifestManager(), $config, $this->getDataDir());
        $this->extractor = new Extractor(
            $logger,
            $config,
            $apiFactory->create(),
            $responseWriterFactory->create(),
            $requestFactory
        );
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
        $this->extractor->extract();
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
