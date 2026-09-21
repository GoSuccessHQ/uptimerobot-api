<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools;

/**
 * The status and Last-Modified of the response a download returned, read
 * from http_get_last_response_headers().
 *
 * The http stream wrapper follows redirects and reports the status line and
 * headers of every response in order, e.g. a "301 Moved Permanently" before
 * the "200 OK" whose body it returned. Only the lines after the last status
 * line belong to that body.
 */
final readonly class ResponseHeaders
{
    /**
     * @param int         $status       Status code of the final response; 0 without one.
     * @param string|null $lastModified Its Last-Modified header, if any.
     */
    public function __construct(
        public int $status,
        public ?string $lastModified,
    ) {}

    /**
     * @param list<string> $lines Status lines and headers of every response.
     */
    public static function parse(array $lines): self
    {
        $status = 0;
        $lastModified = null;

        foreach ($lines as $line) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~', $line, $match) === 1) {
                // The next response of a redirect starts.
                $status = (int) $match[1];
                $lastModified = null;
            } elseif (preg_match('/^last-modified:\s*(.+)$/i', $line, $match) === 1) {
                $lastModified = trim($match[1]);
            }
        }

        return new self($status, $lastModified);
    }
}
