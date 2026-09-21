<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator;

use RuntimeException;

/**
 * Deterministic naming rules for generated code.
 */
final class Naming
{
    /**
     * Mixed-case acronyms that would otherwise be split wrongly at the start of a
     * name, e.g. "IPv6" would become "iPv6".
     */
    private const array LEADING_TOKENS = ['IPv4' => 'ipv4', 'IPv6' => 'ipv6', 'OAuth' => 'oauth'];

    /**
     * camelCase name for a property or parameter, derived from a JSON key.
     *
     * "friendlyName" → "friendlyName", "CNAME" → "cname", "IP" → "ip",
     * "IPv6" → "ipv6", "MANUAL_SELECTED" → "manualSelected",
     * "total_downtime_seconds" → "totalDowntimeSeconds". Capitals inside a
     * camelCase key are kept: "checkSSLErrors" stays as it is.
     */
    public static function camel(string $key): string
    {
        $words = self::words($key);

        if ($words === []) {
            throw new RuntimeException("Cannot derive a name from \"{$key}\".");
        }

        $first = self::lowerLeading(array_shift($words));
        $rest = array_map(self::capitalize(...), $words);
        $name = $first . implode('', $rest);

        return preg_match('/^\d/', $name) === 1 ? "value{$name}" : $name;
    }

    /**
     * PascalCase class name: "monitor_group" → "MonitorGroup"; inner capitals are kept.
     */
    public static function pascal(string $value): string
    {
        $name = implode('', array_map(static fn(string $word): string => ucfirst($word), self::words($value)));

        if ($name === '') {
            throw new RuntimeException("Cannot derive a class name from \"{$value}\".");
        }

        return preg_match('/^\d/', $name) === 1 ? "Value{$name}" : $name;
    }

    /**
     * Enum case name from a configured name: kept as it is if it is a valid
     * identifier, otherwise converted to PascalCase.
     */
    public static function enumCase(string $name): string
    {
        $case = preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) === 1 ? $name : self::pascal($name);

        return self::avoidReserved($case);
    }

    /**
     * PascalCase enum case derived from a value or a name in the specification:
     * "VISUAL_COMPARISON" → "VisualComparison", "HTTP_BASIC" → "HttpBasic",
     * "DAYS_30" → "Days30", "UP" → "Up", "ipv4Only" → "Ipv4Only",
     * "not_equals" → "NotEquals", "UpAndDown" → "UpAndDown".
     */
    public static function enumCaseFromValue(string $value): string
    {
        $case = implode('', array_map(self::capitalize(...), self::words($value)));

        if ($case === '') {
            throw new RuntimeException("Cannot derive an enum case from \"{$value}\".");
        }

        return self::avoidReserved(preg_match('/^\d/', $case) === 1 ? "Value{$case}" : $case);
    }

    /**
     * SCREAMING_SNAKE constant name: "type" → "TYPE", "alertType" → "ALERT_TYPE".
     */
    public static function constant(string $key): string
    {
        $snake = (string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', self::camel($key));

        return strtoupper($snake);
    }

    /**
     * @return list<string>
     */
    private static function words(string $value): array
    {
        return preg_split('/[^A-Za-z0-9]+/', $value, -1, \PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * An all-capital word becomes "Word"; a mixed-case word keeps its inner
     * capitals ("ipv4Only" → "Ipv4Only", "UpAndDown" stays).
     */
    private static function capitalize(string $word): string
    {
        return preg_match('/^[A-Z0-9]+$/', $word) === 1 ? ucfirst(strtolower($word)) : ucfirst($word);
    }

    /**
     * "class" is the only name a class constant, and so an enum case, cannot have.
     */
    private static function avoidReserved(string $case): string
    {
        return strtolower($case) === 'class' ? "{$case}Value" : $case;
    }

    private static function lowerLeading(string $word): string
    {
        foreach (self::LEADING_TOKENS as $token => $lower) {
            if (str_starts_with($word, $token)) {
                return $lower . substr($word, \strlen($token));
            }
        }

        if (preg_match('/^[A-Z]+/', $word, $match) !== 1) {
            return $word;
        }

        $run = $match[0];
        $length = \strlen($run);

        if ($length === \strlen($word) || $length === 1) {
            // "ID" → "id", "Origin" → "origin"
            return strtolower($run) . substr($word, $length);
        }

        $next = $word[$length];

        if (ctype_lower($next)) {
            // "CNAMEDomain" → "cname" + "Domain"
            return strtolower(substr($run, 0, -1)) . substr($word, $length - 1);
        }

        // "S3Type", "URL2" → lower the whole run
        return strtolower($run) . substr($word, $length);
    }
}
