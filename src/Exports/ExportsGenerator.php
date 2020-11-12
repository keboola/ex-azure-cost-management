<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Exports;

use Generator;

class ExportsGenerator
{
    /**
     * @return Generator|Export[]
     */
    public function generateExports(): Generator
    {
        $export = new Export('test', [
            'type' => 'Usage',
            'timeframe' => 'MonthToDate',
            'dataset' => [
                'granularity' => 'Daily',
            ],
        ]);

        yield $export;
    }
}
