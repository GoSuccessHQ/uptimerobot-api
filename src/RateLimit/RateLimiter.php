<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\RateLimit;

/**
 * Throttles outgoing requests so the configured request rate is never exceeded.
 *
 * Implementations may block (sleep) inside {@see acquire()} until a slot becomes
 * available. This is intentional: it lets callers fire requests in a loop without
 * having to implement their own back-off logic, while still respecting the limit.
 */
interface RateLimiter
{
    /**
     * Reserve a single request slot, blocking until one is available.
     */
    public function acquire(): void;
}
