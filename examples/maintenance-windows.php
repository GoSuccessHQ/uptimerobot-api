<?php

declare(strict_types=1);

/**
 * List the maintenance windows with their schedules. Read-only.
 *
 *   UPTIMEROBOT_API_KEY=your-api-key php examples/maintenance-windows.php
 */

use GoSuccess\UptimeRobot\Enum\MaintenanceWindowInterval;
use GoSuccess\UptimeRobot\UptimeRobot;

/** @var UptimeRobot $uptimeRobot */
$uptimeRobot = require __DIR__ . '/bootstrap.php';

// Weekdays as the official Terraform provider numbers them; the API does not
// document its numbering.
$weekdays = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
$count = 0;

foreach ($uptimeRobot->maintenanceWindows->all() as $window) {
    $days = array_map(
        static fn(int $day): string => match ($window->interval) {
            MaintenanceWindowInterval::Weekly => $weekdays[$day] ?? "day {$day}",
            MaintenanceWindowInterval::Monthly => $day === -1 ? 'last day' : "day {$day}",
            default => (string) $day,
        },
        $window->days,
    );

    printf(
        "#%d %s: %s%s at %s%s for %d min, %s, %s\n",
        $window->id,
        $window->name,
        $window->interval->value ?? '?',
        $days === [] ? '' : ' on ' . implode(', ', $days),
        $window->time,
        $window->date === null ? '' : " from {$window->date}",
        $window->duration,
        $window->autoAddMonitors ? 'all monitors' : count($window->monitorIds) . ' monitor(s)',
        $window->status->value ?? '?',
    );
    ++$count;
}

if ($count === 0) {
    echo "No maintenance windows.\n";
}
