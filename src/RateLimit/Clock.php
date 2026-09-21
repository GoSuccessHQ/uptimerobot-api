<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\RateLimit;

/**
 * Abstraction over the system clock so that retries and rate limiting can be
 * tested deterministically without real wall-clock delays.
 */
interface Clock
{
    /**
     * Current Unix time in seconds as a float, like microtime(true).
     *
     * It must be Unix time rather than an arbitrary monotonic counter, since it
     * is compared with timestamps the API sends (`Retry-After` dates and epoch
     * values of `x-ratelimit-reset`).
     */
    public function now(): float;

    /**
     * Block the current execution for the given number of seconds.
     */
    public function sleep(float $seconds): void;
}
