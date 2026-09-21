<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Http;

use InvalidArgumentException;

/**
 * Encodes `multipart/form-data` bodies, which UptimeRobot accepts next to JSON
 * where files are uploaded (the logo and icon of a status page).
 *
 * Field values are formatted like query parameters ({@see Query::format()}):
 * booleans become `true`/`false`, enums their value, dates ISO 8601 in UTC.
 * The API documents lists in forms as repeated `name[]` fields and an empty
 * list as a single empty `name[]` field, which is what this encoder sends.
 *
 * - `null` values and `null` list items are omitted: a form has no way to
 *   express JSON `null`, so clearing a field needs a JSON request.
 * - Nested objects and lists of lists are rejected. The API does not document
 *   how it reads them from a form, and a guessed encoding could silently send
 *   something else than intended.
 *
 * @internal
 */
final readonly class Multipart
{
    /**
     * @param string $contentType Value of the `Content-Type` header, including the boundary.
     * @param string $body        The encoded form.
     */
    private function __construct(
        public string $contentType,
        public string $body,
    ) {}

    /**
     * @param array<string, mixed>      $fields   Form fields: scalars, backed enums, dates and
     *                                            lists of them; `null` is left out.
     * @param array<string, FileUpload> $files    Files keyed by field name.
     * @param string|null               $boundary A fixed boundary (for tests); random by default.
     *
     * @throws InvalidArgumentException On a value that cannot be sent as a form field.
     */
    public static function encode(array $fields, array $files = [], ?string $boundary = null): self
    {
        /** @var list<array{headers: string, content: string}> $parts */
        $parts = [];

        foreach ($fields as $name => $value) {
            $name = (string) $name;

            if ($value === null) {
                continue;
            }

            if (!\is_array($value)) {
                $parts[] = self::field($name, Query::format($name, $value));

                continue;
            }

            if (!array_is_list($value)) {
                throw new InvalidArgumentException("The field {$name} is an object, which cannot be sent as form data; send the request as JSON instead.");
            }

            if ($value === []) {
                $parts[] = self::field("{$name}[]", '');

                continue;
            }

            foreach ($value as $item) {
                if ($item === null) {
                    continue;
                }

                if (\is_array($item)) {
                    throw new InvalidArgumentException("The field {$name} contains a nested list or object, which cannot be sent as form data; send the request as JSON instead.");
                }

                $parts[] = self::field("{$name}[]", Query::format($name, $item));
            }
        }

        foreach ($files as $name => $file) {
            $name = (string) $name;

            // The array is typed by PHPDoc only; a wrong value must not become a silent empty part.
            if (!$file instanceof FileUpload) {
                $type = get_debug_type($file);

                throw new InvalidArgumentException("The file {$name} must be a FileUpload, got {$type}.");
            }

            $parts[] = [
                'headers' => 'Content-Disposition: form-data; name="' . self::quote($name) . '"; filename="' . self::quote($file->filename) . "\"\r\n"
                    . "Content-Type: {$file->contentType}\r\n",
                'content' => $file->contents,
            ];
        }

        $boundary = self::boundary($parts, $boundary);
        $body = '';

        foreach ($parts as $part) {
            $body .= "--{$boundary}\r\n{$part['headers']}\r\n{$part['content']}\r\n";
        }

        return new self("multipart/form-data; boundary={$boundary}", "{$body}--{$boundary}--\r\n");
    }

    /**
     * @return array{headers: string, content: string}
     */
    private static function field(string $name, string $value): array
    {
        return ['headers' => 'Content-Disposition: form-data; name="' . self::quote($name) . "\"\r\n", 'content' => $value];
    }

    /**
     * Escape a name for a quoted header parameter the way browsers do (WHATWG
     * HTML), so a crafted file name cannot break out of the part headers.
     */
    private static function quote(string $value): string
    {
        return str_replace(['"', "\r", "\n"], ['%22', '%0D', '%0A'], $value);
    }

    /**
     * A boundary that occurs in none of the parts, which would end a part early.
     *
     * @param list<array{headers: string, content: string}> $parts
     */
    private static function boundary(array $parts, ?string $boundary): string
    {
        if ($boundary !== null && preg_match('~^[0-9A-Za-z\'()+_,./:=?-]{1,70}$~D', $boundary) !== 1) {
            throw new InvalidArgumentException("Invalid multipart boundary \"{$boundary}\".");
        }

        do {
            $candidate = $boundary ?? 'uptimerobot-' . bin2hex(random_bytes(16));
            $collides = false;

            foreach ($parts as $part) {
                if (str_contains($part['content'], "--{$candidate}")) {
                    $collides = true;

                    break;
                }
            }

            if ($collides && $boundary !== null) {
                throw new InvalidArgumentException("The multipart boundary \"{$boundary}\" occurs in the content.");
            }
        } while ($collides);

        return $candidate;
    }
}
