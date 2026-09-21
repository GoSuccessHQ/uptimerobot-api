<?php

declare(strict_types=1);

/**
 * List the incidents of the last 30 days with their root cause, and the
 * activity log of the newest one. Read-only.
 *
 *   UPTIMEROBOT_API_KEY=your-api-key php examples/incidents.php
 */

use GoSuccess\UptimeRobot\Model\CommentActivity;
use GoSuccess\UptimeRobot\Model\NotificationActivity;
use GoSuccess\UptimeRobot\Model\StatusUpdateActivity;
use GoSuccess\UptimeRobot\Model\UnknownActivityLogEntry;
use GoSuccess\UptimeRobot\UptimeRobot;

/** @var UptimeRobot $uptimeRobot */
$uptimeRobot = require __DIR__ . '/bootstrap.php';

$newest = null;

// Newest first. Incident IDs are strings: they exceed the integers a JSON
// number holds exactly.
foreach ($uptimeRobot->incidents->all(startedAfter: new DateTimeImmutable('-30 days')) as $incident) {
    $newest ??= $incident;

    printf(
        "%s  %-12s %-9s %s: %s (cause %s, %s)\n",
        $incident->startedAt?->format('Y-m-d H:i') ?? '?',
        $incident->type,
        $incident->status,
        $incident->monitor->friendlyName ?? "monitor #{$incident->monitor->id}",
        $incident->reason,
        $incident->cause ?? '?',
        $incident->duration === null ? 'ongoing' : "{$incident->duration} s",
    );
}

if ($newest === null) {
    echo "No incidents in the last 30 days.\n";

    return;
}

$rootCause = $uptimeRobot->incidents->get($newest->id)->rootCause;

printf("\nIncident %s\n", $newest->id);

if ($rootCause !== null && $rootCause->url !== '') {
    printf("  Check:    %s\n", $rootCause->url);
    printf("  Response: %s\n", $rootCause->httpResponseCode ?? 'none');
}

foreach ($uptimeRobot->incidents->activityLog($newest->id) as $entry) {
    // Every entry has a date and a region; the rest depends on its kind.
    $line = match (true) {
        $entry instanceof StatusUpdateActivity => sprintf(
            '%s: %s%s',
            $entry->alertLogType,
            $entry->reason,
            $entry->remoteNode === null ? '' : " (from {$entry->remoteNode->city}, {$entry->remoteNode->country})",
        ),
        $entry instanceof NotificationActivity => sprintf(
            '%s alert to %s: %s',
            $entry->notificationType,
            $entry->sentToFullName,
            $entry->notificationStatus->value ?? '?',
        ),
        $entry instanceof CommentActivity => "comment by {$entry->commentFullName}",
        $entry instanceof UnknownActivityLogEntry => "entry of the type {$entry->type}",
        default => 'unknown entry',
    };

    printf("  %s %s\n", $entry->date?->format('H:i:s') ?? '?', $line);
}
