<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Model;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use stdClass;

/**
 * Formats PHP values for request payloads and query strings.
 *
 * @internal
 */
final class Json
{
    /**
     * Format a date as ISO 8601 in UTC with milliseconds, e.g.
     * `2026-09-18T12:00:00.000Z`: the precision of the dates the API sends,
     * and the one its filters compare with (verified live).
     */
    public static function date(DateTimeInterface $date): string
    {
        return DateTimeImmutable::createFromInterface($date)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s.v\Z');
    }

    /**
     * Format a float without exponent notation and independent of the locale.
     */
    public static function float(float $value): string
    {
        $formatted = rtrim(rtrim(\sprintf('%.14F', $value), '0'), '.');

        return $formatted === '-0' ? '0' : $formatted;
    }

    /**
     * Encode a map so that an empty one is sent as `{}` rather than `[]`.
     *
     * @template T
     *
     * @param array<array-key, T> $map
     *
     * @return array<array-key, T>|stdClass
     */
    public static function map(array $map): array|stdClass
    {
        return $map === [] ? new stdClass() : $map;
    }
}
