<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Tools\Generator;

use GoSuccess\UptimeRobot\Tools\Generator\PathPatterns;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(PathPatterns::class)]
final class PathPatternsTest extends TestCase
{
    public function testMatchesExactLocations(): void
    {
        $patterns = new PathPatterns('enums', ['MonitorDto.status' => 'MonitorStatus']);

        self::assertSame('MonitorStatus', $patterns->match('MonitorDto.status'));
        self::assertNull($patterns->match('MonitorDto.statuses'));
        self::assertNull($patterns->match('XMonitorDto.status'));
    }

    public function testTheWildcardMatchesAnyCharactersIncludingDots(): void
    {
        $patterns = PathPatterns::of('integers', ['*.id', 'Create*MonitorDto.interval']);

        self::assertTrue($patterns->matches('TagsController_deleteTag.id'));
        self::assertTrue($patterns->matches('SimpleTagPaginationDto.data[].id'));
        self::assertTrue($patterns->matches('CreateHttpMonitorDto.interval'));
        self::assertFalse($patterns->matches('MonitorDto.userId'));
    }

    public function testBracketsAndBracesAreLiteral(): void
    {
        $patterns = PathPatterns::of('integers', ['MonitorDto.ids[]', 'MonitorDto.map{}']);

        self::assertTrue($patterns->matches('MonitorDto.ids[]'));
        self::assertTrue($patterns->matches('MonitorDto.map{}'));
        self::assertFalse($patterns->matches('MonitorDto.idsx'));
        self::assertFalse($patterns->matches('MonitorDto.ids[].id'));
    }

    public function testReportsPatternsThatNeverMatched(): void
    {
        $patterns = PathPatterns::of('floats', ['a.b', 'c.*', 'd']);
        $patterns->match('c.x');

        self::assertSame(['a.b', 'd'], $patterns->unused());
        self::assertSame('floats', $patterns->key());
    }

    public function testMatchingPatternsMayAgree(): void
    {
        $patterns = new PathPatterns('enums', ['*.thresholdType' => 'ThresholdType', 'UpdateDto.*' => 'ThresholdType']);

        self::assertSame('ThresholdType', $patterns->match('UpdateDto.thresholdType'));
        self::assertSame([], $patterns->unused());
    }

    public function testMatchingPatternsMustNotDisagree(): void
    {
        $patterns = new PathPatterns('enums', ['*.status' => 'MonitorStatus', 'PspDto.*' => 'StatusPageStatus']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("'enums': *.status and PspDto.* both match PspDto.status but disagree.");

        $patterns->match('PspDto.status');
    }
}
