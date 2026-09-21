<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Integration;

use GoSuccess\UptimeRobot\Exception\ForbiddenException;
use GoSuccess\UptimeRobot\Exception\NotFoundException;
use GoSuccess\UptimeRobot\Model\CommentActivity;
use GoSuccess\UptimeRobot\Model\IncidentSummary;
use GoSuccess\UptimeRobot\Model\NotificationActivity;
use GoSuccess\UptimeRobot\Model\StatusUpdateActivity;
use GoSuccess\UptimeRobot\Model\UnknownActivityLogEntry;
use GoSuccess\UptimeRobot\UptimeRobot;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Reads the incidents of the account with their filters, root cause, activity
 * log and sent alerts; nothing is changed. The checks need at least one
 * incident.
 */
#[CoversNothing]
final class IncidentsReadOnlyTest extends IntegrationTestCase
{
    /** @var list<IncidentSummary>|null Every incident, read once for all tests. */
    private static ?array $incidents = null;

    public function testListsTheIncidentsNewestFirst(): void
    {
        $incidents = self::incidents();
        $ids = self::ids($incidents);

        self::assertSame($ids, array_values(array_unique($ids)));

        $previous = null;

        foreach ($incidents as $incident) {
            // Strings of digits, beyond the integers a JSON number holds exactly.
            self::assertMatchesRegularExpression('/^\d+$/', $incident->id);
            self::assertNotSame('', $incident->status);
            self::assertNotSame('', $incident->type);
            self::assertGreaterThan(0, $incident->monitor->id);
            self::assertNotNull($incident->startedAt);

            if ($previous !== null) {
                self::assertLessThanOrEqual($previous, $incident->startedAt, "Incident {$incident->id} is out of order.");
            }

            $previous = $incident->startedAt;

            if ($incident->resolvedAt !== null && $incident->duration !== null) {
                // The duration is the time from start to resolution in seconds.
                self::assertEqualsWithDelta($incident->resolvedAt->getTimestamp() - $incident->startedAt->getTimestamp(), $incident->duration, 1);
            }
        }
    }

    public function testContinuesAfterTheCursor(): void
    {
        $ids = self::ids(self::incidents());

        // The cursor is the ID of the last incident of the previous page: the
        // incidents that started before it follow (verified live).
        $rest = self::ids(self::client()->incidents->list(cursor: $ids[0])->items);

        self::assertNotContains($ids[0], $rest);
        self::assertSame(\array_slice($ids, 1, \count($rest)), $rest);
    }

    public function testFiltersByMonitor(): void
    {
        $monitor = self::incidents()[0]->monitor;
        $expected = self::ids(array_values(array_filter(
            self::incidents(),
            static fn(IncidentSummary $incident): bool => $incident->monitor->id === $monitor->id,
        )));

        self::assertSame($expected, self::ids(iterator_to_array(self::client()->incidents->all(monitorId: $monitor->id), false)));

        if ($monitor->friendlyName === null || $monitor->friendlyName === '') {
            return;
        }

        // A part of the name matches, whatever the case of the letters.
        $part = mb_strtoupper(mb_substr($monitor->friendlyName, 0, 3));
        $matches = iterator_to_array(self::client()->incidents->all(monitorName: $part), false);

        self::assertContains(self::incidents()[0]->id, self::ids($matches));

        foreach ($matches as $incident) {
            self::assertStringContainsStringIgnoringCase($part, (string) $incident->monitor->friendlyName);
        }
    }

    public function testFiltersByStart(): void
    {
        $incidents = self::incidents();
        $middle = $incidents[intdiv(\count($incidents), 2)]->startedAt;
        self::assertNotNull($middle);

        $after = iterator_to_array(self::client()->incidents->all(startedAfter: $middle), false);
        $before = iterator_to_array(self::client()->incidents->all(startedBefore: $middle), false);

        // The dates are sent in whole seconds.
        $from = $middle->setTime((int) $middle->format('H'), (int) $middle->format('i'), (int) $middle->format('s'));
        $to = $from->modify('+1 second');

        foreach ($after as $incident) {
            self::assertGreaterThanOrEqual($from, $incident->startedAt);
        }

        foreach ($before as $incident) {
            self::assertLessThanOrEqual($to, $incident->startedAt);
        }

        // The newest incident started after the middle one, the oldest before it.
        $newest = $incidents[0];
        $oldest = $incidents[\count($incidents) - 1];

        if ($newest->startedAt > $to) {
            self::assertContains($newest->id, self::ids($after));
        }

        if ($oldest->startedAt < $from) {
            self::assertContains($oldest->id, self::ids($before));
        }
    }

    public function testReadsAnIncidentAsTheListDoes(): void
    {
        $listed = self::incidents()[0];
        $incident = self::client()->incidents->get($listed->id);

        self::assertSame($listed->id, $incident->id);
        self::assertSame($listed->status, $incident->status);
        self::assertSame($listed->cause, $incident->cause);
        self::assertSame($listed->reason, $incident->reason);
        self::assertSame($listed->duration, $incident->duration);
        self::assertEquals($listed->startedAt, $incident->startedAt);
        self::assertEquals($listed->resolvedAt, $incident->resolvedAt);
        // Null according to the specification, but present on every incident
        // read live.
        self::assertNotNull($incident->rootCause);
    }

    public function testReadsTheActivityLogNewestFirst(): void
    {
        $entries = self::client()->incidents->activityLog(self::incidents()[0]->id);

        self::assertNotSame([], $entries);

        $previous = null;

        foreach ($entries as $entry) {
            if ($entry instanceof UnknownActivityLogEntry) {
                self::fail("An entry of the unknown type {$entry->type}.");
            }

            self::assertTrue($entry instanceof StatusUpdateActivity || $entry instanceof NotificationActivity || $entry instanceof CommentActivity);
            // Declared by ActivityLogEntry, so no instanceof is needed.
            self::assertNotNull($entry->date);

            if ($previous !== null) {
                self::assertLessThanOrEqual($previous, $entry->date);
            }

            $previous = $entry->date;

            if ($entry instanceof StatusUpdateActivity) {
                // As the docblocks say (verified live).
                if ($entry->alertLogType === 'Up') {
                    self::assertNull($entry->remoteNode);
                }

                if ($entry->alertLogType !== 'Slow') {
                    self::assertNull($entry->responseTime);
                }
            }
        }
    }

    public function testReadsTheSentAlertsOldestFirst(): void
    {
        // Up to the first incident with alerts, which spares the rate limit.
        foreach (\array_slice(self::incidents(), 0, 5) as $incident) {
            $alerts = self::client()->incidents->alerts($incident->id);
            $timestamps = [];

            foreach ($alerts as $alert) {
                // Every value the API sends is one the enum knows.
                self::assertNotNull($alert->status, "Unknown status of an alert of incident {$incident->id}.");
                self::assertNotNull($alert->timestamp);
                self::assertNotSame('', $alert->channelType);
                $timestamps[] = $alert->timestamp;
            }

            $sorted = $timestamps;
            sort($sorted);
            self::assertEquals($sorted, $timestamps, "The alerts of incident {$incident->id} are out of order.");

            if ($alerts !== []) {
                break;
            }
        }
    }

    public function testReportsAnUnknownIncident(): void
    {
        self::incidents();

        try {
            self::client()->incidents->get('1');
            self::fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            // {"message":"Incident 1 not found","code":"000-004"} (verified live).
            self::assertSame('000-004', $e->errorCode);
        }
    }

    private static function client(): UptimeRobot
    {
        return new UptimeRobot(self::apiKey());
    }

    /**
     * @param list<IncidentSummary> $incidents
     *
     * @return list<string>
     */
    private static function ids(array $incidents): array
    {
        return array_map(static fn(IncidentSummary $incident): string => $incident->id, $incidents);
    }

    /**
     * @return non-empty-list<IncidentSummary>
     */
    private static function incidents(): array
    {
        try {
            self::$incidents ??= iterator_to_array(self::client()->incidents->all(), false);
        } catch (ForbiddenException $e) {
            if ($e->errorCode === '000-003') {
                self::markTestSkipped('The plan of the account does not include incidents.');
            }

            throw $e;
        }

        if (self::$incidents === []) {
            self::markTestSkipped('The account has no incidents.');
        }

        return self::$incidents;
    }
}
