<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Http;

use GoSuccess\UptimeRobot\ClientOptions;
use GoSuccess\UptimeRobot\Exception\ApiException;
use GoSuccess\UptimeRobot\Exception\RateLimitException;
use GoSuccess\UptimeRobot\Exception\SerializationException;
use GoSuccess\UptimeRobot\Exception\TransportException;
use GoSuccess\UptimeRobot\RateLimit\Clock;
use GoSuccess\UptimeRobot\RateLimit\RateLimiter;
use GoSuccess\UptimeRobot\RateLimit\RateLimitStatus;
use GoSuccess\UptimeRobot\RateLimit\SystemClock;
use InvalidArgumentException;
use JsonException;
use SensitiveParameter;

/**
 * Mid-level HTTP layer: builds authenticated requests, sends them through the
 * {@see HttpClient}, honors the rate limits, retries transient failures and
 * maps error responses to typed exceptions.
 *
 * @internal
 */
final class Connection
{
    private const int JSON_ENCODE_FLAGS = \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION;

    /**
     * Integers beyond the int range become strings instead of floats, so an ID
     * is never silently rounded.
     */
    private const int JSON_DECODE_FLAGS = \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING;

    /**
     * Base URI without a trailing slash, e.g. `https://api.uptimerobot.com/v3`.
     */
    public readonly string $baseUri;

    /**
     * The quota reported by the most recent response that carried the
     * `x-ratelimit-*` headers, or null before the first one.
     */
    public private(set) ?RateLimitStatus $rateLimit = null;

    /**
     * Clock time until which the next request is held back, because the last
     * response reported that no requests remain in the current window.
     */
    private ?float $resumeAt = null;

    /**
     * @param string $apiKey Sent as `Authorization: Bearer <key>`.
     */
    public function __construct(
        string $baseUri,
        #[SensitiveParameter]
        private readonly string $apiKey,
        private readonly ClientOptions $options,
        private readonly HttpClient $httpClient,
        private readonly RateLimiter $rateLimiter,
        private readonly Clock $clock = new SystemClock(),
    ) {
        $baseUri = rtrim($baseUri, '/');

        if (!preg_match('~^https?://[^/?#]+~i', $baseUri)) {
            throw new InvalidArgumentException("The base URI must be an absolute http(s) URI, got \"{$baseUri}\".");
        }

        if (trim($apiKey) === '') {
            throw new InvalidArgumentException('The API key must not be empty.');
        }

        // A line break would inject headers; a key read from a file often ends with one.
        if (preg_match('/[\x00-\x1F\x7F]/', $apiKey) === 1) {
            throw new InvalidArgumentException('The API key must not contain line breaks or other control characters.');
        }

        $this->baseUri = $baseUri;
    }

    /**
     * Send a request with an optional JSON object body and decode the JSON response.
     *
     * @param array<string, mixed>         $query
     * @param array<array-key, mixed>|null $body  Encoded as a JSON object; an empty
     *                                            array becomes `{}`.
     *
     * @return mixed The decoded response, or null for an empty body.
     */
    public function json(Method $method, string $path, array $query = [], ?array $body = null): mixed
    {
        $headers = ['Accept' => 'application/json'];
        $payload = null;

        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
            $payload = self::encodeJson($body);
        }

        return self::decodeJson($this->send($method, $path, $query, $payload, $headers)->body);
    }

    /**
     * Send a `multipart/form-data` request, e.g. with a file upload, and decode
     * the JSON response.
     *
     * @param array<string, mixed>      $fields Form fields; see {@see Multipart} for
     *                                          how values are encoded.
     * @param array<string, FileUpload> $files  Files keyed by field name.
     * @param array<string, mixed>      $query
     *
     * @return mixed The decoded response, or null for an empty body.
     */
    public function multipart(Method $method, string $path, array $fields = [], array $files = [], array $query = []): mixed
    {
        $form = Multipart::encode($fields, $files);

        return self::decodeJson($this->send($method, $path, $query, $form->body, ['Content-Type' => $form->contentType])->body);
    }

    /**
     * Send a request and return the raw response.
     *
     * @param array<string, mixed>  $query
     * @param array<string, string> $headers Extra headers, overriding the defaults.
     *
     * @throws ApiException       On an error response once all retries are used up.
     * @throws TransportException On a network failure once all retries are used up.
     */
    public function send(
        Method $method,
        string $path,
        array $query = [],
        ?string $body = null,
        array $headers = [],
    ): Response {
        $request = new Request(
            method: $method,
            uri: $this->buildUri($path, $query),
            headers: [
                'Authorization' => "Bearer {$this->apiKey}",
                'Accept' => 'application/json',
                'User-Agent' => $this->options->userAgent,
                ...$headers,
            ],
            body: $body,
            timeout: $this->options->timeout,
        );

        $attempt = 0;

        while (true) {
            // Wait for an exhausted window first, so the limiter records the actual send time.
            $this->awaitRateLimitReset();
            $this->rateLimiter->acquire();

            try {
                $response = $this->httpClient->send($request);
            } catch (TransportException $e) {
                // The request may have reached the server, so only repeat it if
                // doing so cannot duplicate a write.
                if ($method->isIdempotent() && $attempt < $this->options->maxRetries) {
                    $this->clock->sleep($this->backoffDelay($attempt++));

                    continue;
                }

                throw $e;
            }

            $receivedAt = $this->clock->now();
            $this->observeRateLimit($response, $receivedAt);

            if ($response->isSuccessful) {
                return $response;
            }

            if ($this->isRetryable($response->statusCode, $method) && $attempt < $this->options->maxRetries) {
                $delay = $this->retryDelay($response, $receivedAt, $attempt++);

                if ($response->statusCode === 429) {
                    // The delay already follows what the API announced for this
                    // rejection; waiting for the window reset on top would only
                    // add to it.
                    $this->resumeAt = null;
                }

                $this->clock->sleep($delay);

                continue;
            }

            throw ApiException::fromResponse($response, $request, $receivedAt);
        }
    }

    /**
     * @param array<array-key, mixed> $body
     */
    public static function encodeJson(array $body): string
    {
        if ($body === []) {
            return '{}';
        }

        try {
            return json_encode($body, self::JSON_ENCODE_FLAGS);
        } catch (JsonException $e) {
            throw new SerializationException("Failed to encode the request body as JSON: {$e->getMessage()}", 0, $e);
        }
    }

    public static function decodeJson(string $body): mixed
    {
        if (trim($body) === '') {
            return null;
        }

        try {
            return json_decode($body, true, 512, self::JSON_DECODE_FLAGS);
        } catch (JsonException $e) {
            throw new SerializationException("Failed to decode the JSON response: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Hide the API key from var_dump() and print_r().
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['baseUri' => $this->baseUri, 'apiKey' => '********', 'rateLimit' => $this->rateLimit];
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return non-empty-string
     */
    private function buildUri(string $path, array $query): string
    {
        $uri = "{$this->baseUri}/" . ltrim($path, '/');
        $queryString = Query::build($query);

        return $queryString === '' ? $uri : "{$uri}?{$queryString}";
    }

    /**
     * Remember the quota a response reported. A response without the headers
     * (e.g. a `401`) keeps the last known status.
     */
    private function observeRateLimit(Response $response, float $receivedAt): void
    {
        $status = RateLimitStatus::fromResponse($response, $receivedAt);

        if ($status === null) {
            return;
        }

        $this->rateLimit = $status;
        $this->resumeAt = $this->options->awaitRateLimitReset && $status->isExhausted() ? $status->resetAt : null;
    }

    /**
     * Hold the request back until the window resets, if the last response used
     * up the quota: the request would only be rejected with a `429` otherwise.
     * UptimeRobot's Terraform provider does the same.
     */
    private function awaitRateLimitReset(): void
    {
        if ($this->resumeAt === null) {
            return;
        }

        $wait = min($this->resumeAt - $this->clock->now(), $this->options->maxRetryDelay);
        $this->resumeAt = null;

        if ($wait > 0.0) {
            $this->clock->sleep($wait);
        }
    }

    /**
     * A `429` is always safe to retry (the request was rejected, not processed).
     * A `5xx` may have been processed, so it is only retried for idempotent methods.
     */
    private function isRetryable(int $status, Method $method): bool
    {
        return $status === 429 || ($status >= 500 && $method->isIdempotent());
    }

    /**
     * For a `429`, the wait the API announced (`Retry-After`, else
     * `x-ratelimit-reset`), otherwise the exponential backoff; always capped
     * by {@see ClientOptions::$maxRetryDelay}.
     */
    private function retryDelay(Response $response, float $receivedAt, int $attempt): float
    {
        if ($response->statusCode === 429) {
            $announced = RateLimitException::announcedDelay($response, $receivedAt);

            if ($announced !== null) {
                return min($announced, $this->options->maxRetryDelay);
            }
        }

        return $this->backoffDelay($attempt);
    }

    private function backoffDelay(int $attempt): float
    {
        return min($this->options->retryBaseDelay * (2 ** $attempt), $this->options->maxRetryDelay);
    }
}
