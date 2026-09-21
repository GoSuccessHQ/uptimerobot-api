<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Http;

use InvalidArgumentException;
use RuntimeException;

/**
 * A file sent with a `multipart/form-data` request, e.g. the logo or icon of a
 * status page.
 *
 * ```php
 * $logo = FileUpload::fromPath('/path/to/logo.png');
 * $logo = new FileUpload('logo.svg', $svgMarkup, 'image/svg+xml');
 * ```
 */
final readonly class FileUpload
{
    /**
     * Content types guessed from the file extension. The content itself is not
     * inspected, since that would require ext-fileinfo.
     */
    private const array CONTENT_TYPES = [
        'avif' => 'image/avif',
        'bmp' => 'image/bmp',
        'gif' => 'image/gif',
        'ico' => 'image/x-icon',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
    ];

    /**
     * @param string $filename    File name reported to the API, e.g. `logo.png`.
     * @param string $contents    The raw bytes of the file.
     * @param string $contentType Media type, e.g. `image/png`.
     */
    public function __construct(
        public string $filename,
        public string $contents,
        public string $contentType = 'application/octet-stream',
    ) {
        if (trim($filename) === '') {
            throw new InvalidArgumentException('The file name must not be empty.');
        }

        // type "/" subtype, optionally followed by parameters; no line breaks
        // that could inject further headers into the form part.
        if (preg_match('~^[\w!#$&^.+-]+/[\w!#$&^.+-]+(?:;[^\x00-\x1F\x7F]*)?$~D', $contentType) !== 1) {
            throw new InvalidArgumentException("Invalid content type \"{$contentType}\".");
        }
    }

    /**
     * Read a local file. The file name defaults to the base name of the path,
     * the content type is guessed from its extension (common image types,
     * otherwise `application/octet-stream`).
     */
    public static function fromPath(string $path, ?string $contentType = null, ?string $filename = null): self
    {
        // file_get_contents() "reads" a directory as an empty string.
        $contents = is_file($path) ? @file_get_contents($path) : false;

        if ($contents === false) {
            throw new RuntimeException("Unable to read the file {$path}.");
        }

        $filename ??= basename($path);

        return new self($filename, $contents, $contentType ?? self::guessContentType($filename));
    }

    /**
     * Keep dumps readable: the size instead of the (possibly binary) contents.
     *
     * @return array<string, int|string>
     */
    public function __debugInfo(): array
    {
        return ['filename' => $this->filename, 'contentType' => $this->contentType, 'size' => \strlen($this->contents)];
    }

    private static function guessContentType(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, \PATHINFO_EXTENSION));

        return self::CONTENT_TYPES[$extension] ?? 'application/octet-stream';
    }
}
