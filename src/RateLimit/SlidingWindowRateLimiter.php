<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\RateLimit;

use InvalidArgumentException;

/**
 * In-memory sliding-window rate limiter.
 *
 * Keeps the timestamps of the most recent requests within a rolling window
 * (60 seconds by default, like UptimeRobot's own limit). When the window is
 * full, {@see acquire()} blocks until the oldest request leaves the window,
 * guaranteeing that no more than `$maxRequests` requests are sent per window.
 *
 * UptimeRobot allows 10 requests per minute on the free plan and twice the
 * monitor limit (at most 5000) on paid plans.
 *
 * This limiter is process-local. For multi-process or distributed setups, provide
 * a custom {@see RateLimiter} (e.g. backed by Redis).
 */
final class SlidingWindowRateLimiter implements RateLimiter
{
    /**
     * Timestamps (seconds) of the requests currently inside the window.
     *
     * @var list<float>
     */
    private array $timestamps = [];

    /**
     * @param int   $maxRequests   Maximum number of requests allowed per window.
     * @param float $windowSeconds Length of the rolling window in seconds.
     */
    public function __construct(
        private readonly int $maxRequests,
        private readonly float $windowSeconds = 60.0,
        private readonly Clock $clock = new SystemClock(),
    ) {
        if ($maxRequests < 1) {
            throw new InvalidArgumentException('maxRequests must be at least 1.');
        }

        if (!is_finite($windowSeconds) || $windowSeconds <= 0.0) {
            throw new InvalidArgumentException('windowSeconds must be a finite number greater than 0.');
        }
    }

    public function acquire(): void
    {
        $now = $this->clock->now();
        $this->prune($now);

        // Check again after every wait: a sleep may end early (e.g. on a
        // signal), and the request must not be recorded before the window
        // has room for it.
        while (\count($this->timestamps) >= $this->maxRequests) {
            // Positive, since prune() keeps only timestamps whose window ends after $now.
            $this->clock->sleep($this->timestamps[0] + $this->windowSeconds - $now);

            $now = $this->clock->now();
            $this->prune($now);
        }

        $this->timestamps[] = $now;
    }

    /**
     * Drop timestamps that have left the rolling window.
     */
    private function prune(float $now): void
    {
        $expired = 0;

        foreach ($this->timestamps as $timestamp) {
            // The same expression as the wait in acquire(), so that a kept
            // timestamp always means a wait greater than zero.
            if ($timestamp + $this->windowSeconds > $now) {
                break;
            }

            ++$expired;
        }

        if ($expired > 0) {
            array_splice($this->timestamps, 0, $expired);
        }
    }
}
