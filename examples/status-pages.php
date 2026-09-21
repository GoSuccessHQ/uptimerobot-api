<?php

declare(strict_types=1);

/**
 * List the status pages with their monitors, design and announcements.
 * Read-only.
 *
 *   UPTIMEROBOT_API_KEY=your-api-key php examples/status-pages.php
 *
 * Creating a page with a logo would look like this (not run here):
 *
 *   $page = $uptimeRobot->statusPages->create(
 *       new StatusPageCreate(friendlyName: 'Status', monitorIds: [0], status: StatusPageStatus::Enabled),
 *       logo: FileUpload::fromPath(__DIR__ . '/logo.png'),
 *   );
 */

use GoSuccess\UptimeRobot\Exception\ForbiddenException;
use GoSuccess\UptimeRobot\UptimeRobot;

/** @var UptimeRobot $uptimeRobot */
$uptimeRobot = require __DIR__ . '/bootstrap.php';

$count = 0;

foreach ($uptimeRobot->statusPages->all() as $page) {
    $design = $page->customSettings?->page;

    printf(
        "#%d %s (%s, key %s): %s%s%s\n",
        $page->id,
        $page->friendlyName,
        $page->status,
        $page->urlKey,
        // [0] stands for every monitor of the account.
        $page->monitorIds === [0] ? 'all monitors' : count($page->monitorIds) . ' monitor(s)',
        $page->isPasswordSet ? ', password protected' : '',
        $design === null ? '' : sprintf(', %s theme', $design->theme->value ?? '?'),
    );
    ++$count;

    try {
        foreach ($uptimeRobot->announcements->all($page->id) as $announcement) {
            printf(
                "  announcement #%d %s [%s, %s]%s\n",
                $announcement->id,
                $announcement->title ?? '(untitled)',
                $announcement->type ?? '?',
                $announcement->status ?? '?',
                $page->pinnedAnnouncementId === $announcement->id ? ' pinned' : '',
            );
        }
    } catch (ForbiddenException $e) {
        // Announcements need the plan feature psp-subscribers (code 000-003).
        echo "  announcements: {$e->getMessage()}\n";
    }
}

if ($count === 0) {
    echo "No status pages.\n";
}
