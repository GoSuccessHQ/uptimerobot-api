<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Integration;

use GoSuccess\UptimeRobot\Exception\ForbiddenException;
use GoSuccess\UptimeRobot\Exception\NotFoundException;
use GoSuccess\UptimeRobot\Model\Announcement;
use GoSuccess\UptimeRobot\UptimeRobot;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Reads the announcements of the status pages; nothing is changed. Needs a
 * status page and the plan feature psp-subscribers.
 */
#[CoversNothing]
final class AnnouncementsReadOnlyTest extends IntegrationTestCase
{
    public function testListsTheAnnouncementsOfEveryStatusPage(): void
    {
        $client = new UptimeRobot(self::apiKey());
        $pages = iterator_to_array($client->statusPages->all(), false);

        if ($pages === []) {
            self::markTestSkipped('The account has no status pages.');
        }

        foreach ($pages as $page) {
            try {
                $announcements = iterator_to_array($client->announcements->all($page->id), false);
            } catch (ForbiddenException $e) {
                if ($e->errorCode === '000-003') {
                    self::markTestSkipped('The plan of the account does not include announcements (psp-subscribers).');
                }

                throw $e;
            }

            $ids = array_map(static fn(Announcement $announcement): int => $announcement->id, $announcements);
            self::assertSame($ids, array_values(array_unique($ids)));
            $previous = null;

            foreach ($announcements as $announcement) {
                self::assertSame($page->id, $announcement->pspId);
                // The values of the descriptions; the casing of the response is
                // not documented, so this fails if the API sends others.
                self::assertContains($announcement->status, [null, 'Offline', 'Pending', 'Published', 'Archived']);
                self::assertContains($announcement->type, [null, 'Info', 'Maintenance', 'Issue']);

                // Newest first, according to the specification.
                if ($previous !== null && $announcement->creationDate !== null) {
                    self::assertLessThanOrEqual($previous, $announcement->creationDate);
                }

                $previous = $announcement->creationDate ?? $previous;
            }
        }
    }

    public function testChecksThePlanBeforeTheStatusPage(): void
    {
        $client = new UptimeRobot(self::apiKey());

        try {
            $client->announcements->list(999999999);
            self::fail('Expected a ForbiddenException or a NotFoundException.');
        } catch (ForbiddenException $e) {
            // Without psp-subscribers: "Feature psp-subscribers is not enabled in
            // your plan.", although the status page does not exist (verified live).
            self::assertSame('000-003', $e->errorCode);
        } catch (NotFoundException $e) {
            // With the feature, presumably "PSP not found" (not verified live).
            self::assertSame('000-004', $e->errorCode);
        }
    }
}
