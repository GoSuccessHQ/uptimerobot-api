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
 *
 * Checks that write to the account (see {@see self::writableApiKey()}) also
 * need UPTIMEROBOT_ALLOW_WRITES=1.
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

    /**
     * The API key for a check that creates, changes and deletes its own data.
     * Such a check must leave the account as it found it.
     */
    protected static function writableApiKey(): string
    {
        $key = self::apiKey();

        if (getenv('UPTIMEROBOT_ALLOW_WRITES') !== '1') {
            self::markTestSkipped('Set UPTIMEROBOT_ALLOW_WRITES=1 as well to run the integration tests that write to the account.');
        }

        return $key;
    }
}
