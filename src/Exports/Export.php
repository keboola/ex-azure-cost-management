<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Exports;

class Export
{
    private string $name;

    private array $body;

    public function __construct(string $name, array $body)
    {
        $this->name = $name;
        $this->body = $body;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getBody(): array
    {
        return $this->body;
    }
}
