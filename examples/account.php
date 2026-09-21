<?php

declare(strict_types=1);

/**
 * Show the account behind the API key: its plan, alert contacts, tags and
 * storm protection settings. Read-only.
 *
 *   UPTIMEROBOT_API_KEY=your-api-key php examples/account.php
 */

use GoSuccess\UptimeRobot\UptimeRobot;

/** @var UptimeRobot $uptimeRobot */
$uptimeRobot = require __DIR__ . '/bootstrap.php';

$user = $uptimeRobot->user->me();
$subscription = $user->activeSubscription;

printf("%s <%s>\n", $user->fullName, $user->email);
printf('Plan: %s, %d of %d monitors', $subscription->plan, $user->monitorsCount, $user->monitorLimit);
echo $subscription->expirationDate === null ? "\n" : ', renews ' . $subscription->expirationDate->format('Y-m-d') . "\n";

echo "\nAlert contacts:\n";

foreach ($uptimeRobot->user->alertContacts() as $contact) {
    printf("  #%d %s %s (%s)\n", $contact->id, $contact->type, $contact->friendlyName ?? $contact->value ?? '', $contact->status);
}

echo "\nTags:\n";

foreach ($uptimeRobot->tags->all() as $tag) {
    printf("  #%d %s\n", $tag->id, $tag->name);
}

$storm = $uptimeRobot->stormProtection->get();
printf(
    "\nStorm protection: %s, groups alerts from %d %s within %d minutes\n",
    $storm->isEnabled ? 'on' : 'off',
    $storm->thresholdValue,
    $storm->thresholdType->value ?? '?',
    $storm->windowMinutes,
);

if ($uptimeRobot->rateLimit !== null) {
    printf("\n%d of %d requests left in the current window\n", $uptimeRobot->rateLimit->remaining, $uptimeRobot->rateLimit->limit);
}
