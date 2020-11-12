<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Exports;

use GuzzleHttp\Psr7\Request;

class ExportRequest
{
    private Export $export;

    private Request $request;

    public function __construct(Export $export, Request $request)
    {
        $this->export = $export;
        $this->request = $request;
    }

    public function getExport(): Export
    {
        return $this->export;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
