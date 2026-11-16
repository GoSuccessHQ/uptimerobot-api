<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Resource;

use DateTimeImmutable;
use GoSuccess\UptimeRobot\Enum\ThirdPartyServiceMaintenanceState;
use GoSuccess\UptimeRobot\Http\Response;
use GoSuccess\UptimeRobot\Resource\ThirdPartyServiceResource;
use GoSuccess\UptimeRobot\Tests\Support\MockHttpClient;
use GoSuccess\UptimeRobot\UptimeRobot;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The third party service monitors of the account as a whole, with the payloads
 * the specification of November 16, 2026 describes (not verified live).
 */
#[CoversClass(ThirdPartyServiceResource::class)]
final class ThirdPartyServiceResourceTest extends TestCase
{
    public function testReadsTheMaintenanceWindowsOfEveryTrackedProviderWithTheirMonitor(): void
    {
        $http = new MockHttpClient(new Response(200, '{"data":[{"id":"mnt_8821","serviceId":4317,"remoteId":"abc123",'
            . '"url":"https://www.githubstatus.com/incidents/abc123","title":"Scheduled database upgrade",'
            . '"description":"Actions may be unavailable.","impact":"maintenance","window":"announced",'
            . '"scheduledFor":"2026-11-20T22:00:00.000Z","scheduledUntil":"2026-11-21T02:00:00.000Z",'
            . '"affectedComponents":[{"id":555,"name":"Actions","externalId":"gh-actions","parentId":null}],'
            . '"monitorId":804093942,"friendlyName":"GitHub"},'
            . '{"id":"mnt_8822","serviceId":7,"remoteId":null,"url":null,"title":"Network maintenance","description":null,'
            . '"impact":null,"window":"in_progress","scheduledFor":"2026-11-16T10:00:00.000Z",'
            . '"scheduledUntil":"2026-11-16T12:00:00.000Z","affectedComponents":[],"monitorId":804093943,'
            . '"friendlyName":"GitLab"}]}'));

        $maintenances = self::client($http)->thirdPartyServices->maintenances();

        self::assertSame('https://api.uptimerobot.com/v3/third-party-services/maintenances', $http->requests[0]->uri);
        self::assertCount(2, $maintenances);
        self::assertSame('mnt_8821', $maintenances[0]->id);
        self::assertSame(4317, $maintenances[0]->serviceId);
        self::assertSame('abc123', $maintenances[0]->remoteId);
        self::assertSame(ThirdPartyServiceMaintenanceState::Announced, $maintenances[0]->window);
        self::assertEquals(new DateTimeImmutable('2026-11-20T22:00:00Z'), $maintenances[0]->scheduledFor);
        self::assertEquals(new DateTimeImmutable('2026-11-21T02:00:00Z'), $maintenances[0]->scheduledUntil);
        // Every row names the monitor the window belongs to.
        self::assertSame(804093942, $maintenances[0]->monitorId);
        self::assertSame('GitHub', $maintenances[0]->friendlyName);
        self::assertCount(1, $maintenances[0]->affectedComponents);
        self::assertSame('Actions', $maintenances[0]->affectedComponents[0]->name);
        self::assertSame('gh-actions', $maintenances[0]->affectedComponents[0]->externalId);
        self::assertNull($maintenances[0]->affectedComponents[0]->parentId);
        // A window a provider published without any text of its own.
        self::assertSame(ThirdPartyServiceMaintenanceState::InProgress, $maintenances[1]->window);
        self::assertNull($maintenances[1]->remoteId);
        self::assertNull($maintenances[1]->url);
        self::assertNull($maintenances[1]->description);
        self::assertNull($maintenances[1]->impact);
        self::assertSame([], $maintenances[1]->affectedComponents);
    }

    /**
     * "Nothing scheduled returns an empty array with a 200, never an error."
     */
    public function testReadsNothingScheduledAsAnEmptyList(): void
    {
        $http = new MockHttpClient(new Response(200, '{"data":[]}'));

        self::assertSame([], self::client($http)->thirdPartyServices->maintenances());
    }

    private static function client(MockHttpClient $http): UptimeRobot
    {
        return new UptimeRobot('secret', httpClient: $http);
    }
}
