<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Support;

use GoSuccess\UptimeRobot\RateLimit\RateLimiter;

/**
 * Rate limiter that counts how often a slot was acquired.
 */
final class SpyRateLimiter implements RateLimiter
{
    public int $acquireCount = 0;

    public function acquire(): void
    {
        ++$this->acquireCount;
    }
}
