<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Pagination;

use GoSuccess\UptimeRobot\Pagination\Page;
use GoSuccess\UptimeRobot\Pagination\Paginator;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Paginator::class)]
#[CoversClass(Page::class)]
final class PaginatorTest extends TestCase
{
    public function testIteratesAcrossAllPagesWithSequentialKeys(): void
    {
        $requested = [];
        // Cursors are the ID of the last item of the previous page.
        $pages = [
            '' => new Page(['a', 'b'], next: 802),
            802 => new Page(['c', 'd'], next: 804),
            804 => new Page(['e']),
        ];

        $paginator = new Paginator(static function (int|string|null $cursor) use ($pages, &$requested): Page {
            $requested[] = $cursor;

            return $pages[$cursor ?? ''];
        });

        self::assertSame(['a', 'b', 'c', 'd', 'e'], iterator_to_array($paginator));
        self::assertSame([null, 802, 804], $requested);
    }

    public function testFetchesLazily(): void
    {
        $calls = 0;
        $paginator = new Paginator(static function (int|string|null $cursor) use (&$calls): Page {
            ++$calls;

            return new Page(['x'], next: 'cursor-' . $calls);
        });

        foreach ($paginator as $item) {
            break;
        }

        self::assertSame(1, $calls);
    }

    public function testFollowsEmptyPagesThatAnnounceMore(): void
    {
        $paginator = new Paginator(static fn(int|string|null $cursor): Page => match ($cursor) {
            null => new Page([], next: '100'),
            '100' => new Page(['a']),
            default => throw new LogicException('Unexpected cursor.'),
        });

        self::assertSame(['a'], iterator_to_array($paginator));
    }

    public function testStopsWhenTheCursorDoesNotAdvance(): void
    {
        $calls = 0;
        $paginator = new Paginator(static function (int|string|null $cursor) use (&$calls): Page {
            ++$calls;

            return new Page(['x'], next: 'same');
        });

        self::assertCount(2, iterator_to_array($paginator));
        self::assertSame(2, $calls);
    }

    public function testStopsWhenTheCursorsRunInACircle(): void
    {
        $requested = [];
        $paginator = new Paginator(static function (int|string|null $cursor) use (&$requested): Page {
            $requested[] = $cursor;

            return new Page([$cursor], next: $cursor === 1 ? 2 : 1);
        });

        self::assertSame([null, 1, 2], iterator_to_array($paginator));
        self::assertSame([null, 1, 2], $requested);
    }

    public function testTellsIntAndStringCursorsApart(): void
    {
        $requested = [];
        $paginator = new Paginator(static function (int|string|null $cursor) use (&$requested): Page {
            $requested[] = $cursor;

            return new Page(['x'], next: match ($cursor) {
                null => 5,
                5 => '5',
                default => null,
            });
        });

        iterator_to_array($paginator);

        self::assertSame([null, 5, '5'], $requested);
    }

    public function testPageReportsWhetherMoreFollow(): void
    {
        self::assertTrue(new Page(['a'], next: 2)->hasMore);
        self::assertTrue(new Page([], next: '803767164')->hasMore);
        self::assertFalse(new Page(['a'])->hasMore);
    }
}
