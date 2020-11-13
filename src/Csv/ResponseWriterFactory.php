<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Csv;

use Keboola\AzureCostExtractor\Config;
use Keboola\Component\Manifest\ManifestManager;
use Keboola\Csv\CsvWriter;

class ResponseWriterFactory
{
    private ManifestManager $manifestManager;

    private Config $config;

    private string $dataDir;

    public function __construct(ManifestManager $manifestManager, Config $config, string $dataDir)
    {
        $this->manifestManager = $manifestManager;
        $this->config = $config;
        $this->dataDir = rtrim($dataDir,'/');
    }

    public function create(): ResponseWriter
    {
        $path = $this->dataDir . '/out/tables/' . $this->config->getConfigRowName() . '.csv';
        $csvWriter = new CsvWriter($path);
        return new ResponseWriter($csvWriter, $this->manifestManager);
    }
}
