<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\RateLimit;

use GoSuccess\UptimeRobot\Http\Response;

/**
 * The request quota UptimeRobot reported with a response, read from its
 * `x-ratelimit-limit`, `x-ratelimit-remaining` and `x-ratelimit-reset` headers.
 */
final readonly class RateLimitStatus
{
    /**
     * An `x-ratelimit-reset` above this is a Unix timestamp (2001-09-09 or
     * later); anything else is a number of seconds (up to almost 32 years).
     */
    private const int EPOCH_THRESHOLD = 1_000_000_000;

    /**
     * @param int   $limit     Requests allowed per window.
     * @param int   $remaining Requests left in the current window.
     * @param float $resetAt   When the window resets, as a {@see Clock} time (Unix
     *                         time with the default {@see SystemClock}).
     */
    public function __construct(
        public int $limit,
        public int $remaining,
        public float $resetAt,
    ) {}

    /**
     * Read the rate-limit headers of a response.
     *
     * @param float $receivedAt Clock time at which the response arrived.
     *
     * @return self|null Null unless all three headers are present and valid; the
     *                   API omits them e.g. on a `401` for an invalid key.
     */
    public static function fromResponse(Response $response, float $receivedAt): ?self
    {
        $limit = self::count($response->header('x-ratelimit-limit'));
        $remaining = self::count($response->header('x-ratelimit-remaining'));
        $resetAt = self::parseReset($response->header('x-ratelimit-reset'), $receivedAt);

        if ($limit === null || $remaining === null || $resetAt === null) {
            return null;
        }

        return new self($limit, $remaining, $resetAt);
    }

    /**
     * Interpret an `x-ratelimit-reset` value as a clock time.
     *
     * UptimeRobot documents the header as the Unix time "at which the rate
     * limiting period will end", but the API sends `60` with every response
     * (verified live: the value stayed 60 while `x-ratelimit-remaining` counted
     * down), which can only be seconds until the reset or the length of the
     * window. Both readings are supported: a value above one billion is a Unix
     * timestamp, anything else a number of seconds from the moment the response
     * was received. Should `60` be the window length, waiting that long is the
     * safe upper bound for a rolling window, whereas reading it as a timestamp
     * (as UptimeRobot's Terraform provider does) would not wait at all.
     *
     * @internal
     *
     * @param float $receivedAt Clock time at which the response arrived.
     */
    public static function parseReset(string $value, float $receivedAt): ?float
    {
        $value = trim($value);

        if (!is_numeric($value)) {
            return null;
        }

        $reset = (float) $value;

        if (!is_finite($reset) || $reset < 0.0) {
            return null;
        }

        return $reset > self::EPOCH_THRESHOLD ? $reset : $receivedAt + $reset;
    }

    /**
     * Whether no requests remain in the current window.
     */
    public function isExhausted(): bool
    {
        return $this->remaining <= 0;
    }

    /**
     * Seconds until the window resets, never negative.
     */
    public function secondsUntilReset(float $now): float
    {
        return max(0.0, $this->resetAt - $now);
    }

    private static function count(string $value): ?int
    {
        $count = filter_var(trim($value), \FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        return \is_int($count) ? $count : null;
    }
}
