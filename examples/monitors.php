<?php

declare(strict_types=1);

/**
 * List the monitors with their status, the uptime of the account over the
 * last 30 days and the response times of the first monitor that is up.
 * Read-only.
 *
 *   UPTIMEROBOT_API_KEY=your-api-key php examples/monitors.php
 */

use GoSuccess\UptimeRobot\Enum\MonitorStatus;
use GoSuccess\UptimeRobot\Enum\UptimeTimeFrame;
use GoSuccess\UptimeRobot\UptimeRobot;

/** @var UptimeRobot $uptimeRobot */
$uptimeRobot = require __DIR__ . '/bootstrap.php';

$up = null;

foreach ($uptimeRobot->monitors->all() as $monitor) {
    printf(
        "#%d %-10s %-9s %s (every %d s)\n",
        $monitor->id,
        $monitor->type->value ?? '?',
        $monitor->status->value ?? '?',
        $monitor->friendlyName,
        $monitor->interval,
    );

    if ($up === null && $monitor->status === MonitorStatus::Up) {
        $up = $monitor;
    }
}

// The account-wide uptime is a fraction from 0 to 1.
$stats = $uptimeRobot->monitors->uptimeStats(UptimeTimeFrame::Days30, logLimit: 5);
printf("\nLast 30 days: %.3f %% uptime, %d incidents\n", $stats->overallUptime * 100, $stats->totalIncidents);

foreach ($stats->logs as $downtime) {
    printf("  %s down for %d s\n", $downtime->datetime?->format('Y-m-d H:i') ?? '?', $downtime->duration ?? 0);
}

if ($up !== null) {
    // The uptime of a single monitor is a percentage already. The response
    // times need both ends of the range, or neither for the last 24 hours.
    $from = new DateTimeImmutable('-7 days');
    $to = new DateTimeImmutable();
    $uptime = $uptimeRobot->monitors->uptime($up->id, from: $from, to: $to);
    $responseTimes = $uptimeRobot->monitors->responseTimeStats($up->id, from: $from, to: $to);

    printf(
        "\n%s, last 7 days: %.3f %% uptime, response times %d / %d / %d ms (min / avg / max)\n",
        $up->friendlyName,
        $uptime->uptime,
        $responseTimes->summary->min ?? 0,
        $responseTimes->summary->avg ?? 0,
        $responseTimes->summary->max ?? 0,
    );
}
