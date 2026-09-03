<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Tests;

use Keboola\AzureCostExtractor\Api\Api;

/**
 * An Api that records the rate limit waits it asks for, instead of really sleeping.
 * It lets the tests assert the wait the extractor computed, and keeps them fast.
 */
class RecordingApi extends Api
{
    /** @var int[] */
    private array $waits = [];

    /**
     * @return int[]
     */
    public function getWaits(): array
    {
        return $this->waits;
    }

    protected function waitForRateLimit(int $seconds): void
    {
        $this->waits[] = $seconds;
    }
}
