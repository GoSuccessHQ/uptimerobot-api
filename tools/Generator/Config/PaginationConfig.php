<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator\Config;

/**
 * A pagination style of the API.
 *
 * UptimeRobot paginates forward with cursors: the first page is requested
 * without a cursor, every further one with the cursor the previous page
 * reported. The generated list method reads the items itself (typed) and
 * hands the decoded payload plus the items to a hand-written factory, which
 * knows where the payload reports the next cursor.
 *
 * The type of the cursor (int or string) is the type of the operation's
 * cursor parameter, so it can differ per operation.
 */
final readonly class PaginationConfig
{
    /**
     * @param string      $name     Style name referenced by methods.
     * @param string      $cursor   Query parameter carrying the cursor.
     * @param string|null $size     Query parameter carrying the page size, where an
     *                              operation offers one.
     * @param int|null    $pageSize Default page size of the list method; null leaves it
     *                              to the API.
     * @param int|null    $allSize  Page size used when iterating over all items; null
     *                              uses $pageSize.
     * @param string      $items    Response property holding the items.
     * @param string      $next     Response property the factory reads the next page
     *                              from, e.g. "nextLink"; every paginated response must
     *                              have it, or all() would end after the first page.
     * @param string      $factory  "Class::method", see ApiConfig::referencedClass(),
     *                              called as factory(array $data, list $items,
     *                              bool $integerCursor): Page.
     */
    public function __construct(
        public string $name,
        public string $cursor,
        public ?string $size,
        public ?int $pageSize,
        public ?int $allSize,
        public string $items,
        public string $next,
        public string $factory,
    ) {}

    public static function fromArray(string $name, ConfigReader $reader): self
    {
        $instance = new self(
            name: $name,
            cursor: $reader->string('cursor'),
            size: $reader->optionalString('size'),
            pageSize: $reader->optionalInt('pageSize'),
            allSize: $reader->optionalInt('allSize'),
            items: $reader->string('items'),
            next: $reader->string('next'),
            factory: $reader->string('factory'),
        );

        $reader->assertNoUnknownKeys();

        return $instance;
    }
}
