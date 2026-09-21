<?php

declare(strict_types=1);

/**
 * List the monitor groups with the monitors in each, and the monitors in no
 * group. Read-only.
 *
 *   UPTIMEROBOT_API_KEY=your-api-key php examples/monitor-groups.php
 */

use GoSuccess\UptimeRobot\UptimeRobot;

/** @var UptimeRobot $uptimeRobot */
$uptimeRobot = require __DIR__ . '/bootstrap.php';

// A group carries neither its monitors nor their number, but every monitor
// names its group; 0 stands for none.
$byGroup = [];

foreach ($uptimeRobot->monitors->all() as $monitor) {
    $byGroup[$monitor->groupId ?? 0][] = $monitor->friendlyName;
}

$groups = [0 => '(no group)'];

foreach ($uptimeRobot->monitorGroups->all() as $group) {
    $groups[$group->id] = sprintf('%s (#%d, created %s)', $group->name, $group->id, $group->createdAt?->format('Y-m-d') ?? '?');
}

foreach ($groups as $id => $label) {
    $monitors = $byGroup[$id] ?? [];
    printf("%s: %d monitor(s)\n", $label, count($monitors));

    foreach ($monitors as $name) {
        printf("  - %s\n", $name);
    }
}
