<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Resource;

use DateTimeImmutable;
use GoSuccess\UptimeRobot\Enum\AlertDeliveryStatus;
use GoSuccess\UptimeRobot\Enum\AssertionComparison;
use GoSuccess\UptimeRobot\Enum\AssertionFailureReason;
use GoSuccess\UptimeRobot\Enum\AssertionLogic;
use GoSuccess\UptimeRobot\Enum\AssertionSource;
use GoSuccess\UptimeRobot\Enum\AssertionValueType;
use GoSuccess\UptimeRobot\Enum\Region;
use GoSuccess\UptimeRobot\Exception\BadRequestException;
use GoSuccess\UptimeRobot\Exception\NotFoundException;
use GoSuccess\UptimeRobot\Http\Response;
use GoSuccess\UptimeRobot\Model\ActivityLogEntry;
use GoSuccess\UptimeRobot\Model\CommentActivity;
use GoSuccess\UptimeRobot\Model\IncidentSummary;
use GoSuccess\UptimeRobot\Model\NotificationActivity;
use GoSuccess\UptimeRobot\Model\SentAlert;
use GoSuccess\UptimeRobot\Model\StatusUpdateActivity;
use GoSuccess\UptimeRobot\Model\UnknownActivityLogEntry;
use GoSuccess\UptimeRobot\Resource\IncidentResource;
use GoSuccess\UptimeRobot\Tests\Support\MockHttpClient;
use GoSuccess\UptimeRobot\UptimeRobot;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The incidents resource against a mocked transport, with the payloads the live
 * API sent (recipient names and device tokens replaced): string IDs beyond
 * 2^53, the filters under their API names, the root cause, the activity log
 * union and the sent alerts.
 */
#[CoversClass(IncidentResource::class)]
final class IncidentResourceTest extends TestCase
{
    /** A downtime as GET /incidents listed it (verified live). */
    private const string DOWNTIME = '{"id":"358532761126055015","status":"Resolved","type":"Downtime","cause":403,"reason":"403 Forbidden",'
        . '"monitor":{"id":804005651,"friendlyName":"my.gosuccess.io"},"commentsCount":0,"startedAt":"2026-09-16T08:41:11.469Z",'
        . '"resolvedAt":"2026-09-16T09:12:18.469Z","duration":1867,"includeInReports":true}';

    /** A slow response as GET /incidents listed it (verified live). */
    private const string SLOW_RESPONSE = '{"id":"349291092551826516","status":"Resolved","type":"SlowResponse","cause":0,"reason":"Response time",'
        . '"monitor":{"id":803767164,"friendlyName":"Enhance API Status"},"commentsCount":0,"startedAt":"2026-08-21T20:38:05.979Z",'
        . '"resolvedAt":"2026-08-21T20:51:10.979Z","duration":785,"includeInReports":false}';

    public function testReadsAPageAsTheApiSendsIt(): void
    {
        // A single page has no nextLink key at all (verified live).
        $http = new MockHttpClient(new Response(200, '{"data":[' . self::DOWNTIME . ',' . self::SLOW_RESPONSE . ']}'));

        $page = self::client($http)->incidents->list();

        self::assertSame('GET', $http->requests[0]->method->value);
        self::assertSame('https://api.uptimerobot.com/v3/incidents', $http->requests[0]->uri);
        self::assertNull($page->next);
        self::assertCount(2, $page->items);

        [$downtime, $slow] = $page->items;
        // Beyond 2^53: kept digit for digit.
        self::assertSame('358532761126055015', $downtime->id);
        self::assertSame('Resolved', $downtime->status);
        self::assertSame('Downtime', $downtime->type);
        self::assertSame(403, $downtime->cause);
        self::assertSame('403 Forbidden', $downtime->reason);
        self::assertSame(804005651, $downtime->monitor->id);
        self::assertSame('my.gosuccess.io', $downtime->monitor->friendlyName);
        self::assertSame(0, $downtime->commentsCount);
        self::assertSame('2026-09-16T08:41:11.469+00:00', $downtime->startedAt?->format('Y-m-d\TH:i:s.vP'));
        self::assertSame('2026-09-16T09:12:18.469+00:00', $downtime->resolvedAt?->format('Y-m-d\TH:i:s.vP'));
        self::assertSame(1867, $downtime->duration);
        self::assertTrue($downtime->includeInReports);

        self::assertSame('SlowResponse', $slow->type);
        self::assertSame(0, $slow->cause);
        self::assertFalse($slow->includeInReports);
    }

    public function testSendsTheFiltersUnderTheirApiNames(): void
    {
        $http = new MockHttpClient(new Response(200, '{"data":[]}'));

        $page = self::client($http)->incidents->list(
            cursor: '352577094135060139',
            monitorId: 803767164,
            monitorName: 'ns',
            startedAfter: new DateTimeImmutable('2026-09-01T02:00:00+02:00'),
            startedBefore: new DateTimeImmutable('2026-09-20T23:59:59Z'),
        );

        // Dates in UTC with milliseconds, as the API accepts them (verified
        // live: started_after=2026-09-01T00:00:00Z and …T08:41:11.469Z).
        self::assertSame(
            'https://api.uptimerobot.com/v3/incidents?cursor=352577094135060139&monitor_id=803767164&monitor_name=ns'
            . '&started_after=2026-09-01T00%3A00%3A00.000Z&started_before=2026-09-20T23%3A59%3A59.000Z',
            $http->requests[0]->uri,
        );
        self::assertSame([], $page->items);
        self::assertNull($page->next);
    }

    public function testFiltersByTheStartOfAnIncidentToTheMillisecond(): void
    {
        // Both bounds are inclusive and compared to the millisecond (verified
        // live): started_before=…T08:41:11.469Z includes this incident,
        // …T08:41:11Z leaves it out, and started_after=…T08:41:11.470Z excludes it.
        $http = new MockHttpClient(new Response(200, '{"data":[' . self::DOWNTIME . ']}'), new Response(200, '{"data":[]}'));
        $client = self::client($http);
        $startedAt = $client->incidents->list()->items[0]->startedAt;
        self::assertNotNull($startedAt);

        $client->incidents->list(startedAfter: $startedAt->modify('+1 millisecond'), startedBefore: $startedAt);

        self::assertSame(
            'https://api.uptimerobot.com/v3/incidents?started_after=2026-09-16T08%3A41%3A11.470Z&started_before=2026-09-16T08%3A41%3A11.469Z',
            $http->requests[1]->uri,
        );
    }

    public function testIteratesOverAllIncidentsWithTheIdOfTheLastIncidentAsCursor(): void
    {
        // A nextLink shaped like those of the monitors: the incidents of the test
        // account fit on one page, so none was observed.
        $http = new MockHttpClient(
            new Response(200, '{"data":[' . self::DOWNTIME . '],"nextLink":"https://api.uptimerobot.com/v3/incidents/?monitor_id=804005651&cursor=358532761126055015"}'),
            new Response(200, '{"data":[' . self::SLOW_RESPONSE . '],"nextLink":null}'),
        );

        $ids = array_map(
            static fn(IncidentSummary $incident): string => $incident->id,
            iterator_to_array(self::client($http)->incidents->all(monitorId: 804005651), false),
        );

        self::assertSame(['358532761126055015', '349291092551826516'], $ids);
        self::assertSame('https://api.uptimerobot.com/v3/incidents?monitor_id=804005651', $http->requests[0]->uri);
        // The cursor stays the exact string, which a float would round.
        self::assertSame('https://api.uptimerobot.com/v3/incidents?cursor=358532761126055015&monitor_id=804005651', $http->requests[1]->uri);
    }

    public function testReportsACursorThatIsNotAnIncidentId(): void
    {
        $http = new MockHttpClient(new Response(400, '{"message":["cursor must contain only digits"],"error":"Bad Request","statusCode":400}'));

        try {
            self::client($http)->incidents->list(cursor: 'abc');
            self::fail('Expected a BadRequestException.');
        } catch (BadRequestException $e) {
            self::assertStringEndsWith('HTTP 400: cursor must contain only digits', $e->getMessage());
        }
    }

    public function testReadsAnIncidentWithItsRootCause(): void
    {
        // GET /incidents/{id} of the downtime above (verified live; fewer headers).
        $http = new MockHttpClient(new Response(200, '{"id":"358532761126055015","status":"Resolved","cause":403,"reason":"403 Forbidden",'
            . '"duration":1867,"startedAt":"2026-09-16T08:41:11.469Z","resolvedAt":"2026-09-16T09:12:18.469Z","rootCause":{'
            . '"url":"GET https://my.gosuccess.io/?*cachebuster*","requestHeaders":{"Accept-Encoding":"gzip, deflate, br","Connection":"close",'
            . '"User-Agent":"Mozilla/5.0+(compatible; UptimeRobot/2.0; http://www.uptimerobot.com/)"},'
            . '"responseHeaders":{"Content-Type":["text/html"],"Server":["BunnyCDN-DE1-1329"],"Vary":["Accept-Encoding"]},'
            . '"httpResponseCode":403,"responseDownloadUrl":"","assertionDiagnostics":null}}'));

        $incident = self::client($http)->incidents->get('358532761126055015');

        self::assertSame('https://api.uptimerobot.com/v3/incidents/358532761126055015', $http->requests[0]->uri);
        self::assertSame('358532761126055015', $incident->id);
        self::assertSame('Resolved', $incident->status);
        self::assertSame(403, $incident->cause);
        self::assertSame('403 Forbidden', $incident->reason);
        self::assertSame(1867, $incident->duration);
        self::assertSame('2026-09-16T08:41:11.469+00:00', $incident->startedAt?->format('Y-m-d\TH:i:s.vP'));
        self::assertSame('2026-09-16T09:12:18.469+00:00', $incident->resolvedAt?->format('Y-m-d\TH:i:s.vP'));

        $rootCause = $incident->rootCause;
        self::assertNotNull($rootCause);
        self::assertSame('GET https://my.gosuccess.io/?*cachebuster*', $rootCause->url);
        self::assertSame(
            ['Accept-Encoding' => 'gzip, deflate, br', 'Connection' => 'close', 'User-Agent' => 'Mozilla/5.0+(compatible; UptimeRobot/2.0; http://www.uptimerobot.com/)'],
            $rootCause->requestHeaders,
        );
        self::assertSame(['Content-Type' => ['text/html'], 'Server' => ['BunnyCDN-DE1-1329'], 'Vary' => ['Accept-Encoding']], $rootCause->responseHeaders);
        self::assertSame(403, $rootCause->httpResponseCode);
        self::assertSame('', $rootCause->responseDownloadUrl);
        self::assertNull($rootCause->assertionDiagnostics);
    }

    public function testReadsTheRootCauseOfATimeoutAndOfASlowResponse(): void
    {
        $http = new MockHttpClient(
            // A timeout: no response, so no status code and no headers (verified live).
            new Response(200, '{"id":"350723639116339234","status":"Resolved","cause":333333,"reason":"Connection Timeout","duration":166825,'
                . '"startedAt":"2026-08-25T19:30:31.695Z","resolvedAt":"2026-08-27T17:50:56.695Z","rootCause":{"url":"GET https://cp.gosuccess.io/api/status",'
                . '"requestHeaders":{"Connection":"close"},"responseHeaders":{},"httpResponseCode":null,"responseDownloadUrl":"","assertionDiagnostics":null}}'),
            // A slow response: an empty url and null headers (verified live).
            new Response(200, '{"id":"349291092551826516","status":"Resolved","cause":0,"reason":"Response time","duration":785,'
                . '"startedAt":"2026-08-21T20:38:05.979Z","resolvedAt":"2026-08-21T20:51:10.979Z","rootCause":{"url":"","requestHeaders":null,'
                . '"responseHeaders":null,"httpResponseCode":null,"responseDownloadUrl":null,"assertionDiagnostics":null}}'),
        );
        $incidents = self::client($http)->incidents;

        $timeout = $incidents->get('350723639116339234')->rootCause;
        self::assertNotNull($timeout);
        self::assertSame(['Connection' => 'close'], $timeout->requestHeaders);
        self::assertSame([], $timeout->responseHeaders);
        self::assertNull($timeout->httpResponseCode);

        $slow = $incidents->get('349291092551826516');
        self::assertSame(0, $slow->cause);
        self::assertNotNull($slow->rootCause);
        self::assertSame('', $slow->rootCause->url);
        self::assertSame([], $slow->rootCause->requestHeaders);
        self::assertSame([], $slow->rootCause->responseHeaders);
        self::assertNull($slow->rootCause->httpResponseCode);
        self::assertNull($slow->rootCause->responseDownloadUrl);
    }

    public function testReadsTheAssertionDiagnosticsOfAnApiMonitor(): void
    {
        // Shaped as the specification documents it: the account had no API
        // monitor incident, so the API never sent one.
        $http = new MockHttpClient(new Response(200, '{"id":"360000000000000001","status":"Resolved","cause":0,"reason":"Assertion failed","duration":60,'
            . '"startedAt":"2026-09-20T10:00:00.000Z","resolvedAt":"2026-09-20T10:01:00.000Z","rootCause":{"url":"GET https://example.com/api",'
            . '"requestHeaders":{},"responseHeaders":{},"httpResponseCode":200,"responseDownloadUrl":null,"assertionDiagnostics":{"version":1,'
            . '"logic":"AND","summary":{"passed":false,"total":2,"passedCount":1,"failedCount":1},"results":[{"index":0,"property":"$.status",'
            . '"source":"body_json","selector":"$.status","comparison":"equals","passed":false,"actualTypes":["string","future_type"],'
            . '"actualSummary":"\"degraded\"","targetType":"string","targetSummary":"\"ok\"","selectedCount":1,"failedCount":1,'
            . '"failingSamples":[{"path":"$.status","actualSummary":"\"degraded\""}],"failureReason":"comparison_failed","truncated":false,'
            . '"redacted":false},{"index":1,"property":"status_code","source":"status_code","comparison":"equals","passed":true,'
            . '"truncated":false,"redacted":false}]}}}'));

        $diagnostics = self::client($http)->incidents->get('360000000000000001')->rootCause?->assertionDiagnostics;

        self::assertNotNull($diagnostics);
        self::assertSame(1, $diagnostics->version);
        self::assertSame(AssertionLogic::And, $diagnostics->logic);
        self::assertFalse($diagnostics->summary->passed);
        self::assertSame([2, 1, 1], [$diagnostics->summary->total, $diagnostics->summary->passedCount, $diagnostics->summary->failedCount]);
        self::assertCount(2, $diagnostics->results);

        $failed = $diagnostics->results[0];
        self::assertSame(AssertionSource::BodyJson, $failed->source);
        self::assertSame(AssertionComparison::Equals, $failed->comparison);
        // An unknown value is dropped rather than failing the response.
        self::assertSame([AssertionValueType::String], $failed->actualTypes);
        self::assertSame(AssertionValueType::String, $failed->targetType);
        self::assertSame('"degraded"', $failed->actualSummary);
        self::assertSame('$.status', $failed->failingSamples[0]->path);
        self::assertSame(AssertionFailureReason::ComparisonFailed, $failed->failureReason);

        $passed = $diagnostics->results[1];
        self::assertTrue($passed->passed);
        self::assertSame(AssertionSource::StatusCode, $passed->source);
        self::assertSame([], $passed->failingSamples);
        self::assertNull($passed->failureReason);
    }

    public function testEncodesTheIdAsOnePathSegmentAndReportsAnUnknownOne(): void
    {
        $http = new MockHttpClient(
            new Response(404, '{"message":"Incident 1 not found","code":"000-004"}'),
            new Response(404, '{"message":"Incident a/b not found","code":"000-004"}'),
        );
        $incidents = self::client($http)->incidents;

        try {
            $incidents->get('1');
            self::fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            self::assertSame('000-004', $e->errorCode);
            self::assertStringEndsWith('HTTP 404: Incident 1 not found (000-004)', $e->getMessage());
        }

        $this->expectException(NotFoundException::class);

        try {
            $incidents->activityLog('a/b');
        } finally {
            self::assertSame('https://api.uptimerobot.com/v3/incidents/a%2Fb/activity-log', $http->requests[1]->uri);
        }
    }

    public function testRejectsAnIdThatAddressesAnotherEndpoint(): void
    {
        // GET /incidents/ answers with the list, which get() read as an
        // incident with the ID '' (verified live); cURL turns incidents/. into
        // the same request.
        $http = new MockHttpClient(new Response(200, '{"data":[' . self::DOWNTIME . ']}'));
        $incidents = self::client($http)->incidents;

        foreach (['', '.', '..'] as $id) {
            try {
                $incidents->get($id);
                self::fail("Expected an InvalidArgumentException for the ID \"{$id}\".");
            } catch (InvalidArgumentException) {
            }
        }

        self::assertSame(0, $http->callCount());
    }

    public function testReadsTheActivityLogByEntryType(): void
    {
        // Entries as the API sent them (verified live), plus a comment and an
        // entry type this client does not know, both shaped per the specification.
        $http = new MockHttpClient(new Response(200, '{"nextLink":null,"data":['
            . '{"type":"COMMENT","date":"2026-08-21T21:00:00.000Z","region":null,"commentId":42,"commentFullName":"Jane Doe","commentEmail":"jane@example.com"},'
            . '{"type":"NOTIFICATION","date":"2026-08-21T20:51:11.000Z","region":"oc","sentToFullName":"Phone of Jane",'
            . '"sentToValue":"device-token","notificationType":"MobileApp","notificationStatus":"SUCCESS"},'
            . '{"type":"STATUS_UPDATE","date":"2026-08-21T20:51:10.979Z","region":"oc","alertLogType":"Up","cause":0,"reason":"Monitor is UP"},'
            . '{"type":"NOTIFICATION","date":"2026-08-21T20:38:06.000Z","region":"oc","sentToFullName":"Phone of Jane",'
            . '"sentToValue":"device-token","notificationType":"MobileApp","notificationStatus":"NOT_DELIVERED"},'
            . '{"type":"STATUS_UPDATE","date":"2026-08-21T20:38:05.000Z","region":"oc","alertLogType":"Slow","cause":0,"reason":"Response time",'
            . '"remoteNode":{"id":0,"status":true,"IP":"3.105.133.239","IPv6":"2406:da1c:9c8:dc02:7ae1:f2ea:ab91:2fde","city":"Sydney",'
            . '"country":"Australia","privateIP":null},"responseTime":3503},'
            . '{"type":"STATUS_UPDATE","date":"2026-08-25T19:29:33.000Z","region":"na","alertLogType":"Down","cause":333333,"reason":"Connection Timeout",'
            . '"remoteNode":{"id":0,"status":true,"IP":"178.156.181.172","IPv6":"10.0.4.16","city":"Ashburn","country":"USA","privateIP":null}},'
            . '{"type":"ESCALATION","date":"2026-08-21T20:40:00.000Z","level":2}'
            . ']}'));

        $entries = self::client($http)->incidents->activityLog('349291092551826516');

        self::assertSame('https://api.uptimerobot.com/v3/incidents/349291092551826516/activity-log', $http->requests[0]->uri);
        self::assertCount(7, $entries);

        $comment = $entries[0];
        self::assertInstanceOf(CommentActivity::class, $comment);
        self::assertSame(42, $comment->commentId);
        self::assertSame('Jane Doe', $comment->commentFullName);
        self::assertNull($comment->region);

        $notification = $entries[1];
        self::assertInstanceOf(NotificationActivity::class, $notification);
        self::assertSame('2026-08-21T20:51:11+00:00', $notification->date?->format(\DATE_ATOM));
        self::assertSame(Region::Oceania, $notification->region);
        self::assertSame('Phone of Jane', $notification->sentToFullName);
        self::assertSame('device-token', $notification->sentToValue);
        self::assertSame('MobileApp', $notification->notificationType);
        self::assertSame(AlertDeliveryStatus::Success, $notification->notificationStatus);

        // The Up entry that ends the incident has no remote node (verified live).
        $up = $entries[2];
        self::assertInstanceOf(StatusUpdateActivity::class, $up);
        self::assertSame('Up', $up->alertLogType);
        self::assertSame(0, $up->cause);
        self::assertSame('Monitor is UP', $up->reason);
        self::assertNull($up->remoteNode);
        self::assertNull($up->responseTime);
        self::assertNull($up->incidentStatus);

        $undelivered = $entries[3];
        self::assertInstanceOf(NotificationActivity::class, $undelivered);
        self::assertSame(AlertDeliveryStatus::NotDelivered, $undelivered->notificationStatus);

        $slow = $entries[4];
        self::assertInstanceOf(StatusUpdateActivity::class, $slow);
        self::assertSame('Slow', $slow->alertLogType);
        self::assertSame(3503, $slow->responseTime);
        self::assertNotNull($slow->remoteNode);
        self::assertSame(0, $slow->remoteNode->id);
        self::assertTrue($slow->remoteNode->status);
        self::assertSame('3.105.133.239', $slow->remoteNode->ip);
        self::assertSame('2406:da1c:9c8:dc02:7ae1:f2ea:ab91:2fde', $slow->remoteNode->ipv6);
        self::assertSame('Sydney', $slow->remoteNode->city);
        self::assertSame('Australia', $slow->remoteNode->country);
        self::assertNull($slow->remoteNode->privateIp);

        $down = $entries[5];
        self::assertInstanceOf(StatusUpdateActivity::class, $down);
        self::assertSame(Region::NorthAmerica, $down->region);
        self::assertSame(333333, $down->cause);
        self::assertNull($down->responseTime);
        // Some nodes report a private IPv4 address as IPv6 (verified live).
        self::assertSame('10.0.4.16', $down->remoteNode?->ipv6);

        $unknown = $entries[6];
        self::assertInstanceOf(UnknownActivityLogEntry::class, $unknown);
        self::assertSame('ESCALATION', $unknown->type);
        self::assertSame(['type' => 'ESCALATION', 'date' => '2026-08-21T20:40:00.000Z', 'level' => 2], $unknown->data);

        // Every kind has a date and a region, the unknown one included.
        self::assertSame(
            ['21:00:00', '20:51:11', '20:51:10', '20:38:06', '20:38:05', '19:29:33', '20:40:00'],
            array_map(static fn(ActivityLogEntry $entry): ?string => $entry->date?->format('H:i:s'), $entries),
        );
        self::assertSame([null, 'oc', 'oc', 'oc', 'oc', 'na', null], array_map(static fn(ActivityLogEntry $entry): ?string => $entry->region?->value, $entries));
    }

    public function testReadsTheSentAlerts(): void
    {
        $http = new MockHttpClient(
            new Response(200, '{"data":[{"timestamp":"2026-08-25T19:30:31.000Z","recipientName":"Phone of Jane","recipientValue":"device-token",'
                . '"channelType":"MobileApp","status":"SUCCESS"},{"timestamp":"2026-08-27T17:50:57.000Z","recipientName":"Phone of Jane",'
                . '"recipientValue":"device-token","channelType":"MobileApp","status":"NOT_DELIVERED"}]}'),
            new Response(200, '{"data":[]}'),
        );
        $incidents = self::client($http)->incidents;

        $alerts = $incidents->alerts('350723639116339234');

        self::assertSame('https://api.uptimerobot.com/v3/incidents/350723639116339234/alerts', $http->requests[0]->uri);
        self::assertSame(['2026-08-25T19:30:31+00:00', '2026-08-27T17:50:57+00:00'], array_map(
            static fn(SentAlert $alert): ?string => $alert->timestamp?->format(\DATE_ATOM),
            $alerts,
        ));
        self::assertSame('Phone of Jane', $alerts[0]->recipientName);
        self::assertSame('device-token', $alerts[0]->recipientValue);
        self::assertSame('MobileApp', $alerts[0]->channelType);
        self::assertSame(AlertDeliveryStatus::Success, $alerts[0]->status);
        self::assertSame(AlertDeliveryStatus::NotDelivered, $alerts[1]->status);

        self::assertSame([], $incidents->alerts('358532761126055015'));
    }

    private static function client(MockHttpClient $http): UptimeRobot
    {
        return new UptimeRobot('secret', httpClient: $http);
    }
}
