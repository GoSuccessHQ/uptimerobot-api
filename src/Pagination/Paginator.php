<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Pagination;

use Closure;
use Generator;
use IteratorAggregate;

/**
 * Lazily iterates over every item of a list endpoint, across all pages.
 *
 * Pages are only requested while the iteration proceeds, so breaking out of a
 * loop early saves the remaining requests. Keys are sequential, which makes the
 * paginator safe to pass to `iterator_to_array()`.
 *
 * @template-covariant T
 *
 * @implements IteratorAggregate<int, T>
 */
final readonly class Paginator implements IteratorAggregate
{
    /**
     * @param Closure(int|string|null): Page<T> $fetchPage Fetches the page at the given
     *                                                     cursor; null requests the first one.
     */
    public function __construct(private Closure $fetchPage) {}

    /**
     * @return Generator<int, T>
     */
    public function getIterator(): Generator
    {
        $position = null;
        $index = 0;
        $requested = [];

        do {
            $page = ($this->fetchPage)($position);

            foreach ($page->items as $item) {
                yield $index++ => $item;
            }

            // A cursor that was already requested (the same one again, or a
            // cycle across pages) would repeat the same pages forever.
            $requested[self::key($position)] = true;
            $position = $page->next;
        } while ($position !== null && !isset($requested[self::key($position)]));
    }

    /**
     * Distinguishes the int cursor 5 from the string cursor "5", which PHP
     * would merge as array keys, and keeps null apart from both.
     */
    private static function key(int|string|null $position): string
    {
        return match (true) {
            $position === null => 'n',
            \is_int($position) => "i{$position}",
            default => "s{$position}",
        };
    }
}
