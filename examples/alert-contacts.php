<?php

declare(strict_types=1);

/**
 * List the personal alert contacts with the monitors that alert each of them,
 * and warn about contacts that receive nothing. Read-only; the addresses are
 * not printed.
 *
 *   UPTIMEROBOT_API_KEY=your-api-key php examples/alert-contacts.php
 */

use GoSuccess\UptimeRobot\Enum\NotificationEvent;
use GoSuccess\UptimeRobot\UptimeRobot;

/** @var UptimeRobot $uptimeRobot */
$uptimeRobot = require __DIR__ . '/bootstrap.php';

// A monitor names its contacts by ID, with the minutes to wait before the
// first alert (threshold) and between repeated ones (recurrence).
$monitorsByContact = [];

foreach ($uptimeRobot->monitors->all() as $monitor) {
    foreach ($monitor->assignedAlertContacts as $assigned) {
        $monitorsByContact[$assigned->alertContactId][] = $monitor->friendlyName;
    }
}

foreach ($uptimeRobot->alertContacts->all() as $contact) {
    $monitors = $monitorsByContact[$contact->id] ?? [];

    printf(
        "#%d %s (%s, %s, alerts for %s): %d monitor(s)\n",
        $contact->id,
        $contact->friendlyName ?? '(unnamed)',
        $contact->type,
        $contact->status,
        $contact->enableNotificationsFor->value ?? '?',
        count($monitors),
    );

    foreach ($monitors as $name) {
        printf("  - %s\n", $name);
    }

    // Only active contacts deliver alerts, and None turns them off.
    if ($monitors !== [] && ($contact->status !== 'Active' || $contact->enableNotificationsFor === NotificationEvent::None)) {
        print "  ! this contact receives no alerts\n";
    }
}
