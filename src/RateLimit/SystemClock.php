<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\RateLimit;

/**
 * Default {@see Clock} implementation backed by the real system clock.
 */
final class SystemClock implements Clock
{
    /**
     * Longest single sleep in seconds (about 31 years), so the conversion to
     * microseconds can never overflow the integer range.
     */
    private const float MAX_SECONDS = 1.0E9;

    public function now(): float
    {
        return microtime(true);
    }

    public function sleep(float $seconds): void
    {
        // Also rejects NAN, for which every comparison is false.
        if (!($seconds > 0.0)) {
            return;
        }

        usleep((int) ceil(min($seconds, self::MAX_SECONDS) * 1_000_000));
    }
}
