<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Csv;

class Column
{
    public const CATEGORY_TIME_DIMENSION = 'time_dimension';
    public const CATEGORY_GROUPING_DIMENSION = 'grouping_dimension';
    public const CATEGORY_AGGREGATION = 'aggregation';
    public const CATEGORY_CURRENCY = 'currency';

    public const PRIMARY_KEY_CATEGORIES = [
        self::CATEGORY_TIME_DIMENSION,
        self::CATEGORY_GROUPING_DIMENSION,
    ];

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

    public function isPrimaryKey(): bool
    {
        return in_array($this->category, self::PRIMARY_KEY_CATEGORIES, true);
    }

    public function mapValue(string $value): string
    {

        if ($this->category === self::CATEGORY_TIME_DIMENSION) {
            // Convert date from 20201031 -> 2020-10-31
            if (preg_match('~^(\d{4})(\d{2})(\d{2})$~', $value, $m)) {
                return "$m[1]-$m[2]-$m[3]";
            }

            // Convert month from 2020-01-01T00:00:00 -> 2020-01
            if (strpos($this->name, 'Month') !== false &&
                preg_match('~^(\d{4}-\d{2})-01T00:00:00$~', $value, $m)
            ) {
                return "$m[1]";
            }
        }

        return $value;
    }
}
