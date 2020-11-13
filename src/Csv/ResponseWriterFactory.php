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
        $tableName = sanitize($this->config->getConfigRowName());
        $csvPath = $this->dataDir . '/out/tables/' . $tableName . '.csv';
        $columnsParser = new ColumnsParser($this->config);
        return new ResponseWriter($csvPath, $this->manifestManager, $columnsParser);
    }
}
