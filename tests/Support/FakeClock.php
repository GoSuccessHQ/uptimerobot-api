<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Support;

use GoSuccess\UptimeRobot\RateLimit\Clock;

/**
 * Deterministic clock for tests: never really sleeps, but records every requested
 * sleep and advances its virtual time accordingly.
 */
final class FakeClock implements Clock
{
    /** @var list<float> */
    public array $sleeps = [];

    public function __construct(private float $time = 0.0) {}

    public function now(): float
    {
        return $this->time;
    }

    public function sleep(float $seconds): void
    {
        $this->sleeps[] = $seconds;
        $this->time += $seconds;
    }

    /**
     * Let time pass without a sleep, e.g. the caller's own work between requests.
     */
    public function advance(float $seconds): void
    {
        $this->time += $seconds;
    }

    public function totalSlept(): float
    {
        return array_sum($this->sleeps);
    }
}
