<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Tools\Generator;

use GoSuccess\UptimeRobot\Tools\Generator\Naming;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Naming::class)]
final class NamingTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function propertyNames(): iterable
    {
        yield 'camelCase' => ['friendlyName', 'friendlyName'];
        yield 'SCREAMING_SNAKE' => ['MANUAL_SELECTED', 'manualSelected'];
        yield 'one capital word' => ['REGION', 'region'];
        yield 'another capital word' => ['THRESHOLD', 'threshold'];
        yield 'acronym' => ['IP', 'ip'];
        yield 'mixed-case acronym' => ['IPv6', 'ipv6'];
        yield 'capital acronym' => ['CNAME', 'cname'];
        yield 'snake_case' => ['total_downtime_seconds', 'totalDowntimeSeconds'];
        yield 'snake_case with a short word' => ['android_push_up_channel', 'androidPushUpChannel'];
        yield 'inner capitals' => ['checkSSLErrors', 'checkSSLErrors'];
        yield 'leading acronym' => ['URLToNotify', 'urlToNotify'];
        yield 'leading digit' => ['2fa', 'value2fa'];
    }

    #[DataProvider('propertyNames')]
    public function testDerivesPropertyNames(string $key, string $expected): void
    {
        self::assertSame($expected, Naming::camel($key));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function enumCases(): iterable
    {
        yield 'SCREAMING_SNAKE' => ['VISUAL_COMPARISON', 'VisualComparison'];
        yield 'SCREAMING_SNAKE with an acronym' => ['HTTP_BASIC', 'HttpBasic'];
        yield 'SCREAMING_SNAKE with digits' => ['DAYS_30', 'Days30'];
        yield 'SCREAMING_SNAKE status' => ['LOOKS_DOWN', 'LooksDown'];
        yield 'one capital word' => ['UP', 'Up'];
        yield 'camelCase' => ['ipv4Only', 'Ipv4Only'];
        yield 'snake_case' => ['not_equals', 'NotEquals'];
        yield 'PascalCase' => ['UpAndDown', 'UpAndDown'];
        yield 'lower case' => ['count', 'Count'];
        yield 'mixed-case acronym' => ['MSTeams', 'MSTeams'];
        yield 'leading digit' => ['30', 'Value30'];
        yield 'reserved' => ['class', 'ClassValue'];
    }

    #[DataProvider('enumCases')]
    public function testDerivesEnumCasesFromValues(string $value, string $expected): void
    {
        self::assertSame($expected, Naming::enumCaseFromValue($value));
    }

    public function testKeepsConfiguredEnumCasesThatAreIdentifiers(): void
    {
        self::assertSame('NOT_EQUALS', Naming::enumCase('NOT_EQUALS'));
        self::assertSame('CaseSensitive', Naming::enumCase('CaseSensitive'));
        self::assertSame('CaseSensitive', Naming::enumCase('case sensitive'));
        self::assertSame('classValue', Naming::enumCase('class'));
    }

    public function testDerivesClassNames(): void
    {
        self::assertSame('MonitorGroup', Naming::pascal('monitor_group'));
        self::assertSame('DataItem', Naming::pascal('.data[]Item'));
        self::assertSame('Value3d', Naming::pascal('3d'));
    }

    public function testDerivesConstantNames(): void
    {
        self::assertSame('TYPE', Naming::constant('type'));
        self::assertSame('ALERT_TYPE', Naming::constant('alertType'));
        self::assertSame('CHANNEL_TYPE', Naming::constant('channel_type'));
    }

    public function testRejectsNamesWithoutLettersOrDigits(): void
    {
        $this->expectException(RuntimeException::class);

        Naming::camel('--');
    }
}
