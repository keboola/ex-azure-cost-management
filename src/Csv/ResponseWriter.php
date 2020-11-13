<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Csv;

use Keboola\AzureCostExtractor\Config;
use Keboola\Component\Manifest\ManifestManager\Options\OutTableManifestOptions;
use Keboola\Component\Manifest\ManifestManager;
use Keboola\Csv\CsvWriter;

class ResponseWriter
{
    private string $csvPath;

    private CsvWriter $csvWriter;

    private Config $config;

    private ManifestManager $manifestManager;

    private ColumnsParser $columnsParser;

    private bool $initialized = false;

    /** @var Column[] */
    private array $columns;

    private int $count = 0;

    public function __construct(
        string $csvPath,
        Config $config,
        ManifestManager $manifestManager,
        ColumnsParser $columnsParser
    ) {
        $this->csvPath = $csvPath;
        $this->csvWriter = new CsvWriter($csvPath);
        $this->config = $config;
        $this->manifestManager = $manifestManager;
        $this->columnsParser = $columnsParser;
    }

    public function writeResponse(array $response): int
    {
        if (!$this->initialized) {
            $this->initialized = true;
            $this->columns = $this->columnsParser->parse($response);
        }

        return $this->writeRows($response['properties']['rows']);
    }

    public function finish(): void
    {
        // No rows -> no CSV file
        if ($this->count === 0) {
            @unlink($this->csvPath);
            return;
        }

        $this->writeManifest();
    }

    private function writeRows(array $rows): int
    {
        $count = 0;
        foreach ($rows as &$row) {
            $this->csvWriter->writeRow($this->mapRow($row));
            $count++;
            $this->count++;
        }

        return $count;
    }

    private function mapRow(array &$row): array
    {
        return array_map(
            fn(Column $column) => $column->mapValue((string) $row[$column->getIndex()]),
            $this->columns
        );
    }

    private function writeManifest(): void
    {
        $columns = $this->columns;
        $primaryKeys = array_filter($columns, fn(Column $column) => $column->isPrimaryKey());

        $options = new OutTableManifestOptions();
        $options->setIncremental($this->config->isIncrementalLoad());
        $options->setColumns(array_map(fn(Column $column) => $column->getName(), $columns));
        $options->setPrimaryKeyColumns(array_map(fn(Column $column) => $column->getName(), $primaryKeys));
        $this->manifestManager->writeTableManifest(basename($this->csvPath), $options);
    }
}
