<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Api;

use Retry\BackOff\BackOffContextInterface;
use Retry\BackOff\ExponentialBackOffPolicy;
use Retry\RetryContextInterface;

class RateLimitBackOffPolicy extends ExponentialBackOffPolicy
{
    private ?int $retryAfterSeconds = null;

    public function __construct()
    {
        parent::__construct(5000, 2.0, 120000);
    }

    public function setRetryAfterSeconds(?int $seconds): void
    {
        $this->retryAfterSeconds = $seconds;
    }

    public function start(?RetryContextInterface $context = null): BackOffContextInterface
    {
        $this->retryAfterSeconds = null;
        return parent::start($context);
    }

    public function backOff(?BackOffContextInterface $context = null): void
    {
        if ($this->retryAfterSeconds !== null && $this->retryAfterSeconds > 0) {
            $sleepMs = $this->retryAfterSeconds * 1000;
            $sleepMs = min($sleepMs, $this->getMaxInterval());
            $this->getSleeper()->sleep($sleepMs);
            $this->retryAfterSeconds = null;
        } else {
            parent::backOff($context);
        }
    }

    private function getSleeper(): \Retry\BackOff\SleeperInterface
    {
        $reflection = new \ReflectionClass(ExponentialBackOffPolicy::class);
        $property = $reflection->getProperty('sleeper');
        $property->setAccessible(true);
        return $property->getValue($this);
    }
}
