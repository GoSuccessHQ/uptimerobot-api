<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Integration;

use GoSuccess\UptimeRobot\Exception\NotFoundException;
use GoSuccess\UptimeRobot\Model\StatusPage;
use GoSuccess\UptimeRobot\UptimeRobot;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Reads the status pages of the account and those its monitors name; nothing
 * is changed. Most checks need at least one status page.
 */
#[CoversNothing]
final class StatusPagesReadOnlyTest extends IntegrationTestCase
{
    /** @var list<StatusPage>|null Every status page, read once for all tests. */
    private static ?array $pages = null;

    public function testListsTheStatusPages(): void
    {
        $pages = self::pages();
        $ids = array_map(static fn(StatusPage $page): int => $page->id, $pages);

        // An account without status pages gets {"data": []} (verified live).
        self::assertSame($ids, array_values(array_unique($ids)));

        foreach ($pages as $page) {
            self::assertNotSame('', $page->friendlyName);
            self::assertNotSame('', $page->urlKey);
            // The values of the requests; the specification documents none for
            // the response, so this fails if the API reports others.
            self::assertContains($page->status, ['ENABLED', 'PAUSED'], "Unknown status of status page {$page->id}.");

            $design = $page->customSettings?->page;

            if ($design !== null) {
                // Every value the API sends is one the enums know.
                self::assertNotNull($design->layout, "Unknown layout of status page {$page->id}.");
                self::assertNotNull($design->theme, "Unknown theme of status page {$page->id}.");
                self::assertNotNull($design->density, "Unknown density of status page {$page->id}.");
            }
        }
    }

    public function testReadsAPageAsTheListDoes(): void
    {
        $pages = self::pages();

        if ($pages === []) {
            self::markTestSkipped('The account has no status pages.');
        }

        self::assertEquals($pages[0], self::client()->statusPages->get($pages[0]->id));
    }

    public function testReportsAnUnknownPage(): void
    {
        self::pages();

        try {
            self::client()->statusPages->get(999999999);
            self::fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            // {"message":"PSP not found","code":"000-004"} (verified live).
            self::assertSame('000-004', $e->errorCode);
        }
    }

    public function testEveryStatusPageOfAMonitorIsListed(): void
    {
        $ids = array_map(static fn(StatusPage $page): int => $page->id, self::pages());
        $unlisted = [];

        foreach (self::client()->monitors->all() as $monitor) {
            foreach ($monitor->psps as $page) {
                if (!\in_array($page->id, $ids, true)) {
                    $unlisted[] = "status page {$page->id} of monitor {$monitor->id}";
                }
            }
        }

        self::assertSame([], $unlisted);
    }

    private static function client(): UptimeRobot
    {
        return new UptimeRobot(self::apiKey());
    }

    /**
     * @return list<StatusPage>
     */
    private static function pages(): array
    {
        return self::$pages ??= iterator_to_array(self::client()->statusPages->all(), false);
    }
}
