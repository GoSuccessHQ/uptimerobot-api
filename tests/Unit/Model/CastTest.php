<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Model;

use GoSuccess\UptimeRobot\Http\Method;
use GoSuccess\UptimeRobot\Model\Cast;
use GoSuccess\UptimeRobot\Tests\Support\ExampleIntEnum;
use GoSuccess\UptimeRobot\Tests\Support\ExampleModel;
use GoSuccess\UptimeRobot\Tests\Support\ExampleUnknownModel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Cast::class)]
final class CastTest extends TestCase
{
    public function testConvertsScalarsLeniently(): void
    {
        self::assertSame('abc', Cast::string('abc'));
        self::assertSame('5', Cast::string(5));
        self::assertSame('352577094135060139', Cast::string('352577094135060139'));
        self::assertNull(Cast::string(true));
        self::assertNull(Cast::string(null));

        self::assertSame(1.5, Cast::float(1.5));
        self::assertSame(2.0, Cast::float(2));
        self::assertSame(0.25, Cast::float('0.25'));
        self::assertNull(Cast::float('x'));
        self::assertNull(Cast::float(null));

        self::assertTrue(Cast::bool(true));
        // Status page features arrive as the strings "true" and "false".
        self::assertTrue(Cast::bool('true'));
        self::assertFalse(Cast::bool(0));
        self::assertFalse(Cast::bool('False'));
        self::assertNull(Cast::bool('yes'));
    }

    #[DataProvider('ints')]
    public function testConvertsIntsWithoutLossOrWarnings(mixed $value, ?int $expected): void
    {
        self::assertSame($expected, Cast::int($value));
    }

    /**
     * @return iterable<string, array{mixed, int|null}>
     */
    public static function ints(): iterable
    {
        yield 'int' => [5, 5];
        yield 'integral float' => [5.0, 5];
        yield 'negative string' => ['-12', -12];
        yield 'leading zeros' => ['007', 7];
        yield 'negative zero' => ['-0', 0];
        yield 'incident id' => ['352577094135060139', 352577094135060139];
        yield 'largest int as string' => ['9223372036854775807', \PHP_INT_MAX];
        yield 'smallest int as string' => ['-9223372036854775808', \PHP_INT_MIN];
        yield 'smallest int as float' => [-9.2233720368547758E18, \PHP_INT_MIN];
        yield 'fraction' => [5.5, null];
        yield 'fraction as string' => ['5.5', null];
        yield 'text' => ['abc', null];
        yield 'trailing line break' => ["12\n", null];
        yield 'string beyond the int range' => ['9223372036854775808', null];
        yield 'float beyond the int range' => [1.0E20, null];
        yield 'float at 2^63' => [9.2233720368547758E18, null];
        yield 'float below the int range' => [-1.0E19, null];
        yield 'infinity' => [\INF, null];
        yield 'not a number' => [\NAN, null];
        yield 'bool' => [true, null];
        yield 'null' => [null, null];
    }

    #[DataProvider('dates')]
    public function testParsesIso8601Dates(mixed $value, ?string $expected): void
    {
        self::assertSame($expected, Cast::dateTime($value)?->format('Y-m-d\TH:i:s.uP'));
    }

    /**
     * @return iterable<string, array{mixed, string|null}>
     */
    public static function dates(): iterable
    {
        // The format of the API, as observed live.
        yield 'milliseconds and Z' => ['2026-11-18T18:03:20.000Z', '2026-11-18T18:03:20.000000+00:00'];
        yield 'offset' => ['2026-09-17T06:20:32+02:00', '2026-09-17T06:20:32.000000+02:00'];
        yield 'offset without colon' => ['2026-09-17T06:20:32+0200', '2026-09-17T06:20:32.000000+02:00'];
        yield 'no zone means UTC' => ['2026-09-17T06:20:32', '2026-09-17T06:20:32.000000+00:00'];
        yield 'space separator' => ['2026-09-17 06:20:32', '2026-09-17T06:20:32.000000+00:00'];
        yield 'without seconds' => ['2026-09-17T06:20Z', '2026-09-17T06:20:00.000000+00:00'];
        yield 'date only' => ['2026-09-17', '2026-09-17T00:00:00.000000+00:00'];
        yield 'seven fractional digits' => ['2026-09-17T06:20:32.1234567Z', '2026-09-17T06:20:32.123456+00:00'];
        yield 'surrounding whitespace' => [' 2026-09-17T06:20:32Z ', '2026-09-17T06:20:32.000000+00:00'];
        yield 'impossible date' => ['2026-02-30T00:00:00Z', null];
        yield 'impossible time' => ['2026-09-17T25:00:00Z', null];
        yield 'relative format' => ['now', null];
        yield 'relative weekday' => ['next monday', null];
        yield 'date with trailing text' => ['2026-09-17 +1 day', null];
        yield 'empty' => ['', null];
        yield 'number' => [1_700_000_000, null];
        yield 'null' => [null, null];
    }

    public function testConvertsUnixTimestamps(): void
    {
        self::assertSame('2023-11-14T22:13:20+00:00', Cast::unixTimestamp(1_700_000_000)?->format(\DATE_ATOM));
        self::assertSame('2023-11-14T22:13:20+00:00', Cast::unixTimestamp('1700000000')?->format(\DATE_ATOM));
        self::assertSame('UTC', Cast::unixTimestamp(1_700_000_000.0)?->getTimezone()->getName());
        self::assertSame('1969-12-31T23:59:55+00:00', Cast::unixTimestamp(-5)?->format(\DATE_ATOM));
        self::assertNull(Cast::unixTimestamp(1.5));
        self::assertNull(Cast::unixTimestamp('2026-09-17'));
        self::assertNull(Cast::unixTimestamp(null));
    }

    public function testConvertsEnumsWithoutTypeErrors(): void
    {
        self::assertSame(ExampleIntEnum::Two, Cast::intEnum(ExampleIntEnum::class, 2));
        self::assertSame(ExampleIntEnum::Two, Cast::intEnum(ExampleIntEnum::class, '2'));
        self::assertNull(Cast::intEnum(ExampleIntEnum::class, 99));
        self::assertNull(Cast::intEnum(ExampleIntEnum::class, 'two'));
        self::assertNull(Cast::intEnum(ExampleIntEnum::class, 1.0E20));

        self::assertSame(Method::Get, Cast::stringEnum(Method::class, 'GET'));
        self::assertNull(Cast::stringEnum(Method::class, 'TRACE'));
        self::assertNull(Cast::stringEnum(Method::class, null));
    }

    public function testConvertsModelsListsAndMaps(): void
    {
        self::assertSame('a', Cast::model(ExampleModel::class, ['name' => 'a'])?->name);
        self::assertNull(Cast::model(ExampleModel::class, 'a'));

        $models = Cast::modelList(ExampleModel::class, [['name' => 'a'], 'junk', ['name' => 'b']]);
        self::assertSame(['a', 'b'], array_map(static fn(ExampleModel $model): ?string => $model->name, $models));
        self::assertSame([], Cast::modelList(ExampleModel::class, null));

        self::assertSame(['a', '5'], Cast::listOf(['a', 5, null, true], Cast::string(...)));
        self::assertSame([], Cast::listOf('scalar', Cast::string(...)));

        // Numeric JSON object keys arrive as integers.
        self::assertSame([1 => 5, 28 => 7, 'x' => 1], Cast::mapOf(['1' => 5, '28' => 7, 'x' => 1, 'y' => 'no'], Cast::int(...)));
        self::assertSame([], Cast::mapOf(null, Cast::int(...)));

        self::assertSame(['a' => 1], Cast::object(['a' => 1]));
        self::assertNull(Cast::object('x'));
    }

    public function testReadsTheVariantADiscriminatorNames(): void
    {
        $variants = ['COMMENT' => ExampleModel::class, 7 => ExampleModel::class];
        $read = static fn(mixed $value): ExampleModel|ExampleUnknownModel|null => Cast::union($value, 'type', $variants, ExampleUnknownModel::class);

        $comment = $read(['type' => 'COMMENT', 'name' => 'a']);
        self::assertInstanceOf(ExampleModel::class, $comment);
        self::assertSame('a', $comment->name);
        self::assertInstanceOf(ExampleModel::class, $read(['type' => 7]));

        // Unknown, missing and malformed discriminators select the fallback,
        // which receives the whole payload.
        $unknown = $read(['type' => 'NOTIFICATION', 'name' => 'b']);
        self::assertInstanceOf(ExampleUnknownModel::class, $unknown);
        self::assertSame(['type' => 'NOTIFICATION', 'name' => 'b'], $unknown->data);
        self::assertInstanceOf(ExampleUnknownModel::class, $read(['name' => 'c']));
        self::assertInstanceOf(ExampleUnknownModel::class, $read(['type' => ['COMMENT']]));

        self::assertNull($read('COMMENT'));
        self::assertNull($read(null));
    }
}
