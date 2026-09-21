<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Model;

use BackedEnum;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * Type-safe conversion of loosely typed JSON values into PHP types, used by the
 * models to read API responses.
 *
 * Every method is lenient: a missing or mistyped value yields null (or an empty
 * list/map) instead of an error, so an API that changes a field cannot break
 * the client. Values that PHP could only convert lossily, or with a warning
 * (such as floats beyond the int range on PHP 8.5), yield null as well.
 *
 * @internal
 */
final class Cast
{
    /**
     * ISO 8601 as the API sends it: a date, optionally followed by a time with
     * seconds, fractions and a zone, e.g. `2026-11-18T18:03:20.000Z`. Anything
     * else is rejected before PHP's parser, which would also accept relative
     * formats such as `now` or `next monday`.
     */
    private const string ISO_8601 = '/^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:Z|[+-]\d{2}(?::?\d{2})?)?)?$/iD';

    /**
     * 2^63 as a float: the smallest float above PHP_INT_MAX. Every float below
     * it and at or above -2^63 converts to an int exactly.
     */
    private const float INT_LIMIT = 9.2233720368547758E18;

    private static ?DateTimeZone $utc = null;

    public static function string(mixed $value): ?string
    {
        return match (true) {
            \is_string($value) => $value,
            \is_int($value), \is_float($value) => (string) $value,
            default => null,
        };
    }

    /**
     * An int, an integral float or a string of digits within the int range.
     */
    public static function int(mixed $value): ?int
    {
        return match (true) {
            \is_int($value) => $value,
            \is_float($value) => self::intFromFloat($value),
            \is_string($value) => self::intFromString($value),
            default => null,
        };
    }

    public static function float(mixed $value): ?float
    {
        return match (true) {
            \is_float($value) => $value,
            \is_int($value) => (float) $value,
            \is_string($value) && is_numeric($value) => (float) $value,
            default => null,
        };
    }

    public static function bool(mixed $value): ?bool
    {
        return match ($value) {
            true, 1, '1', 'true', 'True' => true,
            false, 0, '0', 'false', 'False' => false,
            default => null,
        };
    }

    /**
     * Parse an ISO 8601 date such as `2026-11-18T18:03:20.000Z` (the format of
     * JavaScript's `Date`, which the API is built on) or `2026-11-18`. A value
     * without a time zone is interpreted as UTC; an impossible date such as
     * February 30 yields null instead of rolling over into March.
     */
    public static function dateTime(mixed $value): ?DateTimeImmutable
    {
        if (!\is_string($value)) {
            return null;
        }

        $value = trim($value);

        if (preg_match(self::ISO_8601, $value) !== 1) {
            return null;
        }

        try {
            $date = new DateTimeImmutable($value, self::$utc ??= new DateTimeZone('UTC'));
        } catch (Exception) {
            return null;
        }

        $errors = DateTimeImmutable::getLastErrors();

        return $errors === false || $errors['warning_count'] === 0 ? $date : null;
    }

    /**
     * Convert a Unix timestamp in seconds into a date in UTC.
     */
    public static function unixTimestamp(mixed $value): ?DateTimeImmutable
    {
        $seconds = self::int($value);

        if ($seconds === null) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('U', (string) $seconds);

        return $date === false ? null : $date->setTimezone(self::$utc ??= new DateTimeZone('UTC'));
    }

    /**
     * @template T of BackedEnum
     *
     * @param class-string<T> $enum An int-backed enum.
     *
     * @return T|null Null for a missing value or a case this client does not know yet.
     */
    public static function intEnum(string $enum, mixed $value): ?BackedEnum
    {
        $int = self::int($value);

        return $int === null ? null : $enum::tryFrom($int);
    }

    /**
     * @template T of BackedEnum
     *
     * @param class-string<T> $enum A string-backed enum.
     *
     * @return T|null Null for a missing value or a case this client does not know yet.
     */
    public static function stringEnum(string $enum, mixed $value): ?BackedEnum
    {
        $string = self::string($value);

        return $string === null ? null : $enum::tryFrom($string);
    }

    /**
     * @template T of ResponseModel
     *
     * @param class-string<T> $model
     *
     * @return T|null
     */
    public static function model(string $model, mixed $value): ?ResponseModel
    {
        return \is_array($value) ? $model::fromArray($value) : null;
    }

    /**
     * An untyped JSON object or array.
     *
     * @return array<array-key, mixed>|null
     */
    public static function object(mixed $value): ?array
    {
        return \is_array($value) ? $value : null;
    }

    /**
     * @template T of ResponseModel
     *
     * @param class-string<T> $model
     *
     * @return list<T>
     */
    public static function modelList(string $model, mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $list = [];

        foreach ($value as $item) {
            if (\is_array($item)) {
                $list[] = $model::fromArray($item);
            }
        }

        return $list;
    }

    /**
     * @template T
     *
     * @param Closure(mixed): (T|null) $item Converts one element; null drops it.
     *
     * @return list<T>
     */
    public static function listOf(mixed $value, Closure $item): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $list = [];

        foreach ($value as $element) {
            $converted = $item($element);

            if ($converted !== null) {
                $list[] = $converted;
            }
        }

        return $list;
    }

    /**
     * Keys are kept as they are; note that PHP turns numeric keys into integers.
     *
     * @template T
     *
     * @param Closure(mixed): (T|null) $item Converts one value; null drops the entry.
     *
     * @return array<array-key, T>
     */
    public static function mapOf(mixed $value, Closure $item): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $map = [];

        foreach ($value as $key => $element) {
            $converted = $item($element);

            if ($converted !== null) {
                $map[$key] = $converted;
            }
        }

        return $map;
    }

    /**
     * PHP 8.5 warns when casting a float that has no int equivalent (NAN,
     * infinity or beyond the int range); such values, like fractions, yield null.
     */
    private static function intFromFloat(float $value): ?int
    {
        if (!is_finite($value) || $value !== floor($value) || $value >= self::INT_LIMIT || $value < -self::INT_LIMIT) {
            return null;
        }

        return (int) $value;
    }

    /**
     * A cast would silently clamp digits beyond the int range to PHP_INT_MAX,
     * so the result is compared with the input.
     */
    private static function intFromString(string $value): ?int
    {
        if (preg_match('/^(-?)0*(\d+)$/D', $value, $matches) !== 1) {
            return null;
        }

        $int = (int) $value;
        $canonical = ($matches[1] === '-' && $matches[2] !== '0' ? '-' : '') . $matches[2];

        return (string) $int === $canonical ? $int : null;
    }
}
