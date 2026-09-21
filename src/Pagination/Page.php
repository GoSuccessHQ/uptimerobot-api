<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Pagination;

/**
 * One page of a list endpoint.
 *
 * UptimeRobot paginates with cursors: a page holds its items plus the cursor
 * of the next page, which is passed back to fetch that page.
 *
 * @template-covariant T
 */
final class Page
{
    /**
     * Whether another page follows this one.
     */
    public bool $hasMore {
        get => $this->next !== null;
    }

    /**
     * @param list<T>         $items The items on this page.
     * @param int|string|null $next  The cursor of the next page, or null if this is
     *                               the last page.
     */
    public function __construct(
        public readonly array $items,
        public readonly int|string|null $next = null,
    ) {}
}
