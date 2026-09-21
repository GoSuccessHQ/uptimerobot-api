<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\RateLimit;

/**
 * No-op rate limiter that never throttles.
 *
 * The default: the client already honors the limits UptimeRobot announces with
 * every response (see {@see RateLimitStatus}). A limiter is only needed to stay
 * below a lower self-imposed rate, or to coordinate several processes.
 */
final class NullRateLimiter implements RateLimiter
{
    public function acquire(): void
    {
        // Intentionally does nothing.
    }
}
