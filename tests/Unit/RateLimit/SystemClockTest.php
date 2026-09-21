<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\RateLimit;

use GoSuccess\UptimeRobot\RateLimit\NullRateLimiter;
use GoSuccess\UptimeRobot\RateLimit\SystemClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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

    /**
     * A wait of 4295 seconds exceeds 2^32 microseconds, which usleep() wrapped
     * around to 33 milliseconds. The sleep must also survive a signal: here the
     * first one is ignored and the second one ends the test.
     */
    #[RequiresPhpExtension('pcntl')]
    #[RequiresPhpExtension('posix')]
    public function testSleepsLongWaitsThroughSignals(): void
    {
        $signals = 0;
        $handler = static function (int $signal) use (&$signals): void {
            if ($signal === SIGALRM) {
                throw new RuntimeException('The signals did not arrive.');
            }

            if (++$signals === 2) {
                throw new RuntimeException('Woken up.');
            }
        };

        $async = pcntl_async_signals(true);
        $previous = [SIGUSR1 => pcntl_signal_get_handler(SIGUSR1), SIGALRM => pcntl_signal_get_handler(SIGALRM)];
        pcntl_signal(SIGUSR1, $handler);
        pcntl_signal(SIGALRM, $handler);
        // A watchdog, in case the signals never arrive.
        pcntl_alarm(10);

        $sender = proc_open(
            [\PHP_BINARY, '-r', 'for ($i = 0; $i < 2; ++$i) { usleep(150000); posix_kill(' . getmypid() . ', SIGUSR1); }'],
            [],
            $pipes,
        );
        self::assertIsResource($sender);
        $start = microtime(true);

        $woken = null;

        try {
            new SystemClock()->sleep(4295.0);
        } catch (RuntimeException $e) {
            $woken = $e->getMessage();
        } finally {
            pcntl_alarm(0);
            // Ignore the signals until the sender is stopped; the default
            // action of SIGUSR1 would end the test run.
            pcntl_signal(SIGUSR1, SIG_IGN);
            pcntl_signal(SIGALRM, SIG_IGN);
            proc_terminate($sender);
            proc_close($sender);

            foreach ($previous as $signal => $callback) {
                pcntl_signal($signal, $callback);
            }

            pcntl_async_signals($async);
        }

        self::assertSame('Woken up.', $woken, 'The sleep ended before the second signal.');
        self::assertSame(2, $signals);
        self::assertGreaterThanOrEqual(0.25, microtime(true) - $start);
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
