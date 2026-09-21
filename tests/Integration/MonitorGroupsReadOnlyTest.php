<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Integration;

use GoSuccess\UptimeRobot\Exception\ForbiddenException;
use GoSuccess\UptimeRobot\Exception\NotFoundException;
use GoSuccess\UptimeRobot\Model\MonitorGroup;
use GoSuccess\UptimeRobot\UptimeRobot;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Reads the monitor groups of the account and checks the groupId of its
 * monitors against them; nothing is changed.
 */
#[CoversNothing]
final class MonitorGroupsReadOnlyTest extends IntegrationTestCase
{
    /** @var list<MonitorGroup>|null Every group, read once for all tests. */
    private static ?array $monitorGroups = null;

    public function testIteratesOverAllGroupsAndContinuesAfterACursor(): void
    {
        $groups = self::monitorGroups();
        $ids = array_map(static fn(MonitorGroup $group): int => $group->id, $groups);

        self::assertSame($ids, array_values(array_unique($ids)));

        foreach ($groups as $group) {
            self::assertGreaterThan(0, $group->id);
            self::assertNotSame('', $group->name);
            self::assertNotNull($group->createdAt);
            self::assertNotNull($group->updatedAt);
        }

        // The cursor is the ID of the last group of the previous page: the
        // groups after the first one follow (verified live).
        $rest = self::client()->monitorGroups->list(cursor: $ids[0])->items;
        $restIds = array_map(static fn(MonitorGroup $group): int => $group->id, $rest);

        self::assertNotContains($ids[0], $restIds);
        self::assertSame(\array_slice($ids, 1, \count($restIds)), $restIds);
    }

    public function testReadsAGroupAsTheListDoes(): void
    {
        $listed = self::monitorGroups()[0];
        $group = self::client()->monitorGroups->get($listed->id);

        self::assertEquals($listed, $group);
    }

    public function testFindsNoGroupForTheMonitorsInNone(): void
    {
        self::monitorGroups();

        try {
            self::client()->monitorGroups->get(0);
            self::fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            self::assertSame('000-004', $e->errorCode);
        }
    }

    public function testEveryGroupOfAMonitorIsListed(): void
    {
        $ids = array_map(static fn(MonitorGroup $group): int => $group->id, self::monitorGroups());

        foreach (self::client()->monitors->all() as $monitor) {
            if (($monitor->groupId ?? 0) !== 0) {
                self::assertContains($monitor->groupId, $ids, "The group of monitor {$monitor->id} is not listed.");
            }
        }
    }

    private static function client(): UptimeRobot
    {
        return new UptimeRobot(self::apiKey());
    }

    /**
     * @return non-empty-list<MonitorGroup>
     */
    private static function monitorGroups(): array
    {
        try {
            self::$monitorGroups ??= iterator_to_array(self::client()->monitorGroups->all(), false);
        } catch (ForbiddenException $e) {
            if ($e->errorCode === '000-003') {
                self::markTestSkipped('The plan of the account does not include monitor groups.');
            }

            throw $e;
        }

        if (self::$monitorGroups === []) {
            self::markTestSkipped('The account has no monitor groups.');
        }

        return self::$monitorGroups;
    }
}
