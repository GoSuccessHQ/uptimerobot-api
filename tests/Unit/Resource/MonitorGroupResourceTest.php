<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Resource;

use GoSuccess\UptimeRobot\Exception\BadRequestException;
use GoSuccess\UptimeRobot\Exception\NotFoundException;
use GoSuccess\UptimeRobot\Http\Response;
use GoSuccess\UptimeRobot\Model\MonitorGroup;
use GoSuccess\UptimeRobot\Model\MonitorGroupCreate;
use GoSuccess\UptimeRobot\Model\MonitorGroupUpdate;
use GoSuccess\UptimeRobot\Resource\MonitorGroupResource;
use GoSuccess\UptimeRobot\Tests\Support\MockHttpClient;
use GoSuccess\UptimeRobot\UptimeRobot;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The monitor groups resource against a mocked transport, with the payloads and
 * error bodies the live API sent.
 */
#[CoversClass(MonitorGroupResource::class)]
final class MonitorGroupResourceTest extends TestCase
{
    /** A group as GET /monitor-groups/{id} returned it (verified live). */
    private const string GROUP = '{"id":29454,"name":"Intern","createdAt":"2026-08-18T12:57:15.000Z","updatedAt":"2026-08-18T12:57:15.000Z"}';

    public function testReadsAPageAsTheApiSendsIt(): void
    {
        // The last page reports nextLink null (verified live).
        $http = new MockHttpClient(new Response(200, '{"data":[' . self::GROUP . ',{"id":29506,"name":"Nodes","createdAt":"2026-08-18T16:07:26.000Z",'
            . '"updatedAt":"2026-08-18T16:07:26.000Z"}],"nextLink":null}'));

        $page = self::client($http)->monitorGroups->list();

        self::assertSame('GET', $http->requests[0]->method->value);
        self::assertSame('https://api.uptimerobot.com/v3/monitor-groups', $http->requests[0]->uri);
        self::assertSame([29454, 29506], array_map(static fn(MonitorGroup $group): int => $group->id, $page->items));
        self::assertSame('Intern', $page->items[0]->name);
        self::assertSame('2026-08-18T12:57:15.000+00:00', $page->items[0]->createdAt?->format('Y-m-d\TH:i:s.vP'));
        self::assertSame('2026-08-18T16:07:26+00:00', $page->items[1]->updatedAt?->format(\DATE_ATOM));
        self::assertNull($page->next);
    }

    public function testIteratesOverAllGroupsWithTheIdOfTheLastGroupAsCursor(): void
    {
        $http = new MockHttpClient(
            new Response(200, '{"data":[' . self::GROUP . '],"nextLink":"https://api.uptimerobot.com/v3/monitor-groups/?cursor=29454"}'),
            new Response(200, '{"data":[{"id":30542,"name":"WP1 (Legacy)","createdAt":"2026-08-31T06:26:21.000Z","updatedAt":"2026-08-31T06:26:21.000Z"}],"nextLink":null}'),
        );

        $names = [];

        foreach (self::client($http)->monitorGroups->all() as $group) {
            $names[] = $group->name;
        }

        self::assertSame(['Intern', 'WP1 (Legacy)'], $names);
        self::assertSame('https://api.uptimerobot.com/v3/monitor-groups', $http->requests[0]->uri);
        // The cursor returns the groups after the one it names (verified live).
        self::assertSame('https://api.uptimerobot.com/v3/monitor-groups?cursor=29454', $http->requests[1]->uri);
    }

    public function testReadsAGroupAndReportsAMissingOne(): void
    {
        $http = new MockHttpClient(
            new Response(200, self::GROUP),
            // GET /monitor-groups/0 answers the same (verified live).
            new Response(404, '{"message":"Monitor group not found","code":"000-004"}'),
        );
        $groups = self::client($http)->monitorGroups;

        self::assertSame('Intern', $groups->get(29454)->name);
        self::assertSame('https://api.uptimerobot.com/v3/monitor-groups/29454', $http->requests[0]->uri);

        try {
            $groups->get(0);
            self::fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            self::assertSame('000-004', $e->errorCode);
            self::assertStringEndsWith('HTTP 404: Monitor group not found (000-004)', $e->getMessage());
        }
    }

    public function testCreatesAGroupWithTheMonitorsToMoveIntoIt(): void
    {
        $http = new MockHttpClient(new Response(201, self::GROUP));

        $group = self::client($http)->monitorGroups->create(new MonitorGroupCreate('Intern', monitorIds: [803767164, 803872200], groupIds: [0]));

        $request = $http->requests[0];
        self::assertSame('POST', $request->method->value);
        self::assertSame('https://api.uptimerobot.com/v3/monitor-groups', $request->uri);
        self::assertSame('application/json', $request->headers['Content-Type'] ?? null);
        self::assertSame('{"name":"Intern","monitorIds":[803767164,803872200],"groupIds":[0]}', $request->body);
        self::assertSame(29454, $group->id);
    }

    public function testSendsOnlyTheNameThatIsSet(): void
    {
        $http = new MockHttpClient(new Response(200, self::GROUP), new Response(200, self::GROUP));
        $groups = self::client($http)->monitorGroups;

        $groups->update(29454, new MonitorGroupUpdate(name: 'Intern'));
        $groups->update(29454, new MonitorGroupUpdate());

        self::assertSame('PATCH', $http->requests[0]->method->value);
        self::assertSame('https://api.uptimerobot.com/v3/monitor-groups/29454', $http->requests[0]->uri);
        self::assertSame('{"name":"Intern"}', $http->requests[0]->body);
        self::assertSame('{}', $http->requests[1]->body);
    }

    public function testDeletesAGroupAndMovesItsMonitors(): void
    {
        // 204 without a body, as documented.
        $http = new MockHttpClient(new Response(204), new Response(204));
        $groups = self::client($http)->monitorGroups;

        $groups->delete(29454);
        $groups->delete(29454, monitorsNewGroupId: 29506);

        self::assertSame('DELETE', $http->requests[0]->method->value);
        self::assertSame('https://api.uptimerobot.com/v3/monitor-groups/29454', $http->requests[0]->uri);
        self::assertNull($http->requests[0]->body);
        self::assertSame('https://api.uptimerobot.com/v3/monitor-groups/29454?monitorsNewGroupId=29506', $http->requests[1]->uri);
    }

    public function testReportsTheErrorsOfADeletion(): void
    {
        $http = new MockHttpClient(
            new Response(400, '{"message":["monitorsNewGroupId must be a positive number"],"error":"Bad Request","statusCode":400}'),
            new Response(404, '{"code":"000-004","message":"Resource you were trying to access is not found."}'),
        );
        $groups = self::client($http)->monitorGroups;

        try {
            $groups->delete(29454, monitorsNewGroupId: 0);
            self::fail('Expected a BadRequestException.');
        } catch (BadRequestException $e) {
            self::assertSame(400, $e->statusCode);
            self::assertStringEndsWith('HTTP 400: monitorsNewGroupId must be a positive number', $e->getMessage());
        }

        try {
            $groups->delete(999999999);
            self::fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            self::assertSame('000-004', $e->errorCode);
        }
    }

    private static function client(MockHttpClient $http): UptimeRobot
    {
        return new UptimeRobot('secret', httpClient: $http);
    }
}
