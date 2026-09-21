<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\RateLimit;

use GoSuccess\UptimeRobot\Http\Response;
use GoSuccess\UptimeRobot\RateLimit\RateLimitStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RateLimitStatus::class)]
final class RateLimitStatusTest extends TestCase
{
    public function testReadsTheHeadersTheApiSends(): void
    {
        // As observed live on a successful response.
        $response = new Response(200, '{}', ['x-ratelimit-limit' => '20', 'x-ratelimit-remaining' => '19', 'x-ratelimit-reset' => '60']);

        $status = RateLimitStatus::fromResponse($response, 1_800_000_000.5);

        self::assertNotNull($status);
        self::assertSame(20, $status->limit);
        self::assertSame(19, $status->remaining);
        self::assertSame(1_800_000_060.5, $status->resetAt);
        self::assertFalse($status->isExhausted());
        self::assertSame(45.0, $status->secondsUntilReset(1_800_000_015.5));
        self::assertSame(0.0, $status->secondsUntilReset(1_800_000_100.0));
    }

    public function testReportsAnExhaustedQuota(): void
    {
        self::assertTrue(new RateLimitStatus(20, 0, 60.0)->isExhausted());
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('incompleteHeaders')]
    public function testIgnoresIncompleteOrInvalidHeaders(array $headers): void
    {
        self::assertNull(RateLimitStatus::fromResponse(new Response(200, '', $headers), 0.0));
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function incompleteHeaders(): iterable
    {
        yield 'none, as on a 401' => [[]];
        yield 'no limit' => [['x-ratelimit-remaining' => '1', 'x-ratelimit-reset' => '60']];
        yield 'no remaining' => [['x-ratelimit-limit' => '20', 'x-ratelimit-reset' => '60']];
        yield 'no reset' => [['x-ratelimit-limit' => '20', 'x-ratelimit-remaining' => '1']];
        yield 'negative remaining' => [['x-ratelimit-limit' => '20', 'x-ratelimit-remaining' => '-1', 'x-ratelimit-reset' => '60']];
        yield 'fractional limit' => [['x-ratelimit-limit' => '2.5', 'x-ratelimit-remaining' => '1', 'x-ratelimit-reset' => '60']];
        yield 'limit beyond the int range' => [['x-ratelimit-limit' => '99999999999999999999', 'x-ratelimit-remaining' => '1', 'x-ratelimit-reset' => '60']];
        yield 'reset as a date' => [['x-ratelimit-limit' => '20', 'x-ratelimit-remaining' => '1', 'x-ratelimit-reset' => 'Wed, 21 Oct 2026 07:28:00 GMT']];
    }

    #[DataProvider('resets')]
    public function testReadsTheResetAsSecondsOrTimestamp(string $value, ?float $expected): void
    {
        self::assertSame($expected, RateLimitStatus::parseReset($value, 1_800_000_000.0));
    }

    /**
     * @return iterable<string, array{string, float|null}>
     */
    public static function resets(): iterable
    {
        yield 'seconds, as the API sends it' => ['60', 1_800_000_060.0];
        yield 'fractional seconds' => [' 1.5 ', 1_800_000_001.5];
        yield 'zero' => ['0', 1_800_000_000.0];
        yield 'timestamp, as documented' => ['1800000042', 1_800_000_042.0];
        yield 'timestamp in the past' => ['1700000000', 1_700_000_000.0];
        yield 'negative' => ['-5', null];
        yield 'infinite' => ['1e999', null];
        yield 'empty' => ['', null];
        yield 'text' => ['soon', null];
    }
}
