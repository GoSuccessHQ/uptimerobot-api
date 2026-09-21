<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Http;

use BackedEnum;
use DateTimeInterface;
use GoSuccess\UptimeRobot\Model\Json;
use InvalidArgumentException;

/**
 * Builds query strings the way the UptimeRobot API expects them.
 *
 * - `null` values are omitted,
 * - booleans become `true`/`false` (not `1`/`0`),
 * - backed enums are sent as their value,
 * - dates are sent as ISO 8601 in UTC, e.g. `2026-09-18T12:00:00Z`,
 * - lists are sent as repeated keys (`customField=a:1&customField=b:2`).
 *
 * @internal
 */
final class Query
{
    /**
     * @param array<string, mixed> $parameters
     */
    public static function build(array $parameters): string
    {
        $pairs = [];

        foreach ($parameters as $name => $value) {
            if ($value === null) {
                continue;
            }

            $name = (string) $name;

            foreach (\is_array($value) ? $value : [$value] as $item) {
                if ($item === null) {
                    continue;
                }

                $pairs[] = rawurlencode($name) . '=' . rawurlencode(self::format($name, $item));
            }
        }

        return implode('&', $pairs);
    }

    /**
     * Format a single value as it is sent in a query string, a path or a form field.
     */
    public static function format(string $name, mixed $value): string
    {
        return match (true) {
            \is_bool($value) => $value ? 'true' : 'false',
            \is_int($value), \is_string($value) => (string) $value,
            \is_float($value) => Json::float($value),
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof DateTimeInterface => Json::date($value),
            default => throw self::unsupported($name, $value),
        };
    }

    private static function unsupported(string $name, mixed $value): InvalidArgumentException
    {
        $type = get_debug_type($value);

        return new InvalidArgumentException("Unsupported value of type {$type} for parameter {$name}.");
    }
}
