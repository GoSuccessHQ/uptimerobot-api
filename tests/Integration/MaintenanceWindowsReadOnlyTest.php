<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Integration;

use GoSuccess\UptimeRobot\Enum\MaintenanceWindowInterval;
use GoSuccess\UptimeRobot\Exception\ForbiddenException;
use GoSuccess\UptimeRobot\Exception\NotFoundException;
use GoSuccess\UptimeRobot\Model\MaintenanceWindow;
use GoSuccess\UptimeRobot\UptimeRobot;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Reads the maintenance windows of the account and those its monitors name;
 * nothing is changed. Most checks need at least one window.
 */
#[CoversNothing]
final class MaintenanceWindowsReadOnlyTest extends IntegrationTestCase
{
    /** @var list<MaintenanceWindow>|null Every window, read once for all tests. */
    private static ?array $windows = null;

    public function testListsTheWindows(): void
    {
        $windows = self::windows();
        $ids = array_map(static fn(MaintenanceWindow $window): int => $window->id, $windows);

        // An account without windows gets {"data": []} (verified live).
        self::assertSame($ids, array_values(array_unique($ids)));

        foreach ($windows as $window) {
            // Every value the API sends is one the enums know.
            self::assertNotNull($window->interval, "Unknown interval of window {$window->id}.");
            self::assertNotNull($window->status, "Unknown status of window {$window->id}.");
            self::assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}$/', $window->time);
            self::assertGreaterThan(0, $window->duration);

            if ($window->date !== null) {
                self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $window->date);
            }

            if ($window->interval === MaintenanceWindowInterval::Weekly) {
                foreach ($window->days as $day) {
                    // 1 = Monday to 7 = Sunday according to the official Terraform
                    // provider; not verified live, so this fails if the API numbers
                    // them from 0.
                    self::assertGreaterThanOrEqual(1, $day);
                    self::assertLessThanOrEqual(7, $day);
                }
            }
        }
    }

    public function testReadsAWindowAsTheListDoes(): void
    {
        $windows = self::windows();

        if ($windows === []) {
            self::markTestSkipped('The account has no maintenance windows.');
        }

        self::assertEquals($windows[0], self::client()->maintenanceWindows->get($windows[0]->id));
    }

    public function testReportsAnUnknownWindow(): void
    {
        self::windows();

        try {
            self::client()->maintenanceWindows->get(999999999);
            self::fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            // {"message":"Maintenance window not found","code":"000-004"} (verified live).
            self::assertSame('000-004', $e->errorCode);
        }
    }

    public function testEveryWindowOfAMonitorIsListed(): void
    {
        $ids = array_map(static fn(MaintenanceWindow $window): int => $window->id, self::windows());
        $unlisted = [];

        foreach (self::client()->monitors->all() as $monitor) {
            foreach ($monitor->maintenanceWindows as $window) {
                if (!\in_array($window->id, $ids, true)) {
                    $unlisted[] = "window {$window->id} of monitor {$monitor->id}";
                }
            }
        }

        self::assertSame([], $unlisted);
    }

    private static function client(): UptimeRobot
    {
        return new UptimeRobot(self::apiKey());
    }

    /**
     * @return list<MaintenanceWindow>
     */
    private static function windows(): array
    {
        try {
            self::$windows ??= iterator_to_array(self::client()->maintenanceWindows->all(), false);
        } catch (ForbiddenException $e) {
            if ($e->errorCode === '000-003') {
                self::markTestSkipped('The plan of the account does not include maintenance windows.');
            }

            throw $e;
        }

        return self::$windows;
    }
}
