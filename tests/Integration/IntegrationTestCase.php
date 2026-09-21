<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Base class of the checks against the live API.
 *
 * They are skipped unless the UPTIMEROBOT_API_KEY environment variable holds
 * an API key:
 *
 *   UPTIMEROBOT_API_KEY=... composer test:integration
 */
abstract class IntegrationTestCase extends TestCase
{
    protected static function apiKey(): string
    {
        $key = getenv('UPTIMEROBOT_API_KEY');

        if (!\is_string($key) || $key === '') {
            self::markTestSkipped('Set UPTIMEROBOT_API_KEY to run the integration tests.');
        }

        return $key;
    }
}
