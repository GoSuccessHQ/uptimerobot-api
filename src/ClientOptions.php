<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot;

use InvalidArgumentException;

/**
 * Immutable transport, retry and rate-limit settings.
 *
 * Credentials are not part of the options: the client receives the API key
 * separately, so options can be shared and logged safely.
 */
final readonly class ClientOptions
{
    public const string DEFAULT_USER_AGENT = 'gosuccess/uptimerobot-api (+https://github.com/GoSuccessHQ/uptimerobot-api)';

    /**
     * @param float  $timeout             Maximum duration of a request in seconds; `0.0` means
     *                                    no limit, though a request that stalls for 60 seconds
     *                                    is still aborted.
     * @param float  $connectTimeout      Maximum duration of the connection phase in seconds.
     * @param int    $maxRetries          How often a failed request is retried. A `429` is
     *                                    always retried; server errors and network failures only
     *                                    for idempotent requests (GET, PUT, DELETE). A DELETE
     *                                    retried after such an error that then answers `404`
     *                                    counts as successful: the earlier attempt may have
     *                                    deleted the resource, and it is gone either way.
     * @param float  $retryBaseDelay      Base delay in seconds of the exponential backoff.
     * @param float  $maxRetryDelay       Upper bound in seconds for a single wait. It also caps a
     *                                    server-provided `Retry-After` or rate-limit reset.
     * @param bool   $awaitRateLimitReset Hold the next request back until the rate-limit window
     *                                    resets once a response reports that no requests remain
     *                                    (`x-ratelimit-remaining: 0`), instead of running into a
     *                                    `429`. The wait is capped by `$maxRetryDelay`.
     * @param string $userAgent           Value of the `User-Agent` header.
     */
    public function __construct(
        public float $timeout = 30.0,
        public float $connectTimeout = 10.0,
        public int $maxRetries = 3,
        public float $retryBaseDelay = 1.0,
        public float $maxRetryDelay = 60.0,
        public bool $awaitRateLimitReset = true,
        public string $userAgent = self::DEFAULT_USER_AGENT,
    ) {
        foreach (['timeout' => $timeout, 'connectTimeout' => $connectTimeout, 'retryBaseDelay' => $retryBaseDelay, 'maxRetryDelay' => $maxRetryDelay] as $name => $value) {
            // NAN and INF would turn into warnings or endless waits once converted for cURL or sleep().
            if (!is_finite($value) || $value < 0.0) {
                throw new InvalidArgumentException("{$name} must be a finite number that is not negative.");
            }
        }

        if ($maxRetries < 0) {
            throw new InvalidArgumentException('maxRetries must not be negative.');
        }

        if (trim($userAgent) === '') {
            throw new InvalidArgumentException('userAgent must not be empty.');
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $userAgent) === 1) {
            throw new InvalidArgumentException('userAgent must not contain control characters.');
        }
    }
}
