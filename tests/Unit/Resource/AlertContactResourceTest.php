<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Resource;

use Deprecated;
use GoSuccess\UptimeRobot\Enum\AlertContactPlatform;
use GoSuccess\UptimeRobot\Enum\AlertContactType;
use GoSuccess\UptimeRobot\Enum\NotificationEvent;
use GoSuccess\UptimeRobot\Exception\BadRequestException;
use GoSuccess\UptimeRobot\Exception\NotFoundException;
use GoSuccess\UptimeRobot\Http\Response;
use GoSuccess\UptimeRobot\Model\AlertContact;
use GoSuccess\UptimeRobot\Model\AlertContactConfig;
use GoSuccess\UptimeRobot\Model\AlertContactCreate;
use GoSuccess\UptimeRobot\Model\AlertContactUpdate;
use GoSuccess\UptimeRobot\Resource\AlertContactResource;
use GoSuccess\UptimeRobot\Tests\Support\MockHttpClient;
use GoSuccess\UptimeRobot\UptimeRobot;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionEnumBackedCase;

/**
 * The personal alert contacts resource against a mocked transport, with the
 * payloads and error bodies the live API sent (addresses replaced).
 */
#[CoversClass(AlertContactResource::class)]
final class AlertContactResourceTest extends TestCase
{
    /** An e-mail contact as GET /alert-contacts/{id} returned it (verified live). */
    private const string EMAIL = '{"id":8733402,"friendlyName":null,"type":"Email","value":"ops@example.com","customValue":null,"enableNotificationsFor":"UpAndDown",'
        . '"sslExpirationReminder":true,"httpUsername":null,"httpPassword":null,"authType":true,"customHeaders":null,"mobileProviderId":null,"status":"Paused",'
        . '"orgAlertContactId":null,"config":null}';

    /** The Android app contact of the same list (verified live). */
    private const string ANDROID = '{"id":8733530,"friendlyName":"Pixel","type":"MobileApp","value":"device-token","customValue":"subscription-id",'
        . '"enableNotificationsFor":"UpAndDown","sslExpirationReminder":false,"httpUsername":null,"httpPassword":null,"authType":true,"customHeaders":null,'
        . '"mobileProviderId":null,"status":"Active","orgAlertContactId":null,"config":{"android_push_up_channel":"default_dnd","android_push_down_channel":"default_dnd"}}';

    public function testReadsAPageAsTheApiSendsIt(): void
    {
        // A single page has no nextLink key at all (verified live).
        $http = new MockHttpClient(new Response(200, '{"data":[' . self::EMAIL . ',' . self::ANDROID . ']}'));

        $page = self::client($http)->alertContacts->list();

        self::assertSame('GET', $http->requests[0]->method->value);
        self::assertSame('https://api.uptimerobot.com/v3/alert-contacts', $http->requests[0]->uri);
        self::assertSame([8733402, 8733530], array_map(static fn(AlertContact $contact): int => $contact->id, $page->items));
        self::assertNull($page->next);

        [$email, $android] = $page->items;
        self::assertSame('Email', $email->type);
        self::assertSame('Paused', $email->status);
        self::assertSame(NotificationEvent::UpAndDown, $email->enableNotificationsFor);
        self::assertSame('ops@example.com', $email->value);
        self::assertNull($email->friendlyName);
        self::assertNull($email->config);
        // A null map reads as empty.
        self::assertSame([], $email->customHeaders);

        self::assertSame('MobileApp', $android->type);
        self::assertSame('subscription-id', $android->customValue);
        self::assertSame('default_dnd', $android->config?->androidPushUpChannel);
        self::assertSame('default_dnd', $android->config->androidPushDownChannel);
    }

    public function testKeepsTypesAndStatusesItDoesNotKnow(): void
    {
        // ToMigrate is not in the specification (verified live); the types
        // change after October 10, 2026.
        $http = new MockHttpClient(new Response(200, str_replace(['"MobileApp"', '"Active"'], ['"MobileAppAndroid"', '"ToMigrate"'], self::ANDROID)));

        $contact = self::client($http)->alertContacts->get(8733530);

        self::assertSame('MobileAppAndroid', $contact->type);
        self::assertSame('ToMigrate', $contact->status);
    }

    public function testIteratesOverAllContactsWithTheIdOfTheLastContactAsCursor(): void
    {
        $http = new MockHttpClient(
            new Response(200, '{"data":[' . self::EMAIL . '],"nextLink":"https://api.uptimerobot.com/v3/alert-contacts/?cursor=8733402"}'),
            new Response(200, '{"data":[' . self::ANDROID . ']}'),
        );

        $ids = [];

        foreach (self::client($http)->alertContacts->all() as $contact) {
            $ids[] = $contact->id;
        }

        self::assertSame([8733402, 8733530], $ids);
        // The contacts with greater IDs follow the cursor (verified live).
        self::assertSame('https://api.uptimerobot.com/v3/alert-contacts?cursor=8733402', $http->requests[1]->uri);
    }

    public function testReadsAContactAndReportsAMissingOne(): void
    {
        $http = new MockHttpClient(
            new Response(200, self::EMAIL),
            new Response(404, '{"message":"Alert contact not found","code":"000-004"}'),
        );
        $contacts = self::client($http)->alertContacts;

        self::assertSame(8733402, $contacts->get(8733402)->id);
        self::assertSame('https://api.uptimerobot.com/v3/alert-contacts/8733402', $http->requests[0]->uri);

        try {
            $contacts->get(1);
            self::fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            self::assertSame('000-004', $e->errorCode);
            self::assertStringEndsWith('HTTP 404: Alert contact not found (000-004)', $e->getMessage());
        }
    }

    public function testCreatesAnEmailContactWithTheNamesOfItsSettings(): void
    {
        $http = new MockHttpClient(new Response(201, self::EMAIL));

        $contact = self::client($http)->alertContacts->create(new AlertContactCreate(
            AlertContactType::Email,
            friendlyName: 'Ops',
            enableNotificationsFor: NotificationEvent::Down,
            value: 'ops@example.com',
        ));

        $request = $http->requests[0];
        self::assertSame('POST', $request->method->value);
        self::assertSame('https://api.uptimerobot.com/v3/alert-contacts', $request->uri);
        self::assertSame('application/json', $request->headers['Content-Type'] ?? null);
        // The names, which the API turns into the numbers of the specification.
        self::assertSame('{"type":"Email","friendlyName":"Ops","enableNotificationsFor":"Down","value":"ops@example.com"}', $request->body);
        self::assertSame(8733402, $contact->id);
    }

    public function testCreatesAMobileAppContactWithItsPushFields(): void
    {
        $http = new MockHttpClient(new Response(201, self::ANDROID));

        self::client($http)->alertContacts->create(new AlertContactCreate(
            AlertContactType::MobileAppAndroid,
            deviceName: 'Pixel',
            oneSignalSubscriptionId: 'subscription-id',
            oneSignalUserId: 'user-id',
            deviceFingerprint: 'fingerprint',
            pushToken: 'device-token',
            platform: AlertContactPlatform::Android,
            config: new AlertContactConfig(androidPushUpChannel: 'default_dnd'),
        ));

        self::assertSame(
            '{"type":"MobileAppAndroid","deviceName":"Pixel","oneSignalSubscriptionId":"subscription-id","oneSignalUserId":"user-id",'
            . '"deviceFingerprint":"fingerprint","pushToken":"device-token","platform":"android","config":{"android_push_up_channel":"default_dnd"}}',
            $http->requests[0]->body,
        );
    }

    public function testMarksTheMobileAppTypesThatExpireAsDeprecated(): void
    {
        // Accepted through October 10, 2026, according to the specification.
        foreach (['MobileAppOld' => 'MobileAppIos', 'MobileApp' => 'MobileAppAndroid'] as $old => $replacement) {
            $case = new ReflectionEnumBackedCase(AlertContactType::class, $old);

            self::assertTrue($case->isDeprecated());
            $attributes = $case->getAttributes(Deprecated::class);
            self::assertCount(1, $attributes);
            self::assertStringStartsWith("Use {$replacement}:", $attributes[0]->newInstance()->message ?? '');
        }

        self::assertFalse(new ReflectionEnumBackedCase(AlertContactType::class, 'MobileAppIos')->isDeprecated());
        // The values stay readable without a deprecation.
        self::assertSame('MobileApp', AlertContactType::tryFrom('MobileApp')?->value);
        self::assertSame('MobileAppIOS', AlertContactType::MobileAppIos->value);
    }

    public function testSendsOnlyTheChangesThatAreSet(): void
    {
        $http = new MockHttpClient(new Response(200, self::EMAIL), new Response(200, self::EMAIL));
        $contacts = self::client($http)->alertContacts;

        $contacts->update(8733402, new AlertContactUpdate(enableNotificationsFor: NotificationEvent::None, isActive: false));
        $contacts->update(8733402, new AlertContactUpdate());

        self::assertSame('PATCH', $http->requests[0]->method->value);
        self::assertSame('https://api.uptimerobot.com/v3/alert-contacts/8733402', $http->requests[0]->uri);
        self::assertSame('{"enableNotificationsFor":"None","isActive":false}', $http->requests[0]->body);
        self::assertSame('{}', $http->requests[1]->body);
    }

    public function testReportsTheErrorsOfAnUpdate(): void
    {
        // The validator runs before the lookup (verified live for 999999999).
        $http = new MockHttpClient(
            new Response(400, '{"message":["enableNotificationsFor must be one of the following values: 0, 1, 2, 3","isActive must be a boolean value"],'
                . '"error":"Bad Request","statusCode":400}'),
            new Response(404, '{"code":"000-004","message":"Resource you were trying to access is not found."}'),
        );
        $contacts = self::client($http)->alertContacts;

        try {
            $contacts->update(999999999, new AlertContactUpdate(friendlyName: 'Ops'));
            self::fail('Expected a BadRequestException.');
        } catch (BadRequestException $e) {
            self::assertStringContainsString('enableNotificationsFor must be one of the following values: 0, 1, 2, 3', $e->getMessage());
        }

        $this->expectException(NotFoundException::class);
        $contacts->update(999999999, new AlertContactUpdate(enableNotificationsFor: NotificationEvent::Down));
    }

    public function testDeletesAContact(): void
    {
        // 200 without a body, as documented.
        $http = new MockHttpClient(
            new Response(200),
            new Response(404, '{"code":"000-004","message":"Resource you were trying to access is not found."}'),
        );
        $contacts = self::client($http)->alertContacts;

        $contacts->delete(8733402);

        self::assertSame('DELETE', $http->requests[0]->method->value);
        self::assertSame('https://api.uptimerobot.com/v3/alert-contacts/8733402', $http->requests[0]->uri);
        self::assertNull($http->requests[0]->body);

        $this->expectException(NotFoundException::class);
        $contacts->delete(999999999);
    }

    private static function client(MockHttpClient $http): UptimeRobot
    {
        return new UptimeRobot('secret', httpClient: $http);
    }
}
