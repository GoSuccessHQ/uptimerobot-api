<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Support\Generated;

use GoSuccess\UptimeRobot\Tools\Generator\Analysis;
use GoSuccess\UptimeRobot\Tools\Generator\Generator;

/**
 * The generator's view of the configured API, built once per test run. Its
 * configuration checks (coverage, unnamed enums, unclassified numbers, …)
 * therefore run with the tests too.
 */
final class GeneratedApi
{
    private static ?Analysis $analysis = null;

    public static function analysis(): Analysis
    {
        return self::$analysis ??= Generator::fromFile(\dirname(__DIR__, 3) . '/tools/config/uptimerobot.php')->analyze();
    }
}
