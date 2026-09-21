<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Http;

/**
 * HTTP methods used by the UptimeRobot API.
 */
enum Method: string
{
    case Get = 'GET';
    case Post = 'POST';
    case Put = 'PUT';
    case Patch = 'PATCH';
    case Delete = 'DELETE';

    /**
     * Whether repeating the request has the same effect as sending it once.
     *
     * UptimeRobot creates resources and triggers actions (pause, start, reset,
     * bulk operations) with POST and changes them with PATCH. Such requests are
     * never retried automatically after a server error or a lost connection,
     * even where the documentation calls an action idempotent: a duplicate
     * create would be worse than a failed call.
     */
    public function isIdempotent(): bool
    {
        return match ($this) {
            self::Get, self::Put, self::Delete => true,
            self::Post, self::Patch => false,
        };
    }
}
