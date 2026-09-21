<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Resource;

use GoSuccess\UptimeRobot\Enum\MaintenanceWindowInterval;
use GoSuccess\UptimeRobot\Enum\MaintenanceWindowStatus;
use GoSuccess\UptimeRobot\Exception\BadRequestException;
use GoSuccess\UptimeRobot\Exception\ForbiddenException;
use GoSuccess\UptimeRobot\Exception\NotFoundException;
use GoSuccess\UptimeRobot\Http\Response;
use GoSuccess\UptimeRobot\Model\MaintenanceWindow;
use GoSuccess\UptimeRobot\Model\MaintenanceWindowCreate;
use GoSuccess\UptimeRobot\Model\MaintenanceWindowUpdate;
use GoSuccess\UptimeRobot\Resource\MaintenanceWindowResource;
use GoSuccess\UptimeRobot\Tests\Support\MockHttpClient;
use GoSuccess\UptimeRobot\UptimeRobot;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The maintenance windows resource against a mocked transport. The account had
 * no windows, so the window below follows the specification; the empty list
 * and the error bodies are the ones the live API sent.
 */
#[CoversClass(MaintenanceWindowResource::class)]
final class MaintenanceWindowResourceTest extends TestCase
{
    /** A weekly window shaped like MaintenanceWindowDto (not observable live). */
    private const string WINDOW = '{"id":5501,"userId":1234567,"name":"Friday deployments","interval":"weekly","date":null,"time":"22:30:00",'
        . '"duration":90,"autoAddMonitors":false,"monitorIds":[803767164,803872200],"days":[5],"status":"active","created":"2026-09-01T08:00:00.000Z"}';

    public function testReadsAWindowAndItsSchedule(): void
    {
        $http = new MockHttpClient(new Response(200, self::WINDOW));

        $window = self::client($http)->maintenanceWindows->get(5501);

        self::assertSame('GET', $http->requests[0]->method->value);
        self::assertSame('https://api.uptimerobot.com/v3/maintenance-windows/5501', $http->requests[0]->uri);
        self::assertSame(5501, $window->id);
        self::assertSame(MaintenanceWindowInterval::Weekly, $window->interval);
        self::assertNull($window->date);
        self::assertSame('22:30:00', $window->time);
        self::assertSame(90, $window->duration);
        self::assertSame([5], $window->days);
        self::assertSame([803767164, 803872200], $window->monitorIds);
        self::assertFalse($window->autoAddMonitors);
        self::assertSame(MaintenanceWindowStatus::Active, $window->status);
        self::assertSame('2026-09-01T08:00:00+00:00', $window->created?->format(\DATE_ATOM));
    }

    public function testReadsTheEmptyListWithoutNextLink(): void
    {
        // Verified live on an account without windows.
        $http = new MockHttpClient(new Response(200, '{"data":[]}'));

        $page = self::client($http)->maintenanceWindows->list();

        self::assertSame('https://api.uptimerobot.com/v3/maintenance-windows', $http->requests[0]->uri);
        self::assertSame([], $page->items);
        self::assertNull($page->next);
    }

    public function testIteratesOverAllWindowsWithTheIdOfTheLastWindowAsCursor(): void
    {
        $http = new MockHttpClient(
            new Response(200, '{"data":[' . self::WINDOW . '],"nextLink":"https://api.uptimerobot.com/v3/maintenance-windows?cursor=5501"}'),
            new Response(200, '{"data":[' . str_replace('5501', '5502', self::WINDOW) . ']}'),
        );

        $ids = array_map(
            static fn(MaintenanceWindow $window): int => $window->id,
            iterator_to_array(self::client($http)->maintenanceWindows->all(), false),
        );

        self::assertSame([5501, 5502], $ids);
        self::assertSame('https://api.uptimerobot.com/v3/maintenance-windows?cursor=5501', $http->requests[1]->uri);
    }

    public function testCreatesAWindowWithItsRequiredScheduleFirst(): void
    {
        $http = new MockHttpClient(new Response(201, self::WINDOW));

        $window = self::client($http)->maintenanceWindows->create(new MaintenanceWindowCreate(
            name: 'Release',
            interval: MaintenanceWindowInterval::Once,
            time: '22:30:00',
            duration: 90,
            date: '2026-09-25',
            monitorIds: [803767164, 803872200],
        ));

        $request = $http->requests[0];
        self::assertSame('POST', $request->method->value);
        self::assertSame('https://api.uptimerobot.com/v3/maintenance-windows', $request->uri);
        self::assertSame('application/json', $request->headers['Content-Type'] ?? null);
        self::assertSame(
            '{"name":"Release","interval":"once","time":"22:30:00","duration":90,"date":"2026-09-25","monitorIds":[803767164,803872200]}',
            $request->body,
        );
        self::assertSame(5501, $window->id);
    }

    public function testCreatesARecurringWindowWithoutADate(): void
    {
        $http = new MockHttpClient(new Response(201, self::WINDOW));

        // As the official Terraform provider creates recurring windows.
        self::client($http)->maintenanceWindows->create(new MaintenanceWindowCreate(
            name: 'Friday deployments',
            interval: MaintenanceWindowInterval::Weekly,
            time: '22:30:00',
            duration: 90,
            days: [5],
        ));

        self::assertSame('{"name":"Friday deployments","interval":"weekly","time":"22:30:00","duration":90,"days":[5]}', $http->requests[0]->body);
    }

    public function testUpdatesOnlyWhatIsSet(): void
    {
        $http = new MockHttpClient(new Response(200, self::WINDOW), new Response(200, self::WINDOW));
        $windows = self::client($http)->maintenanceWindows;

        $windows->update(5501, new MaintenanceWindowUpdate(status: MaintenanceWindowStatus::Paused));
        $windows->update(5501, new MaintenanceWindowUpdate(interval: MaintenanceWindowInterval::Monthly, days: [1, -1], monitorIds: [], autoAddMonitors: true));

        self::assertSame('PATCH', $http->requests[0]->method->value);
        self::assertSame('https://api.uptimerobot.com/v3/maintenance-windows/5501', $http->requests[0]->uri);
        self::assertSame('{"status":"paused"}', $http->requests[0]->body);
        self::assertSame('{"interval":"monthly","days":[1,-1],"monitorIds":[],"autoAddMonitors":true}', $http->requests[1]->body);
    }

    public function testDeletesAWindow(): void
    {
        // 204 without a body, as documented.
        $http = new MockHttpClient(new Response(204));

        self::client($http)->maintenanceWindows->delete(5501);

        self::assertSame('DELETE', $http->requests[0]->method->value);
        self::assertSame('https://api.uptimerobot.com/v3/maintenance-windows/5501', $http->requests[0]->uri);
        self::assertNull($http->requests[0]->body);
    }

    public function testReportsUnknownWindowsAndThoseOfOtherAccounts(): void
    {
        // The bodies the live API sent for GET 999999999, GET 1 and DELETE 999999999.
        $http = new MockHttpClient(
            new Response(404, '{"message":"Maintenance window not found","code":"000-004"}'),
            new Response(403, '{"message":"Not belongs to user","code":"000-006"}'),
            new Response(404, '{"code":"000-004","message":"Resource you were trying to access is not found."}'),
        );
        $windows = self::client($http)->maintenanceWindows;

        try {
            $windows->get(999999999);
            self::fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            self::assertSame('000-004', $e->errorCode);
        }

        try {
            $windows->get(1);
            self::fail('Expected a ForbiddenException.');
        } catch (ForbiddenException $e) {
            self::assertSame('000-006', $e->errorCode);
            self::assertStringEndsWith('HTTP 403: Not belongs to user (000-006)', $e->getMessage());
        }

        try {
            $windows->delete(999999999);
            self::fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            self::assertSame('000-004', $e->errorCode);
        }
    }

    public function testReportsTheValidationErrorsOfTheApi(): void
    {
        // Verified live for a PATCH with "time": "25:00:00" and "duration": 0.
        $http = new MockHttpClient(new Response(400, '{"message":["time must match /^(?:[01]\\\d|2[0-3]):[0-5]\\\d:[0-5]\\\d$/ regular expression",'
            . '"duration must not be less than 1"],"error":"Bad Request","statusCode":400}'));

        try {
            self::client($http)->maintenanceWindows->update(999999999, new MaintenanceWindowUpdate(time: '25:00:00', duration: 0));
            self::fail('Expected a BadRequestException.');
        } catch (BadRequestException $e) {
            self::assertSame(400, $e->statusCode);
            self::assertStringContainsString('duration must not be less than 1', $e->getMessage());
            self::assertStringContainsString('time must match', $e->getMessage());
        }

        self::assertSame('{"time":"25:00:00","duration":0}', $http->requests[0]->body);
    }

    private static function client(MockHttpClient $http): UptimeRobot
    {
        return new UptimeRobot('secret', httpClient: $http);
    }
}
