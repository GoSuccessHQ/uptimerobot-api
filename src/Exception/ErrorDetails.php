<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Exception;

/**
 * Normalizes the error bodies of the UptimeRobot API, which the specification
 * does not describe at all. Observed live:
 *
 * - API errors: `{"message": "Monitor not found", "code": "000-004"}`
 * - Validation errors and unknown routes (NestJS defaults):
 *   `{"message": ["name must be a string", …], "error": "Bad Request", "statusCode": 400}`
 *   or `{"message": "Cannot GET /v3/x", "error": "Not Found", "statusCode": 404}`
 * - Plain text or HTML, e.g. from the CDN in front of the API
 *
 * A list of messages is joined with "; ". Without a message, `error` is used.
 *
 * @internal
 */
final readonly class ErrorDetails
{
    private const int MAX_MESSAGE_BYTES = 500;

    private function __construct(
        public ?string $message,
        public ?string $errorCode,
    ) {}

    public static function parse(string $body): self
    {
        if (trim($body) === '') {
            return new self(null, null);
        }

        $decoded = json_decode($body, true);

        if (!\is_array($decoded)) {
            // Plain text or an HTML error page.
            return new self(self::excerpt($body), null);
        }

        return new self(
            message: self::message($decoded['message'] ?? null) ?? self::message($decoded['error'] ?? null),
            errorCode: self::code($decoded['code'] ?? null),
        );
    }

    /**
     * A message string, or a list of them as NestJS reports validation errors.
     */
    private static function message(mixed $value): ?string
    {
        if (\is_array($value)) {
            $value = implode('; ', array_filter(
                array_map(static fn(mixed $item): string => \is_string($item) ? trim($item) : '', $value),
                static fn(string $item): bool => $item !== '',
            ));
        }

        return \is_string($value) && trim($value) !== '' ? self::excerpt($value) : null;
    }

    private static function code(mixed $value): ?string
    {
        return match (true) {
            \is_string($value) && trim($value) !== '' => self::excerpt($value),
            \is_int($value) => (string) $value,
            default => null,
        };
    }

    /**
     * Shorten long bodies (e.g. HTML pages) without breaking a UTF-8 sequence.
     */
    private static function excerpt(string $text): string
    {
        $text = trim($text);

        if (\strlen($text) <= self::MAX_MESSAGE_BYTES) {
            return $text;
        }

        $cut = substr($text, 0, self::MAX_MESSAGE_BYTES);

        // Drop a trailing, incomplete multi-byte sequence.
        return (preg_replace('/[\xC0-\xFF][\x80-\xBF]*$/', '', $cut) ?? $cut) . '…';
    }
}
