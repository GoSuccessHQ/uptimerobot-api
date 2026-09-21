<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Http;

use DateTimeImmutable;
use DateTimeZone;
use GoSuccess\UptimeRobot\Http\Method;
use GoSuccess\UptimeRobot\Http\Query;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Query::class)]
final class QueryTest extends TestCase
{
    public function testFormatsValuesTheWayTheApiExpects(): void
    {
        $query = Query::build([
            'limit' => 50,
            'includeTimeSeries' => true,
            'includeOrgMembers' => false,
            'ratio' => 0.5,
            'name' => 'a b/c&d',
            'method' => Method::Post,
            'from' => new DateTimeImmutable('2026-09-18 14:30:00', new DateTimeZone('Europe/Berlin')),
            'skipped' => null,
        ]);

        self::assertSame(
            'limit=50&includeTimeSeries=true&includeOrgMembers=false&ratio=0.5&name=a%20b%2Fc%26d&method=POST&from=2026-09-18T12%3A30%3A00.000Z',
            $query,
        );
    }

    public function testSendsListsAsRepeatedKeys(): void
    {
        self::assertSame(
            'customField=team%3Aops&customField=env%3Aprod',
            Query::build(['customField' => ['team:ops', null, 'env:prod']]),
        );
    }

    public function testReturnsAnEmptyStringWithoutParameters(): void
    {
        self::assertSame('', Query::build(['a' => null]));
        self::assertSame('', Query::build([]));
    }

    public function testFormatsFloatsWithoutExponent(): void
    {
        self::assertSame('x=0.00001&y=1000000&z=0', Query::build(['x' => 0.00001, 'y' => 1e6, 'z' => -0.0]));
    }

    public function testRejectsUnsupportedValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported value of type stdClass for parameter object.');

        Query::build(['object' => new stdClass()]);
    }
}
