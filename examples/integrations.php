<?php

declare(strict_types=1);

/**
 * List the integrations of the account, or with --contacts also the personal
 * alert contacts, which the API reports in the same shape. Read-only; the
 * destinations are not printed.
 *
 *   UPTIMEROBOT_API_KEY=your-api-key php examples/integrations.php [--contacts]
 *
 * Creating one takes the model of its type, which sends the type for you:
 *
 *   $uptimeRobot->integrations->create(new SlackIntegrationCreate(
 *       webhookUrl: 'https://hooks.slack.com/services/...',
 *       customValue: '#alerts',
 *       friendlyName: 'Ops channel',
 *       enableNotificationsFor: NotificationEvent::Down,
 *   ));
 */

use GoSuccess\UptimeRobot\UptimeRobot;

/** @var UptimeRobot $uptimeRobot */
$uptimeRobot = require __DIR__ . '/bootstrap.php';

$arguments = $_SERVER['argv'] ?? [];
$withContacts = is_array($arguments) && in_array('--contacts', $arguments, true);
$count = 0;

foreach ($uptimeRobot->integrations->all(includeOrgMembers: $withContacts ?: null) as $integration) {
    printf(
        "#%d %s: %s, %s, alerts for %s\n",
        $integration->id,
        $integration->friendlyName ?? '(unnamed)',
        $integration->type,
        $integration->status,
        $integration->enableNotificationsFor->value ?? '?',
    );
    ++$count;
}

if ($count === 0) {
    print $withContacts ? "No integrations or contacts.\n" : "No integrations; --contacts lists the personal alert contacts too.\n";
}
