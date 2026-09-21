<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Resource;

use DateTimeImmutable;
use GoSuccess\UptimeRobot\Enum\HttpAuthType;
use GoSuccess\UptimeRobot\Enum\HttpMethod;
use GoSuccess\UptimeRobot\Enum\IpVersion;
use GoSuccess\UptimeRobot\Enum\KeywordCaseType;
use GoSuccess\UptimeRobot\Enum\KeywordType;
use GoSuccess\UptimeRobot\Enum\MonitorStatus;
use GoSuccess\UptimeRobot\Enum\MonitorType;
use GoSuccess\UptimeRobot\Enum\PostValueType;
use GoSuccess\UptimeRobot\Enum\Region;
use GoSuccess\UptimeRobot\Enum\RegionInfrastructure;
use GoSuccess\UptimeRobot\Enum\ResponseTimeRegion;
use GoSuccess\UptimeRobot\Enum\UptimeLogType;
use GoSuccess\UptimeRobot\Enum\UptimeTimeFrame;
use GoSuccess\UptimeRobot\Exception\BadRequestException;
use GoSuccess\UptimeRobot\Exception\NotFoundException;
use GoSuccess\UptimeRobot\Http\Response;
use GoSuccess\UptimeRobot\Model\HttpMonitorConfig;
use GoSuccess\UptimeRobot\Model\KeywordMonitorCreate;
use GoSuccess\UptimeRobot\Model\MonitorConfigUpdate;
use GoSuccess\UptimeRobot\Model\MonitorUpdate;
use GoSuccess\UptimeRobot\Model\RegionalData;
use GoSuccess\UptimeRobot\Resource\MonitorResource;
use GoSuccess\UptimeRobot\Tests\Support\MockHttpClient;
use GoSuccess\UptimeRobot\UptimeRobot;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The monitors resource against a mocked transport, with the payloads the live
 * API sent (IDs of deleted test monitors, the user ID replaced): the requests
 * it sends, the responses it reads, and the hand-written uptimeStats().
 */
#[CoversClass(MonitorResource::class)]
final class MonitorResourceTest extends TestCase
{
    /**
     * An HTTP monitor as POST /monitors returned it (verified live), plus a last incident.
     */
    private const string MONITOR = '{"type":"HTTP","interval":300,"sslBrand":null,"sslExpiryDateTime":null,"domainExpireDate":null,'
        . '"checkSSLErrors":true,"sslExpirationReminder":false,"domainExpirationReminder":false,"followRedirections":false,'
        . '"authType":"NONE","httpUsername":"","httpPassword":"","customHttpHeaders":{"X-Test":"1","X-Num":2},"httpMethodType":"POST",'
        . '"successHttpResponseCodes":["2xx","404"],"timeout":30,"postValueData":{"a":1,"b":"x"},"postValueType":"RAW_JSON","port":null,'
        . '"gracePeriod":30,"keywordValue":"","keywordCaseType":0,"keywordType":null,"responseTimeThreshold":0,'
        . '"config":{"sslExpirationPeriodDays":[7,30],"ipVersion":"ipv4Only","applicationErrorRetries":1},'
        . '"regionalData":{"REGION":["eu"],"MANUAL_SELECTED":true,"INFRASTRUCTURE":"New","THRESHOLD":{"eu":5000}},'
        . '"maintenanceWindows":[],"psps":[],"id":804045264,"friendlyName":"GoSuccess API client test (safe to delete)",'
        . '"status":"STARTED","url":"https://example.com","currentStateDuration":0,"lastIncidentId":"352577094135060139","userId":1234567,'
        . '"tags":[],"assignedAlertContacts":[{"alertContactId":8733402,"threshold":0,"recurrence":0}],'
        . '"lastIncident":{"id":"352577094135060139","status":"Resolved","cause":333333,"reason":"Connection Timeout","startedAt":"2026-08-30T22:15:29.807Z","duration":64},'
        . '"lastDayUptimes":{"bucketSize":0,"histogram":[]},"createDateTime":"2026-09-21T09:10:00.000Z","apiKey":"","groupId":0,"customFields":{"env":"test"}}';

    public function testReadsAMonitorAsTheApiSendsIt(): void
    {
        $http = new MockHttpClient(new Response(200, self::MONITOR));

        $monitor = self::client($http)->monitors->get(804045264);

        self::assertSame('https://api.uptimerobot.com/v3/monitors/804045264', $http->requests[0]->uri);
        self::assertSame(804045264, $monitor->id);
        self::assertSame(MonitorType::Http, $monitor->type);
        self::assertSame(MonitorStatus::Started, $monitor->status);
        self::assertSame(HttpAuthType::None, $monitor->authType);
        self::assertSame(HttpMethod::Post, $monitor->httpMethodType);
        self::assertTrue($monitor->checkSslErrors);
        // 0 on monitors of other types than keyword.
        self::assertSame(KeywordCaseType::CaseSensitive, $monitor->keywordCaseType);
        self::assertNull($monitor->keywordType);
        self::assertSame(['a' => 1, 'b' => 'x'], $monitor->postValueData);
        self::assertSame(PostValueType::RawJson, $monitor->postValueType);
        self::assertSame(['X-Test' => '1', 'X-Num' => '2'], $monitor->customHttpHeaders);
        self::assertSame(['2xx', '404'], $monitor->successHttpResponseCodes);
        self::assertSame(['env' => 'test'], $monitor->customFields);
        self::assertSame(0, $monitor->groupId);
        self::assertEquals(new DateTimeImmutable('2026-09-21T09:10:00Z'), $monitor->createDateTime);

        self::assertNotNull($monitor->config);
        self::assertSame([7, 30], $monitor->config->sslExpirationPeriodDays);
        self::assertSame(IpVersion::Ipv4Only, $monitor->config->ipVersion);
        self::assertSame(1, $monitor->config->applicationErrorRetries);
        // Keys that were not set are null, not empty objects.
        self::assertNull($monitor->config->dnsRecords);
        self::assertNull($monitor->config->apiAssertions);

        self::assertSame([Region::Europe], $monitor->regionalData->region);
        self::assertSame(['eu' => 5000], $monitor->regionalData->threshold);
        self::assertTrue($monitor->regionalData->manualSelected);
        self::assertSame(RegionInfrastructure::New, $monitor->regionalData->infrastructure);

        self::assertSame(8733402, $monitor->assignedAlertContacts[0]->alertContactId ?? null);
        // Incident IDs exceed the integers JSON numbers hold exactly.
        self::assertSame('352577094135060139', $monitor->lastIncidentId);
        self::assertNotNull($monitor->lastIncident);
        self::assertSame('352577094135060139', $monitor->lastIncident->id);
        self::assertSame(333333, $monitor->lastIncident->cause);
        self::assertEquals(new DateTimeImmutable('2026-08-30T22:15:29.807Z'), $monitor->lastIncident->startedAt);
        self::assertNull($monitor->lastDayUptimes->totalChanges);
    }

    public function testReadsAnEmptyConfigAndMissingOptionalKeys(): void
    {
        $http = new MockHttpClient(new Response(200, '{"id":1,"config":{},"regionalData":{"REGION":["na"],"INFRASTRUCTURE":"New"}}'), new Response(200, '{"id":2,"config":null}'));
        $monitors = self::client($http)->monitors;

        $empty = $monitors->get(1);
        $none = $monitors->get(2);

        self::assertNotNull($empty->config);
        self::assertSame([], $empty->config->sslExpirationPeriodDays);
        self::assertNull($empty->config->applicationErrorRetries);
        self::assertFalse($empty->regionalData->manualSelected);
        self::assertSame([], $empty->regionalData->threshold);
        self::assertNull($none->config);
    }

    public function testCreatesAMonitorOfTheTypeOfItsModel(): void
    {
        $http = new MockHttpClient(new Response(201, self::MONITOR));

        $monitor = self::client($http)->monitors->create(new KeywordMonitorCreate(
            friendlyName: 'Shop',
            interval: 300,
            url: 'https://example.com',
            timeout: 30,
            keywordType: KeywordType::AlertNotExists,
            keywordCaseType: KeywordCaseType::CaseInsensitive,
            keywordValue: 'Example Domain',
            assignedAlertContacts: [],
            regionData: new RegionalData(region: [Region::Europe, Region::NorthAmerica], threshold: ['eu' => 2000]),
            postValueData: '{"a":1}',
            config: new HttpMonitorConfig(sslExpirationPeriodDays: [7, 14]),
        ));

        self::assertSame(804045264, $monitor->id);
        self::assertSame('POST', $http->requests[0]->method->value);
        self::assertSame('https://api.uptimerobot.com/v3/monitors', $http->requests[0]->uri);
        self::assertSame('application/json', $http->requests[0]->headers['Content-Type'] ?? null);
        // keywordCaseType as the number, which create accepts as well (verified live).
        self::assertSame(
            '{"type":"KEYWORD","friendlyName":"Shop","interval":300,"url":"https://example.com","timeout":30,"keywordType":"ALERT_NOT_EXISTS",'
            . '"keywordCaseType":1,"keywordValue":"Example Domain","assignedAlertContacts":[],"regionData":{"REGION":["eu","na"],"THRESHOLD":{"eu":2000}},'
            . '"postValueData":"{\"a\":1}","config":{"sslExpirationPeriodDays":[7,14]}}',
            $http->requests[0]->body,
        );
    }

    /**
     * @return iterable<string, array{MonitorUpdate, string}>
     */
    public static function updates(): iterable
    {
        yield 'config merged key by key, null removes a key' => [
            new MonitorUpdate(config: new MonitorConfigUpdate(sslExpirationPeriodDays: null, ipVersion: null, applicationErrorRetries: 2)),
            '{"config":{"sslExpirationPeriodDays":null,"ipVersion":null,"applicationErrorRetries":2}}',
        ];

        yield 'config cleared' => [new MonitorUpdate(config: null), '{"config":null}'];

        yield 'maps and success codes replaced or reset' => [
            new MonitorUpdate(customHttpHeaders: [], successHttpResponseCodes: [], customFields: ['env' => 'prod']),
            '{"customHttpHeaders":{},"successHttpResponseCodes":[],"customFields":{"env":"prod"}}',
        ];

        yield 'nullable method, keyword case as number' => [
            new MonitorUpdate(httpMethodType: null, keywordCaseType: KeywordCaseType::CaseSensitive, checkSslErrors: false),
            '{"keywordCaseType":0,"httpMethodType":null,"checkSSLErrors":false}',
        ];

        yield 'nothing' => [new MonitorUpdate(), '{}'];
    }

    #[DataProvider('updates')]
    public function testUpdatesOnlyWhatIsSet(MonitorUpdate $changes, string $body): void
    {
        $http = new MockHttpClient(new Response(200, self::MONITOR));

        self::client($http)->monitors->update(804045264, $changes);

        self::assertSame('PATCH', $http->requests[0]->method->value);
        self::assertSame('https://api.uptimerobot.com/v3/monitors/804045264', $http->requests[0]->uri);
        self::assertSame($body, $http->requests[0]->body);
    }

    public function testListsWithFiltersAndReportsTheNextCursor(): void
    {
        $http = new MockHttpClient(new Response(200, '{"data":[' . self::MONITOR . '],"nextLink":"https://api.uptimerobot.com/v3/monitors/?status=UP%2CPAUSED&limit=1&cursor=804045264"}'));

        $page = self::client($http)->monitors->list(
            limit: 1,
            customField: ['env:test', 'team:qa'],
            groupId: 0,
            status: [MonitorStatus::Up, MonitorStatus::Paused],
            name: 'shop',
            tags: ['web', 'eu'],
        );

        self::assertSame(
            'https://api.uptimerobot.com/v3/monitors?limit=1&customField=env%3Atest&customField=team%3Aqa&groupId=0&status=UP%2CPAUSED&name=shop&tags=web%2Ceu',
            $http->requests[0]->uri,
        );
        self::assertCount(1, $page->items);
        self::assertSame(804045264, $page->next);
    }

    public function testIteratesOverAllMonitorsInPagesOf200(): void
    {
        $http = new MockHttpClient(
            new Response(200, '{"data":[{"id":1},{"id":2}],"nextLink":"https://api.uptimerobot.com/v3/monitors/?limit=200&cursor=2"}'),
            // The last page reports no nextLink at all (verified live).
            new Response(200, '{"data":[{"id":3}]}'),
        );

        $ids = [];

        foreach (self::client($http)->monitors->all(status: [MonitorStatus::Down]) as $monitor) {
            $ids[] = $monitor->id;
        }

        self::assertSame([1, 2, 3], $ids);
        self::assertSame('https://api.uptimerobot.com/v3/monitors?limit=200&status=DOWN', $http->requests[0]->uri);
        self::assertSame('https://api.uptimerobot.com/v3/monitors?cursor=2&limit=200&status=DOWN', $http->requests[1]->uri);
    }

    public function testSendsAnEmptyJsonObjectToPauseStartAndReset(): void
    {
        $paused = str_replace('"STARTED"', '"PAUSED"', self::MONITOR);
        // reset answers 201 without a body (verified live).
        $http = new MockHttpClient(new Response(201, $paused), new Response(201, self::MONITOR), new Response(201));
        $monitors = self::client($http)->monitors;

        self::assertSame(MonitorStatus::Paused, $monitors->pause(804045264)->status);
        self::assertSame(MonitorStatus::Started, $monitors->start(804045264)->status);
        $monitors->reset(804045264);

        foreach (['pause', 'start', 'reset'] as $index => $action) {
            $request = $http->requests[$index];
            self::assertSame('POST', $request->method->value);
            self::assertSame("https://api.uptimerobot.com/v3/monitors/804045264/{$action}", $request->uri);
            // Without it, the API answers 415 (verified live).
            self::assertSame('application/json', $request->headers['Content-Type'] ?? null);
            self::assertSame('{}', $request->body);
        }
    }

    public function testDeletesAndReportsAMissingMonitor(): void
    {
        $http = new MockHttpClient(
            new Response(200),
            new Response(404, '{"message":"Monitor not found","code":"000-004"}'),
        );
        $monitors = self::client($http)->monitors;

        $monitors->delete(804045264);

        self::assertSame('DELETE', $http->requests[0]->method->value);
        self::assertNull($http->requests[0]->body);

        try {
            $monitors->get(804045264);
            self::fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            self::assertSame('000-004', $e->errorCode);
        }
    }

    public function testReadsTheUptimeStatisticsOfATimeFrame(): void
    {
        // overallUptime is the int 1 without downtime (verified live).
        $http = new MockHttpClient(new Response(200, '{"overallUptime":1,"totalIncidents":1,"totalTimeWithoutIncidents":3155579985,"affectedMonitors":1,'
            . '"logs":[{"type":"DOWNTIME","datetime":"2026-09-16T05:51:03.052Z","duration":12075}],"mtbf":null}'));

        $stats = self::client($http)->monitors->uptimeStats(UptimeTimeFrame::Days30);

        self::assertSame('GET', $http->requests[0]->method->value);
        self::assertSame('https://api.uptimerobot.com/v3/monitors/uptime-stats?timeFrame=DAYS_30', $http->requests[0]->uri);
        self::assertSame(1.0, $stats->overallUptime);
        self::assertSame(3155579985, $stats->totalTimeWithoutIncidents);
        self::assertNull($stats->mtbf);
        self::assertSame(UptimeLogType::Downtime, $stats->logs[0]->type ?? null);
        self::assertEquals(new DateTimeImmutable('2026-09-16T05:51:03.052Z'), $stats->logs[0]->datetime);
        self::assertSame(12075, $stats->logs[0]->duration);
    }

    public function testSendsACustomTimeFrameAsUnixSeconds(): void
    {
        $http = new MockHttpClient(new Response(200, '{"overallUptime":0.9958582478343357,"totalIncidents":3,"totalTimeWithoutIncidents":1629432,"affectedMonitors":3,"logs":[],"mtbf":4481738}'));

        $stats = self::client($http)->monitors->uptimeStats(
            UptimeTimeFrame::Custom,
            start: new DateTimeImmutable('2026-09-01T02:00:00+02:00'),
            end: new DateTimeImmutable('2026-09-20T00:00:00Z'),
            logLimit: 10,
        );

        self::assertSame('https://api.uptimerobot.com/v3/monitors/uptime-stats?timeFrame=CUSTOM&start=1788220800&end=1789862400&logLimit=10', $http->requests[0]->uri);
        self::assertSame(0.9958582478343357, $stats->overallUptime);
        self::assertSame(4481738, $stats->mtbf);
    }

    /**
     * @return iterable<string, array{UptimeTimeFrame, ?DateTimeImmutable, ?DateTimeImmutable, string}>
     */
    public static function invalidTimeFrames(): iterable
    {
        $start = new DateTimeImmutable('2026-09-01T00:00:00Z');
        $end = new DateTimeImmutable('2026-09-20T00:00:00Z');

        yield 'custom without range' => [UptimeTimeFrame::Custom, null, null, 'UptimeTimeFrame::Custom needs $start and $end.'];
        yield 'custom without end' => [UptimeTimeFrame::Custom, $start, null, 'UptimeTimeFrame::Custom needs $start and $end.'];
        yield 'custom, empty range' => [UptimeTimeFrame::Custom, $start, $start, '$start must be at least one second before $end.'];
        yield 'custom, reversed range' => [UptimeTimeFrame::Custom, $end, $start, '$start must be at least one second before $end.'];
        yield 'range for another time frame' => [UptimeTimeFrame::Week, $start, $end, '$start and $end only apply to UptimeTimeFrame::Custom; the API ignores them for other time frames.'];
        yield 'end for another time frame' => [UptimeTimeFrame::Day, null, $end, '$start and $end only apply to UptimeTimeFrame::Custom; the API ignores them for other time frames.'];
    }

    #[DataProvider('invalidTimeFrames')]
    public function testRejectsARangeThatDoesNotFitTheTimeFrame(UptimeTimeFrame $timeFrame, ?DateTimeImmutable $start, ?DateTimeImmutable $end, string $message): void
    {
        $http = new MockHttpClient();

        try {
            self::client($http)->monitors->uptimeStats($timeFrame, $start, $end);
            self::fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException $e) {
            self::assertSame($message, $e->getMessage());
        }

        self::assertSame(0, $http->callCount());
    }

    public function testReportsTheValidationErrorsOfTheApi(): void
    {
        $http = new MockHttpClient(new Response(400, '{"message":["logLimit must not be less than 1"],"error":"Bad Request","statusCode":400}'));

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('logLimit must not be less than 1');

        self::client($http)->monitors->uptimeStats(UptimeTimeFrame::Day, logLimit: 0);
    }

    public function testReadsTheUptimeOfAMonitorAsAPercentage(): void
    {
        $http = new MockHttpClient(new Response(200, '{"uptime":92.54237891737893,"total_downtime_seconds":6441,"incident_count":2,"mtbf":38640,'
            . '"from":"2026-09-20T08:58:51.465Z","to":"2026-09-21T08:58:51.465Z"}'));

        $stats = self::client($http)->monitors->uptime(
            804045264,
            from: new DateTimeImmutable('2026-09-20T10:58:51.465+02:00'),
            to: new DateTimeImmutable('2026-09-21T08:58:51Z'),
        );

        self::assertSame('https://api.uptimerobot.com/v3/monitors/804045264/stats/uptime?from=2026-09-20T08%3A58%3A51Z&to=2026-09-21T08%3A58%3A51Z', $http->requests[0]->uri);
        self::assertSame(92.54237891737893, $stats->uptime);
        self::assertSame(6441, $stats->totalDowntimeSeconds);
        self::assertSame(2, $stats->incidentCount);
        self::assertEquals(new DateTimeImmutable('2026-09-20T08:58:51.465Z'), $stats->from);
    }

    public function testReadsResponseTimesWithTheirTimeSeries(): void
    {
        $http = new MockHttpClient(new Response(200, '{"summary":{"min":79,"max":2080,"avg":166},"data_points":2,"from":"2026-09-20T08:58:51.977Z","to":"2026-09-21T08:58:51.977Z",'
            . '"time_series":[{"timestamp":"2026-09-20T09:00:00.000Z","value":190},{"timestamp":"2026-09-20T09:05:00.000Z","value":134}]}'));

        $stats = self::client($http)->monitors->responseTimeStats(804045264, includeTimeSeries: true, region: ResponseTimeRegion::Europe);

        self::assertSame('https://api.uptimerobot.com/v3/monitors/804045264/stats/response-time?includeTimeSeries=true&region=eu', $http->requests[0]->uri);
        self::assertSame(166, $stats->summary->avg);
        self::assertSame(2, $stats->dataPoints);
        self::assertCount(2, $stats->timeSeries);
        self::assertEquals(new DateTimeImmutable('2026-09-20T09:05:00Z'), $stats->timeSeries[1]->timestamp);
        self::assertSame(134, $stats->timeSeries[1]->value);
    }

    public function testReadsResponseTimesByRegionWithNullForRegionsWithoutChecks(): void
    {
        $regional = '{"summary":{"min":79,"max":2080,"avg":166},"data_points":326,"from":"2026-09-20T08:58:52.338Z","to":"2026-09-21T08:58:52.338Z"}';
        $http = new MockHttpClient(new Response(200, "{\"na\":null,\"eu\":{$regional},\"as\":null,\"oc\":null,\"all\":{$regional},\"from\":\"2026-09-20T08:58:52.338Z\",\"to\":\"2026-09-21T08:58:52.338Z\"}"));

        $stats = self::client($http)->monitors->responseTimeStatsByRegion(804045264);

        self::assertSame('https://api.uptimerobot.com/v3/monitors/804045264/stats/response-time/all', $http->requests[0]->uri);
        self::assertNull($stats->na);
        self::assertNull($stats->as);
        self::assertNotNull($stats->eu);
        self::assertNotNull($stats->all);
        self::assertSame(326, $stats->eu->dataPoints);
        self::assertSame(2080, $stats->all->summary->max);
        // Without includeTimeSeries, there is none (verified live).
        self::assertSame([], $stats->eu->timeSeries);
    }

    private static function client(MockHttpClient $http): UptimeRobot
    {
        return new UptimeRobot('secret', httpClient: $http);
    }
}
