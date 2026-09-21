<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Pagination;

use GoSuccess\UptimeRobot\Exception\SerializationException;
use GoSuccess\UptimeRobot\Pagination\Cursor;
use GoSuccess\UptimeRobot\Pagination\Page;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Cursor::class)]
#[CoversClass(Page::class)]
final class CursorTest extends TestCase
{
    public function testReadsTheCursorOfAnAbsoluteNextLink(): void
    {
        // As the API sends it (verified live): the filters of the request plus the cursor.
        $page = Cursor::fromNextLink(['data' => [], 'nextLink' => 'https://api.uptimerobot.com/v3/monitors/?limit=1&cursor=803767164'], ['a'], integerCursor: true);

        self::assertSame(['a'], $page->items);
        self::assertSame(803767164, $page->next);
        self::assertTrue($page->hasMore);
    }

    public function testReadsTheCursorOfARelativeNextLink(): void
    {
        self::assertSame('352577094135060139', Cursor::fromNextLink(['nextLink' => '/v3/incidents?cursor=352577094135060139&monitor_id=5'], [], integerCursor: false)->next);
        self::assertSame('x y', Cursor::fromNextLink(['nextLink' => '?cursor=x%20y'], [], integerCursor: false)->next);
    }

    public function testEndsWithoutANextLink(): void
    {
        self::assertNull(Cursor::fromNextLink(['data' => [], 'nextLink' => null], [], integerCursor: true)->next);
        self::assertFalse(Cursor::fromNextLink(['data' => []], [], integerCursor: true)->hasMore);
    }

    /**
     * @return iterable<string, array{mixed, bool, string}>
     */
    public static function brokenNextLinks(): iterable
    {
        yield 'no cursor' => ['https://api.uptimerobot.com/v3/monitors/?limit=1', false, 'has no cursor'];
        yield 'no query' => ['https://api.uptimerobot.com/v3/monitors/', false, 'has no cursor'];
        yield 'empty' => ['', false, 'has no cursor'];
        yield 'empty cursor' => ['/v3/monitors?cursor=', false, 'has no cursor'];
        yield 'list cursor' => ['/v3/monitors?cursor[]=5', false, 'has no cursor'];
        yield 'not a string' => [5, false, 'Expected nextLink to be a string or null, got int.'];
        yield 'not an integer' => ['/v3/monitors?cursor=abc', true, 'Expected an integer cursor in nextLink, got "abc".'];
        yield 'beyond the int range' => ['/v3/monitors?cursor=99999999999999999999', true, 'Expected an integer cursor in nextLink'];
    }

    #[DataProvider('brokenNextLinks')]
    public function testRejectsNextLinksWithoutAUsableCursor(mixed $link, bool $integer, string $message): void
    {
        $this->expectException(SerializationException::class);
        $this->expectExceptionMessage($message);

        Cursor::fromNextLink(['nextLink' => $link], [], integerCursor: $integer);
    }

    public function testReadsNextCursorId(): void
    {
        // GET /tags (verified live: {"data":[],"nextCursorId":null}).
        self::assertNull(Cursor::fromNextCursorId(['data' => [], 'nextCursorId' => null], [], integerCursor: true)->next);
        self::assertNull(Cursor::fromNextCursorId([], [], integerCursor: true)->next);
        self::assertSame(42, Cursor::fromNextCursorId(['nextCursorId' => 42], ['a'], integerCursor: true)->next);
        self::assertSame(42, Cursor::fromNextCursorId(['nextCursorId' => '42'], ['a'], integerCursor: true)->next);
        self::assertSame('42', Cursor::fromNextCursorId(['nextCursorId' => 42], ['a'], integerCursor: false)->next);
    }

    public function testRejectsANextCursorIdThatIsNoCursor(): void
    {
        $this->expectException(SerializationException::class);
        $this->expectExceptionMessage('Expected nextCursorId to be an integer, a string or null, got float.');

        Cursor::fromNextCursorId(['nextCursorId' => 4.5], [], integerCursor: true);
    }

    public function testRejectsANextCursorIdThatIsNoInteger(): void
    {
        $this->expectException(SerializationException::class);
        $this->expectExceptionMessage('Expected an integer cursor in nextCursorId, got "4x".');

        Cursor::fromNextCursorId(['nextCursorId' => '4x'], [], integerCursor: true);
    }
}
