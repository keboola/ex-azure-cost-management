<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Csv;

use Keboola\Component\Manifest\ManifestManager;
use Keboola\Csv\CsvWriter;

class ResponseWriter
{
    private CsvWriter $csvWriter;

    private ManifestManager $manifestManager;

    private bool $initialized = false;

    public function __construct(CsvWriter $csvWriter, ManifestManager $manifestManager)
    {
        $this->csvWriter = $csvWriter;
        $this->manifestManager = $manifestManager;
    }

    public function writeResponse(array $response): void
    {
        if (!$this->initialized) {
            $this->initialize($response);
        }

        var_export($response);
    }

    public function writeManifest(): void
    {
    }

    /**
     * Parse columns from the first page/response
     */
    private function initialize(array $response): void
    {
        $this->initialized = true;
    }
}
