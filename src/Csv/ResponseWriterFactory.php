<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Csv;

use Keboola\AzureCostExtractor\Config;
use Keboola\Component\Manifest\ManifestManager;
use function Keboola\Utils\sanitizeColumnName as sanitize;

class ResponseWriterFactory
{
    private ManifestManager $manifestManager;

    private Config $config;

    private string $dataDir;

    public function __construct(ManifestManager $manifestManager, Config $config, string $dataDir)
    {
        $this->manifestManager = $manifestManager;
        $this->config = $config;
        $this->dataDir = rtrim($dataDir, '/');
    }

    public function create(): ResponseWriter
    {
        $csvPath = $this->dataDir . '/out/tables/' . $this->config->getDestination() . '.csv';
        $columnsParser = new ColumnsParser($this->config);
        return new ResponseWriter($csvPath, $this->config, $this->manifestManager, $columnsParser);
    }
}
