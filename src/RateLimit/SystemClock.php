<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\RateLimit;

/**
 * Default {@see Clock} implementation backed by the real system clock.
 */
final class SystemClock implements Clock
{
    /**
     * Longest wait in seconds (about 31 years), so that no conversion comes
     * near the limits of the integer range.
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

        $deadline = self::monotonic() + min($seconds, self::MAX_SECONDS);

        // time_nanosleep() takes whole seconds, while usleep() takes the
        // microseconds as a 32-bit unsigned integer and wraps around for waits
        // of about 71 minutes or more. A signal ends a sleep early, so the rest
        // is slept until the deadline has passed.
        while (($remaining = $deadline - self::monotonic()) > 0.0) {
            $whole = floor($remaining);
            $nanoseconds = min(ceil(($remaining - $whole) * 1.0E9), 999_999_999.0);

            if (time_nanosleep((int) $whole, (int) $nanoseconds) === false) {
                return;
            }
        }
    }

    /**
     * Seconds on a monotonic clock, which a change of the system time does not move.
     */
    private static function monotonic(): float
    {
        return hrtime(true) / 1.0E9;
    }
}
