<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Exception;

use GoSuccess\UptimeRobot\Http\Response;
use GoSuccess\UptimeRobot\RateLimit\RateLimitStatus;

/**
 * The request was rejected because the rate limit was exceeded (HTTP 429).
 *
 * The client already retries such requests automatically; this exception is
 * only thrown once all retries are used up. {@see $retryAfter} holds the number
 * of seconds to wait, when the API announced it.
 */
final class RateLimitException extends ApiException
{
    /**
     * @param int|null $retryAfter Seconds until the API accepts requests again, from
     *                             `Retry-After` or else `x-ratelimit-reset`.
     */
    public function __construct(
        string $message,
        int $statusCode,
        string $responseBody = '',
        ?string $errorCode = null,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $statusCode, $responseBody, $errorCode);
    }

    /**
     * Parse a `Retry-After` header value into seconds.
     *
     * Supports both the delay-seconds form ("30") and the HTTP-date form
     * ("Wed, 21 Oct 2026 07:28:00 GMT"). Returns null if the value is empty or
     * cannot be interpreted.
     *
     * @param float|null $now Current Unix time for the HTTP-date form; defaults to
     *                        the system time.
     */
    public static function parseRetryAfter(string $value, ?float $now = null): ?int
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            // An absurdly long value saturates at PHP_INT_MAX; callers cap it anyway.
            return (int) $value;
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return null;
        }

        return max(0, (int) ceil($timestamp - ($now ?? microtime(true))));
    }

    /**
     * How long the API asked to wait after a response, in seconds: the
     * `Retry-After` header if present, otherwise the `x-ratelimit-reset` header
     * (see {@see RateLimitStatus::parseReset()}). A reset that lies in the past
     * (e.g. because of clock skew) announces nothing.
     *
     * @internal
     *
     * @param float $now Clock time at which the response arrived.
     */
    public static function announcedDelay(Response $response, float $now): ?float
    {
        $retryAfter = self::parseRetryAfter($response->header('retry-after'), $now);

        if ($retryAfter !== null) {
            return (float) $retryAfter;
        }

        $resetAt = RateLimitStatus::parseReset($response->header('x-ratelimit-reset'), $now);

        return $resetAt !== null && $resetAt > $now ? $resetAt - $now : null;
    }

    /**
     * {@see announcedDelay()} rounded up to whole seconds.
     *
     * @internal
     */
    public static function retryAfter(Response $response, float $now): ?int
    {
        $delay = self::announcedDelay($response, $now);

        // Never above PHP_INT_MAX, whose float value would not convert back.
        return $delay === null ? null : (int) min(ceil($delay), 9.0E18);
    }
}
