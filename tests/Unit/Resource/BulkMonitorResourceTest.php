<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Resource;

use Closure;
use GoSuccess\UptimeRobot\Enum\BulkOperationStatus;
use GoSuccess\UptimeRobot\Enum\Region;
use GoSuccess\UptimeRobot\Exception\BadRequestException;
use GoSuccess\UptimeRobot\Http\Response;
use GoSuccess\UptimeRobot\Model\BulkMonitorUpdate;
use GoSuccess\UptimeRobot\Model\RegionalData;
use GoSuccess\UptimeRobot\Resource\BulkMonitorResource;
use GoSuccess\UptimeRobot\Tests\Support\MockHttpClient;
use GoSuccess\UptimeRobot\UptimeRobot;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The hand-written bulk operations against a mocked transport. They were not
 * run live: they would change the monitors of the account.
 */
#[CoversClass(BulkMonitorResource::class)]
final class BulkMonitorResourceTest extends TestCase
{
    private const string RESULT = '{"results":[{"monitorId":804045264,"monitorName":"Shop","status":"success"},'
        . '{"monitorId":804045265,"monitorName":"Blog","status":"error","error":"Monitor limit reached","code":"000-007"}],'
        . '"totalSuccess":1,"totalError":1}';

    /**
     * @return iterable<string, array{string, int|null, int|null, string}>
     */
    public static function selections(): iterable
    {
        yield 'pause a group' => ['pause', 12, null, '{"groupId":12}'];
        yield 'pause the monitors in no group' => ['pause', 0, null, '{"groupId":0}'];
        yield 'start a tag' => ['start', null, 42, '{"tagId":42}'];
        yield 'start a tag in a group' => ['start', 12, 42, '{"groupId":12,"tagId":42}'];
    }

    #[DataProvider('selections')]
    public function testPausesAndStartsTheSelectedMonitors(string $action, ?int $groupId, ?int $tagId, string $body): void
    {
        $http = new MockHttpClient(new Response(201, self::RESULT));
        $bulk = self::client($http)->bulkMonitors;

        $result = $action === 'pause' ? $bulk->pause($groupId, $tagId) : $bulk->start($groupId, $tagId);

        $request = $http->requests[0];
        self::assertSame('POST', $request->method->value);
        self::assertSame("https://api.uptimerobot.com/v3/monitors/bulk/{$action}", $request->uri);
        self::assertSame('application/json', $request->headers['Content-Type'] ?? null);
        self::assertSame($body, $request->body);
        self::assertSame(1, $result->totalSuccess);
        self::assertSame(1, $result->totalError);
    }

    public function testChangesTheSelectedMonitors(): void
    {
        $http = new MockHttpClient(new Response(201, self::RESULT));

        $result = self::client($http)->bulkMonitors->update(
            new BulkMonitorUpdate(interval: 300, checkSslErrors: true, regionalData: new RegionalData(region: [Region::Europe]), customFields: ['env' => 'prod']),
            groupId: 12,
        );

        self::assertSame('https://api.uptimerobot.com/v3/monitors/bulk/update', $http->requests[0]->uri);
        self::assertSame(
            '{"groupId":12,"interval":300,"checkSSLErrors":true,"regionalData":{"REGION":["eu"]},"customFields":{"env":"prod"}}',
            $http->requests[0]->body,
        );
        self::assertCount(2, $result->results);
    }

    public function testReportsTheResultOfEveryMonitor(): void
    {
        $http = new MockHttpClient(new Response(201, self::RESULT));

        [$success, $failure] = self::client($http)->bulkMonitors->start(tagId: 42)->results;

        self::assertSame(804045264, $success->monitorId);
        self::assertSame('Shop', $success->monitorName);
        self::assertSame(BulkOperationStatus::Success, $success->status);
        self::assertNull($success->error);
        self::assertNull($success->code);
        self::assertSame(BulkOperationStatus::Error, $failure->status);
        self::assertSame('Monitor limit reached', $failure->error);
        self::assertSame('000-007', $failure->code);
    }

    /**
     * @return iterable<string, array{Closure(BulkMonitorResource): mixed, string}>
     */
    public static function incompleteCalls(): iterable
    {
        $selection = 'Select the monitors with $groupId, $tagId or both; the API requires at least one.';

        yield 'pause without selection' => [static fn(BulkMonitorResource $bulk): mixed => $bulk->pause(), $selection];
        yield 'start without selection' => [static fn(BulkMonitorResource $bulk): mixed => $bulk->start(), $selection];
        yield 'update without selection' => [static fn(BulkMonitorResource $bulk): mixed => $bulk->update(new BulkMonitorUpdate(interval: 300)), $selection];
        yield 'update without changes' => [static fn(BulkMonitorResource $bulk): mixed => $bulk->update(new BulkMonitorUpdate(), groupId: 12), '$changes changes nothing; set at least one setting.'];
    }

    /**
     * @param Closure(BulkMonitorResource): mixed $call
     */
    #[DataProvider('incompleteCalls')]
    public function testRejectsACallThatSelectsOrChangesNothingBeforeSendingIt(Closure $call, string $message): void
    {
        $http = new MockHttpClient();

        try {
            $call(self::client($http)->bulkMonitors);
            self::fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException $e) {
            self::assertSame($message, $e->getMessage());
        }

        self::assertSame(0, $http->callCount());
    }

    public function testReportsTheValidationErrorsOfTheApi(): void
    {
        $http = new MockHttpClient(new Response(400, '{"message":["tagId must not be less than 1"],"error":"Bad Request","statusCode":400}'));

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('tagId must not be less than 1');

        self::client($http)->bulkMonitors->pause(tagId: 0);
    }

    private static function client(MockHttpClient $http): UptimeRobot
    {
        return new UptimeRobot('secret', httpClient: $http);
    }
}
