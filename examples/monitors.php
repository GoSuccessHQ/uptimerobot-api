<?php

declare(strict_types=1);

/**
 * List the monitors with their settings, page by page and all at once, and
 * the monitors that are down. Read-only; examples/monitor-stats.php shows
 * their statistics.
 *
 *   UPTIMEROBOT_API_KEY=your-api-key php examples/monitors.php
 */

use GoSuccess\UptimeRobot\Enum\MonitorStatus;
use GoSuccess\UptimeRobot\Enum\Region;
use GoSuccess\UptimeRobot\Model\MonitorTag;
use GoSuccess\UptimeRobot\UptimeRobot;

/** @var UptimeRobot $uptimeRobot */
$uptimeRobot = require __DIR__ . '/bootstrap.php';

// One page: list() returns the items and the cursor of the next page, the ID
// of the last monitor on it.
$page = $uptimeRobot->monitors->list(limit: 5);
printf("First page: %d monitor(s)%s\n\n", count($page->items), $page->hasMore ? ", next cursor {$page->next}" : ', the only page');

// Every monitor: all() fetches the pages while the loop runs, 200 at a time.
foreach ($uptimeRobot->monitors->all() as $monitor) {
    printf(
        "#%d %-9s %-7s %s\n",
        $monitor->id,
        $monitor->type->value ?? '?',
        // STARTED until the first check after a start.
        $monitor->status->value ?? '?',
        $monitor->friendlyName,
    );

    printf(
        "  checks %s every %d s from %s\n",
        // A heartbeat monitor reports the token of its heartbeat URL here.
        $monitor->url,
        $monitor->interval,
        implode(', ', array_map(static fn(Region $region): string => $region->name, $monitor->regionalData->region)) ?: '?',
    );

    printf(
        "  %d alert contact(s), group %s, tags: %s\n",
        count($monitor->assignedAlertContacts),
        // 0 stands for no group.
        $monitor->groupId ?: 'none',
        implode(', ', array_map(static fn(MonitorTag $tag): string => $tag->name, $monitor->tags)) ?: 'none',
    );

    if ($monitor->lastIncident !== null) {
        printf(
            "  last incident %s: %s, %s\n",
            $monitor->lastIncident->startedAt?->format('Y-m-d H:i') ?? '?',
            $monitor->lastIncident->reason,
            $monitor->lastIncident->status,
        );
    }
}

// Filters combine with AND; the statuses match if any of them applies.
$down = $uptimeRobot->monitors->all(status: [MonitorStatus::Down, MonitorStatus::LooksDown]);
$count = 0;

foreach ($down as $monitor) {
    if ($count++ === 0) {
        echo "\nDown:\n";
    }

    printf("  #%d %s for %d s\n", $monitor->id, $monitor->friendlyName, $monitor->currentStateDuration);
}

if ($count === 0) {
    echo "\nNo monitor is down.\n";
}
