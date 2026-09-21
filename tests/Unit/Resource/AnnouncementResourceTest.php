<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Resource;

use DateTimeImmutable;
use DateTimeZone;
use GoSuccess\UptimeRobot\Enum\AnnouncementStatus;
use GoSuccess\UptimeRobot\Enum\AnnouncementStatusFilter;
use GoSuccess\UptimeRobot\Enum\AnnouncementType;
use GoSuccess\UptimeRobot\Exception\ApiException;
use GoSuccess\UptimeRobot\Exception\ForbiddenException;
use GoSuccess\UptimeRobot\Http\Response;
use GoSuccess\UptimeRobot\Model\AnnouncementCreate;
use GoSuccess\UptimeRobot\Model\AnnouncementUpdate;
use GoSuccess\UptimeRobot\Resource\AnnouncementResource;
use GoSuccess\UptimeRobot\Tests\Support\MockHttpClient;
use GoSuccess\UptimeRobot\UptimeRobot;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The announcements resource against a mocked transport. The account lacks
 * the plan feature psp-subscribers, so the announcement below follows the
 * specification; the error bodies are the ones the live API sent.
 */
#[CoversClass(AnnouncementResource::class)]
final class AnnouncementResourceTest extends TestCase
{
    /** An announcement shaped like PspAnnouncementResponseDto (not observable live). */
    private const string ANNOUNCEMENT = '{"id":3301,"pspId":81234,"userId":1234567,"title":"Scheduled Maintenance",'
        . '"content":"We will be performing scheduled maintenance on our servers.","status":"Pending","type":"Maintenance",'
        . '"deliveryStatus":"InQueue","startDate":"2026-10-01T22:00:00.000Z","endDate":null,"creationDate":"2026-09-21T12:00:00.000Z",'
        . '"submitDate":null,"sentCount":0}';

    private const string PLAN = '{"message":"Feature psp-subscribers is not enabled in your plan.","code":"000-003"}';

    public function testReadsAnAnnouncement(): void
    {
        $http = new MockHttpClient(new Response(200, self::ANNOUNCEMENT));

        $announcement = self::client($http)->announcements->get(81234, 3301);

        self::assertSame('GET', $http->requests[0]->method->value);
        self::assertSame('https://api.uptimerobot.com/v3/psps/81234/announcements/3301', $http->requests[0]->uri);
        self::assertSame(3301, $announcement->id);
        self::assertSame(81234, $announcement->pspId);
        self::assertSame('Scheduled Maintenance', $announcement->title);
        // Strings: the response casing is not documented.
        self::assertSame('Pending', $announcement->status);
        self::assertSame('Maintenance', $announcement->type);
        self::assertSame('InQueue', $announcement->deliveryStatus);
        self::assertSame('2026-10-01T22:00:00+00:00', $announcement->startDate?->format(\DATE_ATOM));
        self::assertNull($announcement->endDate);
        self::assertSame('2026-09-21T12:00:00+00:00', $announcement->creationDate?->format(\DATE_ATOM));
        self::assertNull($announcement->submitDate);
        self::assertSame(0, $announcement->sentCount);
    }

    public function testListsWithTheUpperCaseStatusFilterAcrossPages(): void
    {
        $http = new MockHttpClient(
            new Response(200, '{"data":[' . self::ANNOUNCEMENT . '],"nextLink":"https://api.uptimerobot.com/v3/psps/81234/announcements/?status=PENDING&cursor=3301"}'),
            new Response(200, '{"data":[],"nextLink":null}'),
        );

        $announcements = iterator_to_array(self::client($http)->announcements->all(81234, AnnouncementStatusFilter::Pending), false);

        self::assertCount(1, $announcements);
        self::assertSame('https://api.uptimerobot.com/v3/psps/81234/announcements?status=PENDING', $http->requests[0]->uri);
        self::assertSame('https://api.uptimerobot.com/v3/psps/81234/announcements?cursor=3301&status=PENDING', $http->requests[1]->uri);
    }

    public function testCreatesAnAnnouncement(): void
    {
        $http = new MockHttpClient(new Response(201, self::ANNOUNCEMENT));

        $announcement = self::client($http)->announcements->create(81234, new AnnouncementCreate(
            title: 'Scheduled Maintenance',
            content: 'We will be performing scheduled maintenance on our servers.',
            status: AnnouncementStatus::Pending,
            type: AnnouncementType::Maintenance,
            startDate: new DateTimeImmutable('2026-10-02 00:00:00', new DateTimeZone('Europe/Berlin')),
            endDate: null,
        ));

        $request = $http->requests[0];
        self::assertSame('POST', $request->method->value);
        self::assertSame('https://api.uptimerobot.com/v3/psps/81234/announcements', $request->uri);
        self::assertSame('application/json', $request->headers['Content-Type'] ?? null);
        self::assertSame(
            '{"title":"Scheduled Maintenance","content":"We will be performing scheduled maintenance on our servers.",'
            . '"status":"Pending","type":"Maintenance","startDate":"2026-10-01T22:00:00.000Z","endDate":null}',
            $request->body,
        );
        self::assertSame(3301, $announcement->id);
    }

    public function testUpdatesOnlyTheSetProperties(): void
    {
        $http = new MockHttpClient(new Response(200, self::ANNOUNCEMENT));

        self::client($http)->announcements->update(81234, 3301, new AnnouncementUpdate(status: AnnouncementStatus::Archived));

        self::assertSame('PATCH', $http->requests[0]->method->value);
        self::assertSame('https://api.uptimerobot.com/v3/psps/81234/announcements/3301', $http->requests[0]->uri);
        self::assertSame('{"status":"Archived"}', $http->requests[0]->body);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function pinActions(): iterable
    {
        yield 'pin' => ['pin'];
        yield 'unpin' => ['unpin'];
    }

    #[DataProvider('pinActions')]
    public function testPinsAndUnpinsWithAnEmptyJsonObject(string $action): void
    {
        $http = new MockHttpClient(new Response(200));
        $announcements = self::client($http)->announcements;

        $action === 'pin' ? $announcements->pin(81234, 3301) : $announcements->unpin(81234, 3301);

        $request = $http->requests[0];
        self::assertSame('POST', $request->method->value);
        self::assertSame("https://api.uptimerobot.com/v3/psps/81234/announcements/3301/{$action}", $request->uri);
        // Without it the API answers 415 "Content-Type must be application/json" (verified live).
        self::assertSame('application/json', $request->headers['Content-Type'] ?? null);
        self::assertSame('{}', $request->body);
    }

    public function testReportsTheMissingPlanFeature(): void
    {
        // The API's answer to every announcement request of the test account (verified live).
        $http = new MockHttpClient(new Response(403, self::PLAN));

        try {
            self::client($http)->announcements->pin(999999999, 1);
            self::fail('Expected a ForbiddenException.');
        } catch (ForbiddenException $e) {
            self::assertSame('000-003', $e->errorCode);
            self::assertStringContainsString('Feature psp-subscribers is not enabled in your plan.', $e->getMessage());
        }
    }

    public function testReportsAnUnsupportedMediaType(): void
    {
        // What the API sends for a pin without a JSON body (verified live), should it ever happen.
        $http = new MockHttpClient(new Response(415, '{"message":"Content-Type must be application/json","error":"Unsupported Media Type","statusCode":415}'));

        try {
            self::client($http)->announcements->unpin(81234, 3301);
            self::fail('Expected an ApiException.');
        } catch (ApiException $e) {
            self::assertSame(415, $e->statusCode);
            self::assertStringContainsString('Content-Type must be application/json', $e->getMessage());
        }
    }

    private static function client(MockHttpClient $http): UptimeRobot
    {
        return new UptimeRobot('secret', httpClient: $http);
    }
}
