<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\RateLimit;

use GoSuccess\UptimeRobot\RateLimit\NullRateLimiter;
use GoSuccess\UptimeRobot\RateLimit\SystemClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SystemClock::class)]
#[CoversClass(NullRateLimiter::class)]
final class SystemClockTest extends TestCase
{
    public function testReportsUnixTime(): void
    {
        self::assertEqualsWithDelta(microtime(true), new SystemClock()->now(), 1.0);
    }

    public function testSleeps(): void
    {
        $clock = new SystemClock();
        $start = microtime(true);

        $clock->sleep(0.02);

        self::assertGreaterThanOrEqual(0.02, microtime(true) - $start);
    }

    public function testIgnoresWaitsThatAreNotPositive(): void
    {
        $clock = new SystemClock();
        $start = microtime(true);

        $clock->sleep(0.0);
        $clock->sleep(-5.0);
        $clock->sleep(\NAN);

        self::assertLessThan(0.5, microtime(true) - $start);
    }

    public function testNullRateLimiterNeverBlocks(): void
    {
        $limiter = new NullRateLimiter();
        $start = microtime(true);

        for ($i = 0; $i < 1000; ++$i) {
            $limiter->acquire();
        }

        self::assertLessThan(0.5, microtime(true) - $start);
    }
}
