<?php

declare(strict_types=1);

/**
 * Show the uptime of the account over the last 30 days and over a range of
 * your own, and the uptime and response times of the first monitor that is
 * up, also by region. Read-only.
 *
 *   UPTIMEROBOT_API_KEY=your-api-key php examples/monitor-stats.php
 */

use GoSuccess\UptimeRobot\Enum\MonitorStatus;
use GoSuccess\UptimeRobot\Enum\UptimeTimeFrame;
use GoSuccess\UptimeRobot\UptimeRobot;

/** @var UptimeRobot $uptimeRobot */
$uptimeRobot = require __DIR__ . '/bootstrap.php';

// All monitors of the account. The overall uptime is a fraction from 0 to 1,
// and the downtimes come newest first.
$stats = $uptimeRobot->monitors->uptimeStats(UptimeTimeFrame::Days30, logLimit: 5);
printf(
    "Last 30 days: %.3f %% uptime, %d incident(s) on %d monitor(s)\n",
    $stats->overallUptime * 100,
    $stats->totalIncidents,
    $stats->affectedMonitors,
);

foreach ($stats->logs as $downtime) {
    printf("  %s down for %d s\n", $downtime->datetime?->format('Y-m-d H:i') ?? '?', $downtime->duration ?? 0);
}

// A range of your own takes Custom with both ends, sent as Unix seconds.
$week = $uptimeRobot->monitors->uptimeStats(
    UptimeTimeFrame::Custom,
    start: new DateTimeImmutable('monday last week midnight'),
    end: new DateTimeImmutable('monday this week midnight'),
);
printf("Last calendar week: %.3f %% uptime\n", $week->overallUptime * 100);

// The first monitor that is up; the loop ends after the first page.
$monitor = null;

foreach ($uptimeRobot->monitors->all(limit: 1, status: [MonitorStatus::Up]) as $monitor) {
    break;
}

if ($monitor === null) {
    echo "\nNo monitor is up.\n";

    return;
}

// The uptime of a single monitor is a percentage already. Pass the response
// time range with both ends, or neither for the last 24 hours; at most 90 days.
$from = new DateTimeImmutable('-7 days');
$to = new DateTimeImmutable();
$uptime = $uptimeRobot->monitors->uptime($monitor->id, from: $from, to: $to);
printf(
    "\n%s, last 7 days: %.3f %% uptime, %d incident(s), %d s down\n",
    $monitor->friendlyName,
    $uptime->uptime,
    $uptime->incidentCount,
    $uptime->totalDowntimeSeconds,
);

// Times are in milliseconds; the time series summarizes longer intervals the
// longer the range is.
$responseTimes = $uptimeRobot->monitors->responseTimeStats($monitor->id, includeTimeSeries: true);
printf(
    "Last 24 hours: %d / %d / %d ms (min / avg / max) over %d data points\n",
    $responseTimes->summary->min ?? 0,
    $responseTimes->summary->avg ?? 0,
    $responseTimes->summary->max ?? 0,
    $responseTimes->dataPoints,
);

foreach (array_slice($responseTimes->timeSeries, -3) as $point) {
    printf("  %s %d ms\n", $point->timestamp?->format('H:i') ?? '?', $point->value);
}

// The regions the monitor is not checked from are null.
$byRegion = $uptimeRobot->monitors->responseTimeStatsByRegion($monitor->id);

foreach (['North America' => $byRegion->na, 'Europe' => $byRegion->eu, 'Asia' => $byRegion->as, 'Oceania' => $byRegion->oc] as $region => $regionStats) {
    if ($regionStats !== null) {
        printf("  %s: %d ms on average\n", $region, $regionStats->summary->avg ?? 0);
    }
}
