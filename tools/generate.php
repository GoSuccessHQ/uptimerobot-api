<?php

declare(strict_types=1);

/**
 * Generate the enums, models, resources and the client of the UptimeRobot API
 * from the committed specification snapshot (resources/specs/uptimerobot.json)
 * and the generator configuration (tools/config/uptimerobot.php).
 *
 * Usage:
 *   php tools/generate.php
 *
 * Generated files start with a marker comment; files with the marker that are
 * no longer produced are deleted, hand-written files are never touched. Run
 * `composer generate` instead to format the result and refresh docs/ as well.
 */

use GoSuccess\UptimeRobot\Tools\Generator\Generator;

require __DIR__ . '/../vendor/autoload.php';

try {
    echo Generator::fromFile(__DIR__ . '/config/uptimerobot.php')->run();
} catch (Throwable $e) {
    fwrite(STDERR, "{$e->getMessage()}\n");

    exit(1);
}
