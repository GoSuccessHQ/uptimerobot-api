<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Model;

use DateTime;
use DateTimeImmutable;
use GoSuccess\UptimeRobot\Model\Json;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Json::class)]
final class JsonTest extends TestCase
{
    public function testFormatsDatesAsUtcWithMilliseconds(): void
    {
        self::assertSame('2026-09-18T12:30:00.000Z', Json::date(new DateTimeImmutable('2026-09-18T14:30:00+02:00')));
        // The precision of the API's dates, e.g. the start of an incident (verified live).
        self::assertSame('2026-09-16T08:41:11.469Z', Json::date(new DateTime('2026-09-16T08:41:11.469Z')));
        self::assertSame('2026-09-18T12:30:00.999Z', Json::date(new DateTimeImmutable('2026-09-18T12:30:00.999999Z')));
    }

    public function testFormatsFloatsWithoutExponent(): void
    {
        self::assertSame('0.1', Json::float(0.1));
        self::assertSame('0.00001', Json::float(1e-5));
        self::assertSame('100000000000000000000', Json::float(1e20));
        self::assertSame('0', Json::float(-0.0));
        self::assertSame('-2.5', Json::float(-2.5));
    }

    public function testEncodesAnEmptyMapAsObject(): void
    {
        self::assertEquals(new stdClass(), Json::map([]));
        self::assertSame(['a' => 1], Json::map(['a' => 1]));
        self::assertSame('{}', json_encode(Json::map([])));
    }
}
