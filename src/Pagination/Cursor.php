<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Pagination;

use GoSuccess\UptimeRobot\Exception\SerializationException;
use GoSuccess\UptimeRobot\Model\Cast;

/**
 * Builds pages from the two ways UptimeRobot reports the cursor of the next
 * page. The generated list methods read the items and call one of these.
 *
 * The cursor is the ID of the last item on the page (verified live), so its
 * type follows the list: an int for most, a numeric string for incidents,
 * whose IDs exceed the range JavaScript numbers hold exactly.
 *
 * @internal
 */
final class Cursor
{
    /**
     * The query parameter that carries the cursor.
     */
    public const string PARAMETER = 'cursor';

    /**
     * `{"data": [...], "nextLink": "https://api.uptimerobot.com/v3/monitors/?limit=1&cursor=803767164"}`.
     *
     * nextLink is the URI of the next page with the filters of the request
     * (verified live), and null on the last page. Only its cursor is used: the
     * list method sends the filters itself.
     *
     * @template T
     *
     * @param array<array-key, mixed> $data          The decoded page.
     * @param list<T>                 $items         The items, already read.
     * @param bool                    $integerCursor Whether the list's cursor is an int.
     *
     * @return Page<T>
     *
     * @throws SerializationException If nextLink is not a URI with a cursor.
     */
    public static function fromNextLink(array $data, array $items, bool $integerCursor): Page
    {
        $link = $data['nextLink'] ?? null;

        if ($link === null) {
            return new Page($items);
        }

        if (!\is_string($link)) {
            throw new SerializationException('Expected nextLink to be a string or null, got ' . get_debug_type($link) . '.');
        }

        $query = parse_url($link, \PHP_URL_QUERY);
        $parameters = [];

        if (\is_string($query)) {
            parse_str($query, $parameters);
        }

        $cursor = $parameters[self::PARAMETER] ?? null;

        if (!\is_string($cursor) || $cursor === '') {
            throw new SerializationException("The nextLink \"{$link}\" has no cursor.");
        }

        return new Page($items, self::cursor($cursor, $integerCursor, 'nextLink'));
    }

    /**
     * `{"data": [...], "nextCursorId": 42}`, with null on the last page; only
     * GET /tags reports its cursor like this.
     *
     * @template T
     *
     * @param array<array-key, mixed> $data          The decoded page.
     * @param list<T>                 $items         The items, already read.
     * @param bool                    $integerCursor Whether the list's cursor is an int.
     *
     * @return Page<T>
     *
     * @throws SerializationException If nextCursorId is neither null nor a cursor.
     */
    public static function fromNextCursorId(array $data, array $items, bool $integerCursor): Page
    {
        $next = $data['nextCursorId'] ?? null;

        if ($next === null) {
            return new Page($items);
        }

        if (!\is_int($next) && !\is_string($next)) {
            throw new SerializationException('Expected nextCursorId to be an integer, a string or null, got ' . get_debug_type($next) . '.');
        }

        return new Page($items, self::cursor($next, $integerCursor, 'nextCursorId'));
    }

    private static function cursor(int|string $value, bool $integer, string $source): int|string
    {
        if (!$integer) {
            return (string) $value;
        }

        return Cast::int($value) ?? throw new SerializationException("Expected an integer cursor in {$source}, got \"{$value}\".");
    }
}
