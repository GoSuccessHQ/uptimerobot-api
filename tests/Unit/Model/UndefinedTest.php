<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Model;

use GoSuccess\UptimeRobot\Model\Undefined;
use GoSuccess\UptimeRobot\Tests\Support\ExampleRequestModel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The sentinel's contract as request models implement it.
 */
#[CoversClass(Undefined::class)]
final class UndefinedTest extends TestCase
{
    public function testLeavesOutFieldsThatWereNotProvided(): void
    {
        self::assertSame([], new ExampleRequestModel()->toArray());
        self::assertSame(['interval' => 300], new ExampleRequestModel(interval: 300)->toArray());
    }

    public function testSendsAnExplicitNullToClearAField(): void
    {
        self::assertSame(['friendlyName' => null], new ExampleRequestModel(friendlyName: null)->toArray());
        self::assertSame('{"friendlyName":null}', json_encode(new ExampleRequestModel(friendlyName: null)->toArray()));
    }

    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('renames')]
    public function testMakesAFieldConditional(bool $rename, array $expected): void
    {
        self::assertSame($expected, new ExampleRequestModel(friendlyName: $rename ? 'Site' : Undefined::Value)->toArray());
    }

    /**
     * @return iterable<string, array{bool, array<string, mixed>}>
     */
    public static function renames(): iterable
    {
        yield 'provided' => [true, ['friendlyName' => 'Site']];
        yield 'left out' => [false, []];
    }

    public function testIsASingleUnbackedCase(): void
    {
        self::assertSame([Undefined::Value], Undefined::cases());
    }
}
