<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\RateLimit;

use GoSuccess\UptimeRobot\RateLimit\SlidingWindowRateLimiter;
use GoSuccess\UptimeRobot\Tests\Support\FakeClock;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SlidingWindowRateLimiter::class)]
final class SlidingWindowRateLimiterTest extends TestCase
{
    public function testAllowsRequestsUpToTheLimitWithoutWaiting(): void
    {
        $clock = new FakeClock(1000.0);
        $limiter = new SlidingWindowRateLimiter(maxRequests: 3, windowSeconds: 60.0, clock: $clock);

        $limiter->acquire();
        $limiter->acquire();
        $limiter->acquire();

        self::assertSame([], $clock->sleeps);
        self::assertSame(1000.0, $clock->now());
    }

    public function testBlocksUntilTheOldestRequestLeavesTheWindow(): void
    {
        $clock = new FakeClock(1000.0);
        $limiter = new SlidingWindowRateLimiter(maxRequests: 2, windowSeconds: 60.0, clock: $clock);

        $limiter->acquire();
        $clock->advance(10.0);
        $limiter->acquire();
        $limiter->acquire();

        // The third request waits until the first one is 60s old.
        self::assertSame([50.0], $clock->sleeps);
        self::assertSame(1060.0, $clock->now());
    }

    public function testStaysWithinTheLimitOverManyRequests(): void
    {
        $clock = new FakeClock(0.0);
        $limiter = new SlidingWindowRateLimiter(maxRequests: 10, windowSeconds: 60.0, clock: $clock);

        for ($i = 0; $i < 25; ++$i) {
            $limiter->acquire();
        }

        // 25 requests at 10/min (the free plan): the first 10 are free, the
        // remaining 15 force waits that span at least two further windows.
        self::assertGreaterThanOrEqual(120.0, $clock->totalSlept());
    }

    public function testRejectsInvalidLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SlidingWindowRateLimiter(maxRequests: 0);
    }

    public function testRejectsInvalidWindow(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SlidingWindowRateLimiter(maxRequests: 1, windowSeconds: 0.0);
    }

    public function testRejectsAnEndlessWindow(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SlidingWindowRateLimiter(maxRequests: 1, windowSeconds: \INF);
    }
}
