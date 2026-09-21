<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Integration;

use DateTimeImmutable;
use GoSuccess\UptimeRobot\Enum\MonitorStatus;
use GoSuccess\UptimeRobot\Enum\MonitorType;
use GoSuccess\UptimeRobot\Enum\Region;
use GoSuccess\UptimeRobot\Enum\UptimeTimeFrame;
use GoSuccess\UptimeRobot\Exception\ForbiddenException;
use GoSuccess\UptimeRobot\Model\Monitor;
use GoSuccess\UptimeRobot\UptimeRobot;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Reads the monitors of the account, filters them and reads their statistics;
 * nothing is changed.
 */
#[CoversNothing]
final class MonitorsReadOnlyTest extends IntegrationTestCase
{
    /** @var list<Monitor>|null Every monitor, read once for all tests. */
    private static ?array $monitors = null;

    public function testIteratesOverAllMonitorsAndReadsOnePage(): void
    {
        $monitors = self::monitors();
        $ids = array_map(static fn(Monitor $monitor): int => $monitor->id, $monitors);

        self::assertSame($ids, array_values(array_unique($ids)));

        $page = self::client()->monitors->list(limit: 1);

        self::assertCount(1, $page->items);
        self::assertSame($ids[0], $page->items[0]->id);
        // A full page reports the next cursor, the ID of its last monitor (verified live).
        self::assertSame($ids[0], $page->next);
    }

    public function testReadsAMonitorAsTheListDoes(): void
    {
        $listed = self::monitors()[0];
        $monitor = self::client()->monitors->get($listed->id);

        self::assertSame($listed->friendlyName, $monitor->friendlyName);
        self::assertSame($listed->url, $monitor->url);
        self::assertSame($listed->type, $monitor->type);
        self::assertEquals($listed->createDateTime, $monitor->createDateTime);

        foreach (self::monitors() as $each) {
            // Every value the API sends is one the enums know.
            self::assertNotNull($each->type, "Unknown type of monitor {$each->id}.");
            self::assertNotNull($each->status, "Unknown status of monitor {$each->id}.");
            self::assertNotNull($each->authType, "Unknown authType of monitor {$each->id}.");
            self::assertNotNull($each->keywordCaseType, "Unknown keywordCaseType of monitor {$each->id}.");
            self::assertNotSame([], $each->regionalData->region, "Monitor {$each->id} has no regions.");
            self::assertNotNull($each->createDateTime);
        }
    }

    public function testFiltersTheList(): void
    {
        $first = self::monitors()[0];
        $monitors = self::client()->monitors;
        $status = $first->status ?? self::fail('The first monitor has no status.');

        $byStatus = $monitors->list(status: [$status])->items;
        $byName = $monitors->list(name: strtoupper($first->friendlyName))->items;
        $byGroup = $monitors->list(groupId: $first->groupId ?? 0)->items;

        self::assertNotSame([], $byStatus);

        foreach ($byStatus as $monitor) {
            self::assertSame($status, $monitor->status);
        }

        // The name filter is a case-insensitive partial match (verified live).
        self::assertContains($first->id, array_map(static fn(Monitor $monitor): int => $monitor->id, $byName));
        self::assertContains($first->id, array_map(static fn(Monitor $monitor): int => $monitor->id, $byGroup));
        self::assertSame([], $monitors->list(tags: ['gosuccess-api-client-no-such-tag'], customField: ['gosuccess-api-client:none'])->items);
    }

    public function testReadsTheUptimeStatisticsOfTheAccount(): void
    {
        $monitors = self::client()->monitors;

        $day = $monitors->uptimeStats(UptimeTimeFrame::Day);
        $week = $monitors->uptimeStats(UptimeTimeFrame::Custom, start: new DateTimeImmutable('-7 days'), end: new DateTimeImmutable(), logLimit: 2);

        foreach ([$day, $week] as $stats) {
            // A fraction, unlike the percentage of a single monitor (verified live).
            self::assertGreaterThanOrEqual(0.0, $stats->overallUptime);
            self::assertLessThanOrEqual(1.0, $stats->overallUptime);
            self::assertGreaterThan(0, $stats->totalTimeWithoutIncidents);
        }

        self::assertLessThanOrEqual(2, \count($week->logs));
    }

    public function testReadsTheStatisticsOfAMonitor(): void
    {
        $monitor = null;

        foreach (self::monitors() as $candidate) {
            if ($candidate->status === MonitorStatus::Up && \in_array($candidate->type, [MonitorType::Http, MonitorType::Keyword, MonitorType::Api], true)) {
                $monitor = $candidate;

                break;
            }
        }

        if ($monitor === null) {
            self::markTestSkipped('The account has no HTTP, keyword or API monitor that is up.');
        }

        $monitors = self::client()->monitors;
        $from = new DateTimeImmutable('-2 days');
        $to = new DateTimeImmutable('-1 day');

        $uptime = $monitors->uptime($monitor->id, from: $from, to: $to);
        $responseTimes = $monitors->responseTimeStats($monitor->id, from: $from, to: $to, includeTimeSeries: true);
        $byRegion = $monitors->responseTimeStatsByRegion($monitor->id);

        // A percentage (verified live).
        self::assertGreaterThanOrEqual(0.0, $uptime->uptime);
        self::assertLessThanOrEqual(100.0, $uptime->uptime);
        // The range is echoed as sent, to the millisecond.
        self::assertSame($from->format('U.v'), $uptime->from?->format('U.v'));
        self::assertSame($to->format('U.v'), $uptime->to?->format('U.v'));
        self::assertSame($responseTimes->dataPoints, \count($responseTimes->timeSeries));
        self::assertNotNull($byRegion->all);

        foreach ($monitor->regionalData->region as $region) {
            $stats = match ($region) {
                Region::NorthAmerica => $byRegion->na,
                Region::Europe => $byRegion->eu,
                Region::Asia => $byRegion->as,
                Region::Oceania => $byRegion->oc,
            };

            self::assertNotNull($stats, "No response times of the region {$region->value}.");
        }
    }

    private static function client(): UptimeRobot
    {
        return new UptimeRobot(self::apiKey());
    }

    /**
     * @return non-empty-list<Monitor>
     */
    private static function monitors(): array
    {
        try {
            self::$monitors ??= iterator_to_array(self::client()->monitors->all(), false);
        } catch (ForbiddenException $e) {
            if ($e->errorCode === '000-003') {
                self::markTestSkipped('The plan of the account does not include monitors.');
            }

            throw $e;
        }

        if (self::$monitors === []) {
            self::markTestSkipped('The account has no monitors.');
        }

        return self::$monitors;
    }
}
