<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Csv;

use Keboola\AzureCostExtractor\Config;
use Keboola\AzureCostExtractor\Exception\UnexpectedColumnException;
use Keboola\Datatype\Definition\BaseType;

class ColumnsParser
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function parse(array $response): array
    {
        // Map columns to objects
        $columns = [];
        foreach ($response['properties']['columns'] as $index => $data) {
            $name = $data['name'];
            $category = $this->parseCategory($name);
            $rawType = $data['type'];
            $type = $this->parseType($rawType);
            $columns[] = new Column($index, $name, $type, $category);
        }

        // Sort columns by category
        self::sortColumns($columns);

        return $columns;
    }

    private function parseType(string $rawType): string
    {
        switch ($rawType) {
            case 'Number':
                return BaseType::NUMERIC;
            default:
                return BaseType::STRING;
        }
    }

    private function parseCategory(string $name): string
    {
        if ($name === 'Currency') {
            return Column::CATEGORY_CURRENCY;
        }

        if ($name === $this->config->getAggregation()) {
            return Column::CATEGORY_AGGREGATION;
        }

        if (in_array($name, $this->config->getGroupingDimensions(), true)) {
            return Column::CATEGORY_GROUPING_DIMENSION;
        }

        if (strpos($name, 'Date') !== false) {
            return Column::CATEGORY_TIME_DIMENSION;
        }

        throw new UnexpectedColumnException(sprintf('Found unexpected column "%s" in the API response.', $name));
    }

    private static function sortColumns(array &$columns): void
    {
        usort($columns, function (Column $a, Column $b) {
            return self::getColumnOrder($a) <=> self::getColumnOrder($b);
        });
    }

    private static function getColumnOrder(Column $column): int
    {
        switch ($column->getCategory()) {
            case Column::CATEGORY_TIME_DIMENSION:
                return 1;
            case Column::CATEGORY_GROUPING_DIMENSION:
                return 2;
            case Column::CATEGORY_AGGREGATION:
                return 3;
            default:
                return 10;
        }
    }
}
