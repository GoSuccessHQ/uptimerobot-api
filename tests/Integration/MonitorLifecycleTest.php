<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Integration;

use GoSuccess\UptimeRobot\Enum\IpVersion;
use GoSuccess\UptimeRobot\Enum\KeywordCaseType;
use GoSuccess\UptimeRobot\Enum\KeywordType;
use GoSuccess\UptimeRobot\Enum\MonitorStatus;
use GoSuccess\UptimeRobot\Enum\MonitorType;
use GoSuccess\UptimeRobot\Exception\NotFoundException;
use GoSuccess\UptimeRobot\Model\HttpMonitorConfig;
use GoSuccess\UptimeRobot\Model\KeywordMonitorCreate;
use GoSuccess\UptimeRobot\Model\MonitorConfigUpdate;
use GoSuccess\UptimeRobot\Model\MonitorUpdate;
use GoSuccess\UptimeRobot\UptimeRobot;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Creates a monitor, reads, changes, pauses, starts and resets it, reads its
 * statistics and deletes it again.
 *
 * It writes to the account, so it only runs with UPTIMEROBOT_ALLOW_WRITES=1
 * besides UPTIMEROBOT_API_KEY. The monitor is a keyword monitor of
 * https://example.com, checked every 5 minutes, that alerts nobody and is
 * named "GoSuccess API client test (safe to delete)". It is deleted even when
 * a check fails; one that an aborted run left behind is deleted first. It
 * needs a free monitor slot of the plan.
 */
#[CoversNothing]
final class MonitorLifecycleTest extends IntegrationTestCase
{
    private const string NAME = 'GoSuccess API client test (safe to delete)';

    private const string URL = 'https://example.com';

    public function testCreatesChangesAndDeletesAMonitor(): void
    {
        $client = new UptimeRobot(self::writableApiKey());
        self::deleteLeftovers($client);
        $count = $client->user->me()->monitorsCount;

        $monitor = $client->monitors->create(new KeywordMonitorCreate(
            friendlyName: self::NAME,
            interval: 300,
            url: self::URL,
            timeout: 30,
            keywordType: KeywordType::AlertNotExists,
            // Sent as the number 1, which create documents as "CaseInsensitive".
            keywordCaseType: KeywordCaseType::CaseInsensitive,
            keywordValue: 'Example Domain',
            assignedAlertContacts: [],
            config: new HttpMonitorConfig(sslExpirationPeriodDays: [7, 14], ipVersion: IpVersion::Ipv4Only),
        ));

        try {
            self::assertSame(MonitorType::Keyword, $monitor->type);
            self::assertSame(MonitorStatus::Started, $monitor->status);
            self::assertSame(KeywordCaseType::CaseInsensitive, $monitor->keywordCaseType);
            self::assertSame([], $monitor->assignedAlertContacts);
            self::assertNotNull($monitor->config);
            self::assertSame([7, 14], $monitor->config->sslExpirationPeriodDays);
            self::assertSame(IpVersion::Ipv4Only, $monitor->config->ipVersion);

            $read = $client->monitors->get($monitor->id);

            self::assertSame(self::NAME, $read->friendlyName);
            self::assertSame('Example Domain', $read->keywordValue);

            // config is merged key by key, null removes a key; the maps are replaced.
            $changed = $client->monitors->update($monitor->id, new MonitorUpdate(
                keywordCaseType: KeywordCaseType::CaseSensitive,
                customHttpHeaders: ['X-Test' => '1'],
                successHttpResponseCodes: ['2xx'],
                config: new MonitorConfigUpdate(ipVersion: null, applicationErrorRetries: 2),
            ));

            self::assertSame(KeywordCaseType::CaseSensitive, $changed->keywordCaseType);
            self::assertSame(['X-Test' => '1'], $changed->customHttpHeaders);
            self::assertSame(['2xx'], $changed->successHttpResponseCodes);
            self::assertNotNull($changed->config);
            self::assertSame([7, 14], $changed->config->sslExpirationPeriodDays);
            self::assertNull($changed->config->ipVersion);
            self::assertSame(2, $changed->config->applicationErrorRetries);

            // Empty values remove the headers and reset the codes; null clears the config.
            $cleared = $client->monitors->update($monitor->id, new MonitorUpdate(customHttpHeaders: [], successHttpResponseCodes: [], config: null));

            self::assertSame([], $cleared->customHttpHeaders);
            self::assertSame(['2xx', '3xx'], $cleared->successHttpResponseCodes);
            self::assertNull($cleared->config);

            self::assertSame(MonitorStatus::Paused, $client->monitors->pause($monitor->id)->status);
            self::assertSame(MonitorStatus::Started, $client->monitors->start($monitor->id)->status);
            $client->monitors->reset($monitor->id);

            $uptime = $client->monitors->uptime($monitor->id);
            $responseTimes = $client->monitors->responseTimeStats($monitor->id);

            self::assertGreaterThanOrEqual(0.0, $uptime->uptime);
            self::assertLessThanOrEqual(100.0, $uptime->uptime);
            self::assertGreaterThanOrEqual(0, $responseTimes->dataPoints);
        } finally {
            $client->monitors->delete($monitor->id);
        }

        try {
            $client->monitors->get($monitor->id);
            self::fail('The monitor still exists.');
        } catch (NotFoundException $e) {
            self::assertSame('000-004', $e->errorCode);
        }

        self::assertSame($count, $client->user->me()->monitorsCount);
    }

    /**
     * Delete the monitors of runs that were aborted before they could clean up.
     */
    private static function deleteLeftovers(UptimeRobot $client): void
    {
        foreach ($client->monitors->list(name: self::NAME)->items as $monitor) {
            if ($monitor->friendlyName === self::NAME && $monitor->url === self::URL) {
                $client->monitors->delete($monitor->id);
            }
        }
    }
}
