<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Csv;

class Column
{
    public const CATEGORY_TIME_DIMENSION = 'time_dimension';
    public const CATEGORY_GROUPING_DIMENSION = 'grouping_dimension';
    public const CATEGORY_AGGREGATION = 'aggregation';
    public const CATEGORY_CURRENCY = 'currency';

    private int $index;

    private string $name;

    private string $type;

    private string $category;

    public function __construct(int $index, string $name, string $type, string $category)
    {
        $this->index = $index;
        $this->name = $name;
        $this->type = $type;
        $this->category = $category;
    }
    public function getIndex(): int
    {
        return $this->index;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function mapValue(string $value): string
    {
        // Convert date from 20201031 -> 2020-10-31
        if ($this->category === self::CATEGORY_TIME_DIMENSION) {
            if (preg_match('~^(\d{4})(\d{2})(\d{2})$~', $value, $m)) {
                return "$m[1]-$m[2]-$m[3]";
            }
        }

        return $value;
    }
}
