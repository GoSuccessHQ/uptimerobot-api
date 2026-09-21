<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Resource;

use GoSuccess\UptimeRobot\Enum\NotificationEvent;
use GoSuccess\UptimeRobot\Enum\PagerDutyLocation;
use GoSuccess\UptimeRobot\Enum\PushoverPriority;
use GoSuccess\UptimeRobot\Exception\BadRequestException;
use GoSuccess\UptimeRobot\Exception\ForbiddenException;
use GoSuccess\UptimeRobot\Exception\NotFoundException;
use GoSuccess\UptimeRobot\Http\Response;
use GoSuccess\UptimeRobot\Model\GoogleChatIntegrationUpdate;
use GoSuccess\UptimeRobot\Model\Integration;
use GoSuccess\UptimeRobot\Model\IntegrationCreate;
use GoSuccess\UptimeRobot\Model\MsTeamsIntegrationCreate;
use GoSuccess\UptimeRobot\Model\PagerDutyIntegrationCreate;
use GoSuccess\UptimeRobot\Model\PagerDutyIntegrationUpdate;
use GoSuccess\UptimeRobot\Model\PushbulletIntegrationCreate;
use GoSuccess\UptimeRobot\Model\PushoverIntegrationCreate;
use GoSuccess\UptimeRobot\Model\SlackIntegrationCreate;
use GoSuccess\UptimeRobot\Model\SlackIntegrationUpdate;
use GoSuccess\UptimeRobot\Model\SplunkIntegrationUpdate;
use GoSuccess\UptimeRobot\Model\TelegramIntegrationCreate;
use GoSuccess\UptimeRobot\Model\WebhookIntegrationCreate;
use GoSuccess\UptimeRobot\Model\WebhookIntegrationUpdate;
use GoSuccess\UptimeRobot\Resource\IntegrationResource;
use GoSuccess\UptimeRobot\Tests\Support\MockHttpClient;
use GoSuccess\UptimeRobot\UptimeRobot;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The integrations resource against a mocked transport. The account has no
 * integrations, so the payloads are the personal contacts that
 * includeOrgMembers lists (verified live, addresses replaced) and integrations
 * shaped like them.
 */
#[CoversClass(IntegrationResource::class)]
final class IntegrationResourceTest extends TestCase
{
    /** A phone contact as includeOrgMembers=true listed it (verified live). */
    private const string VOICE = '{"id":6554089,"friendlyName":null,"enableNotificationsFor":"UpAndDown","type":"Voice","status":"Active","sslExpirationReminder":false,'
        . '"value":"+10000000000","customValue":"","customValue2":"","customValue3":"","customValue4":"","customHeaders":null}';

    /** A Slack integration in the same shape. */
    private const string SLACK = '{"id":9100001,"friendlyName":"Ops","enableNotificationsFor":"Down","type":"Slack","status":"Active","sslExpirationReminder":true,'
        . '"value":"https://hooks.slack.com/services/T0/B0/X","customValue":"#alerts","customValue2":"","customValue3":"","customValue4":"","customHeaders":null}';

    public function testListsTheIntegrationsAndThePersonalContactsOnRequest(): void
    {
        // Without integrations, the list is {"data":[]} (verified live).
        $http = new MockHttpClient(new Response(200, '{"data":[]}'), new Response(200, '{"data":[' . self::VOICE . ']}'));
        $integrations = self::client($http)->integrations;

        self::assertSame([], $integrations->list()->items);
        self::assertSame('https://api.uptimerobot.com/v3/integrations', $http->requests[0]->uri);

        $page = $integrations->list(includeOrgMembers: true);

        self::assertSame('https://api.uptimerobot.com/v3/integrations?includeOrgMembers=true', $http->requests[1]->uri);
        self::assertNull($page->next);
        $contact = $page->items[0];
        self::assertSame(6554089, $contact->id);
        self::assertSame('Voice', $contact->type);
        self::assertSame('Active', $contact->status);
        self::assertSame(NotificationEvent::UpAndDown, $contact->enableNotificationsFor);
        self::assertSame('+10000000000', $contact->value);
        // An empty string where AlertContact has null (verified live).
        self::assertSame('', $contact->customValue);
        self::assertSame([], $contact->customHeaders);
    }

    public function testIteratesOverAllItemsWithTheFilterAndTheIdOfTheLastItemAsCursor(): void
    {
        $http = new MockHttpClient(
            new Response(200, '{"data":[' . self::VOICE . '],"nextLink":"https://api.uptimerobot.com/v3/integrations/?includeOrgMembers=true&cursor=6554089"}'),
            new Response(200, '{"data":[' . self::SLACK . ']}'),
        );

        $ids = array_map(static fn(Integration $integration): int => $integration->id, iterator_to_array(self::client($http)->integrations->all(includeOrgMembers: true), false));

        self::assertSame([6554089, 9100001], $ids);
        self::assertSame('https://api.uptimerobot.com/v3/integrations?cursor=6554089&includeOrgMembers=true', $http->requests[1]->uri);
    }

    public function testReportsTheTwoKindsOfMissingIntegration(): void
    {
        $http = new MockHttpClient(
            new Response(200, self::SLACK),
            new Response(404, '{"message":"Alert contact not found","code":"000-004"}'),
            new Response(404, '{"message":"No integration found.","code":"021-005"}'),
        );
        $integrations = self::client($http)->integrations;

        $slack = $integrations->get(9100001);
        self::assertSame('Slack', $slack->type);
        self::assertSame('#alerts', $slack->customValue);
        self::assertSame(NotificationEvent::Down, $slack->enableNotificationsFor);
        self::assertSame('https://api.uptimerobot.com/v3/integrations/9100001', $http->requests[0]->uri);

        // An unknown ID and the ID of a personal contact (verified live).
        foreach ([999999999 => '000-004', 6554089 => '021-005'] as $id => $code) {
            try {
                $integrations->get($id);
                self::fail('Expected a NotFoundException.');
            } catch (NotFoundException $e) {
                self::assertSame($code, $e->errorCode);
            }
        }
    }

    /**
     * @return iterable<string, array{IntegrationCreate, string}>
     */
    public static function creations(): iterable
    {
        yield 'Slack' => [
            new SlackIntegrationCreate('https://hooks.slack.com/services/T0/B0/X', '#alerts', friendlyName: 'Ops', enableNotificationsFor: NotificationEvent::Down),
            '{"type":"Slack","data":{"webhookURL":"https://hooks.slack.com/services/T0/B0/X","customValue":"#alerts","friendlyName":"Ops","enableNotificationsFor":"Down"}}',
        ];

        // The spelling of the specification, which the API matches ignoring case.
        yield 'Microsoft Teams' => [
            new MsTeamsIntegrationCreate('https://example.webhook.office.com/x', sslExpirationReminder: true),
            '{"type":"MSTeams","data":{"webhookURL":"https://example.webhook.office.com/x","sslExpirationReminder":true}}',
        ];

        yield 'webhook' => [
            new WebhookIntegrationCreate(
                'https://example.com/hook',
                '{"message":"Alert: $monitorURL is $alertType"}',
                sendAsJson: true,
                customHeaders: ['Authorization' => 'Bearer token'],
            ),
            '{"type":"Webhook","data":{"urlToNotify":"https://example.com/hook","postValue":"{\"message\":\"Alert: $monitorURL is $alertType\"}",'
            . '"sendAsJSON":true,"customHeaders":{"Authorization":"Bearer token"}}}',
        ];

        yield 'PagerDuty' => [
            new PagerDutyIntegrationCreate(str_repeat('a', 32), location: PagerDutyLocation::Europe, autoResolve: true),
            '{"type":"Pagerduty","data":{"integrationKey":"' . str_repeat('a', 32) . '","location":"eu","autoResolve":true}}',
        ];

        yield 'Pushbullet' => [
            new PushbulletIntegrationCreate('access-token'),
            '{"type":"PushBullet","data":{"accessToken":"access-token"}}',
        ];

        yield 'Pushover' => [
            new PushoverIntegrationCreate('user-key', priority: PushoverPriority::Emergency),
            '{"type":"Pushover","data":{"userKey":"user-key","priority":"Emergency"}}',
        ];

        // The specification gives Telegram only the common settings.
        yield 'Telegram' => [new TelegramIntegrationCreate(), '{"type":"Telegram","data":{}}'];
    }

    #[DataProvider('creations')]
    public function testCreatesAnIntegrationInTheEnvelopeOfItsType(IntegrationCreate $integration, string $body): void
    {
        $http = new MockHttpClient(new Response(201, self::SLACK));

        $created = self::client($http)->integrations->create($integration);

        $request = $http->requests[0];
        self::assertSame('POST', $request->method->value);
        self::assertSame('https://api.uptimerobot.com/v3/integrations', $request->uri);
        self::assertSame('application/json', $request->headers['Content-Type'] ?? null);
        self::assertSame($body, $request->body);
        self::assertSame(9100001, $created->id);
    }

    public function testChangesAnIntegrationAndAlwaysSendsItsType(): void
    {
        $http = new MockHttpClient(...array_fill(0, 5, new Response(200, self::SLACK)));
        $integrations = self::client($http)->integrations;

        $integrations->update(9100001, new SlackIntegrationUpdate(enableNotificationsFor: NotificationEvent::None));
        // The API rejects a change without a type (verified live).
        $integrations->update(9100001, new SlackIntegrationUpdate());
        // Splunk's own field, not the Google Chat fields the specification names.
        $integrations->update(9100002, new SplunkIntegrationUpdate(urlToNotify: 'https://alert.victorops.com/x'));
        $integrations->update(9100003, new GoogleChatIntegrationUpdate(roomUrl: 'https://chat.googleapis.com/v1/spaces/x', customMessage: ''));
        // An empty map clears the headers, null is sent as null.
        $integrations->update(9100004, new WebhookIntegrationUpdate(customHeaders: []));

        self::assertSame('PATCH', $http->requests[0]->method->value);
        self::assertSame('https://api.uptimerobot.com/v3/integrations/9100001', $http->requests[0]->uri);
        self::assertSame('{"type":"Slack","data":{"enableNotificationsFor":"None"}}', $http->requests[0]->body);
        self::assertSame('{"type":"Slack","data":{}}', $http->requests[1]->body);
        self::assertSame('{"type":"Splunk","data":{"urlToNotify":"https://alert.victorops.com/x"}}', $http->requests[2]->body);
        self::assertSame('{"type":"GoogleChat","data":{"roomURL":"https://chat.googleapis.com/v1/spaces/x","customMessage":""}}', $http->requests[3]->body);
        self::assertSame('{"type":"Webhook","data":{"customHeaders":{}}}', $http->requests[4]->body);
    }

    public function testReportsTheChecksBeforeTheLookup(): void
    {
        // Verified live for PATCH /integrations/999999999: an unknown type, a type
        // the plan lacks, and a known one.
        $http = new MockHttpClient(
            new Response(400, '{"message":["type must be one of the following values: 1, 2, 5, 6, 7, 8, 9, 11, 12, 13, 12, 13, 14, 15, 16, 17, 18, 20, 21, 23, 24, 25"],'
                . '"error":"Bad Request","statusCode":400}'),
            new Response(403, '{"message":"This integration is not available for current user.","code":"021-003"}'),
            new Response(404, '{"code":"000-004","message":"Resource you were trying to access is not found."}'),
        );
        $integrations = self::client($http)->integrations;

        try {
            $integrations->update(999999999, new SlackIntegrationUpdate());
            self::fail('Expected a BadRequestException.');
        } catch (BadRequestException $e) {
            self::assertStringContainsString('type must be one of the following values', $e->getMessage());
        }

        try {
            $integrations->update(999999999, new PagerDutyIntegrationUpdate());
            self::fail('Expected a ForbiddenException.');
        } catch (ForbiddenException $e) {
            self::assertSame('021-003', $e->errorCode);
            self::assertStringEndsWith('HTTP 403: This integration is not available for current user. (021-003)', $e->getMessage());
        }

        $this->expectException(NotFoundException::class);
        $integrations->update(999999999, new SlackIntegrationUpdate());
    }

    public function testDeletesAnIntegration(): void
    {
        // 200 without a body, as documented.
        $http = new MockHttpClient(
            new Response(200),
            new Response(404, '{"code":"000-004","message":"Resource you were trying to access is not found."}'),
        );
        $integrations = self::client($http)->integrations;

        $integrations->delete(9100001);

        self::assertSame('DELETE', $http->requests[0]->method->value);
        self::assertSame('https://api.uptimerobot.com/v3/integrations/9100001', $http->requests[0]->uri);
        self::assertNull($http->requests[0]->body);

        $this->expectException(NotFoundException::class);
        $integrations->delete(999999999);
    }

    private static function client(MockHttpClient $http): UptimeRobot
    {
        return new UptimeRobot('secret', httpClient: $http);
    }
}
