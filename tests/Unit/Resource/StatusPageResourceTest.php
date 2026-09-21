<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Resource;

use Closure;
use GoSuccess\UptimeRobot\Enum\StatusPageDensity;
use GoSuccess\UptimeRobot\Enum\StatusPageLayout;
use GoSuccess\UptimeRobot\Enum\StatusPageSort;
use GoSuccess\UptimeRobot\Enum\StatusPageStatus;
use GoSuccess\UptimeRobot\Enum\StatusPageTheme;
use GoSuccess\UptimeRobot\Exception\BadRequestException;
use GoSuccess\UptimeRobot\Exception\NotFoundException;
use GoSuccess\UptimeRobot\Http\FileUpload;
use GoSuccess\UptimeRobot\Http\Request;
use GoSuccess\UptimeRobot\Http\Response;
use GoSuccess\UptimeRobot\Model\StatusPageCreate;
use GoSuccess\UptimeRobot\Model\StatusPageCustomSettingsColorsInput;
use GoSuccess\UptimeRobot\Model\StatusPageCustomSettingsFeaturesInput;
use GoSuccess\UptimeRobot\Model\StatusPageCustomSettingsFontInput;
use GoSuccess\UptimeRobot\Model\StatusPageCustomSettingsInput;
use GoSuccess\UptimeRobot\Model\StatusPageCustomSettingsPageInput;
use GoSuccess\UptimeRobot\Model\StatusPageUpdate;
use GoSuccess\UptimeRobot\Resource\StatusPageResource;
use GoSuccess\UptimeRobot\Tests\Support\MockHttpClient;
use GoSuccess\UptimeRobot\UptimeRobot;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The status pages resource against a mocked transport, with the hand-written
 * create() and update() and their file uploads. The account has no status
 * pages and may not create one, so the page below follows the specification
 * and the official Terraform provider; the empty list and the error bodies are
 * the ones the live API sent.
 */
#[CoversClass(StatusPageResource::class)]
final class StatusPageResourceTest extends TestCase
{
    /**
     * A page shaped like PspDto (not observable live), with the features as
     * the strings of the specification and as booleans.
     */
    private const string PAGE = '{"id":81234,"friendlyName":"Shop status","customDomain":null,"isPasswordSet":true,"monitorIds":[0],'
        . '"tagIds":[],"monitorsCount":9,"status":"ENABLED","urlKey":"AbC1dE2fG3","homepageLink":"https://example.com","gaCode":null,'
        . '"shareAnalyticsConsent":false,"useSmallCookieConsentModal":false,"icon":null,"noIndex":true,'
        . '"logo":"https://cdn.example.com/logo.png","hideUrlLinks":false,"subscription":false,"showCookieBar":false,"pinnedAnnouncementId":77,'
        . '"customSettings":{"font":{"family":"Roboto"},"page":{"layout":"logo_on_left","theme":"dark","density":"compact"},'
        . '"colors":{"main":"#112233","text":null,"link":"#556677"},'
        . '"features":{"showBars":"true","showUptimePercentage":"false","enableFloatingStatus":null,"showOverallUptime":true,'
        . '"showOutageUpdates":false,"showOutageDetails":"true","enableDetailsPage":"true","showMonitorURL":"false","hidePausedMonitors":"true"}}}';

    public function testReadsAStatusPageWithItsDesign(): void
    {
        $http = new MockHttpClient(new Response(200, self::PAGE));

        $page = self::client($http)->statusPages->get(81234);

        self::assertSame('GET', $http->requests[0]->method->value);
        self::assertSame('https://api.uptimerobot.com/v3/psps/81234', $http->requests[0]->uri);
        self::assertSame(81234, $page->id);
        self::assertSame('Shop status', $page->friendlyName);
        self::assertTrue($page->isPasswordSet);
        self::assertSame([0], $page->monitorIds);
        self::assertSame(9, $page->monitorsCount);
        self::assertSame('ENABLED', $page->status);
        self::assertSame('https://cdn.example.com/logo.png', $page->logo);
        self::assertNull($page->icon);
        self::assertSame(77, $page->pinnedAnnouncementId);

        $settings = $page->customSettings;
        self::assertNotNull($settings);
        self::assertSame('Roboto', $settings->font?->family);
        self::assertSame(StatusPageLayout::LogoOnLeft, $settings->page->layout);
        self::assertSame(StatusPageTheme::Dark, $settings->page->theme);
        self::assertSame(StatusPageDensity::Compact, $settings->page->density);
        self::assertSame('#112233', $settings->colors->main);
        self::assertNull($settings->colors->text);

        // "true"/"false" and booleans alike; null stays unknown.
        $features = $settings->features;
        self::assertTrue($features->showBars);
        self::assertFalse($features->showUptimePercentage);
        self::assertNull($features->enableFloatingStatus);
        self::assertTrue($features->showOverallUptime);
        self::assertFalse($features->showOutageUpdates);
        self::assertFalse($features->showMonitorUrl);
        self::assertTrue($features->hidePausedMonitors);
    }

    public function testReadsAPageWithoutDesign(): void
    {
        $http = new MockHttpClient(new Response(200, '{"id":81235,"friendlyName":"Blank","monitorIds":null,"status":"PAUSED","customSettings":null}'));

        $page = self::client($http)->statusPages->get(81235);

        self::assertSame([], $page->monitorIds);
        self::assertSame('PAUSED', $page->status);
        self::assertNull($page->customSettings);
    }

    public function testReadsTheEmptyListWithoutNextLink(): void
    {
        // Verified live on an account without status pages.
        $http = new MockHttpClient(new Response(200, '{"data":[]}'));

        $page = self::client($http)->statusPages->list();

        self::assertSame('https://api.uptimerobot.com/v3/psps', $http->requests[0]->uri);
        self::assertSame([], $page->items);
        self::assertNull($page->next);
        self::assertFalse($page->hasMore);
    }

    public function testCreatesAPageAsJson(): void
    {
        $http = new MockHttpClient(new Response(201, self::PAGE));

        $page = self::client($http)->statusPages->create(new StatusPageCreate(
            friendlyName: 'Shop status',
            monitorIds: [0],
            status: StatusPageStatus::Enabled,
            sort: StatusPageSort::StatusDownUpPaused,
            noIndex: true,
            customSettings: new StatusPageCustomSettingsInput(
                page: new StatusPageCustomSettingsPageInput(layout: StatusPageLayout::LogoOnLeft, theme: StatusPageTheme::Dark),
                features: new StatusPageCustomSettingsFeaturesInput(showBars: true, showMonitorUrl: false),
            ),
        ));

        $request = $http->requests[0];
        self::assertSame('POST', $request->method->value);
        self::assertSame('https://api.uptimerobot.com/v3/psps', $request->uri);
        self::assertSame('application/json', $request->headers['Content-Type'] ?? null);
        self::assertSame(
            '{"friendlyName":"Shop status","monitorIds":[0],"noIndex":true,"sort":"StatusDownUpPaused","status":"ENABLED",'
            . '"customSettings":{"page":{"layout":"logo_on_left","theme":"dark"},"features":{"showBars":true,"showMonitorURL":false}}}',
            $request->body,
        );
        self::assertSame(81234, $page->id);
    }

    public function testCreatesAPageWithALogoAsForm(): void
    {
        $http = new MockHttpClient(new Response(201, self::PAGE));

        self::client($http)->statusPages->create(
            new StatusPageCreate(
                friendlyName: 'Shop status',
                monitorIds: [803767164, 803872200],
                hideUrlLinks: true,
                status: StatusPageStatus::Enabled,
                customSettings: new StatusPageCustomSettingsInput(
                    font: new StatusPageCustomSettingsFontInput(family: 'Roboto'),
                    page: new StatusPageCustomSettingsPageInput(theme: StatusPageTheme::Light),
                    colors: new StatusPageCustomSettingsColorsInput(main: '#131a26'),
                    features: new StatusPageCustomSettingsFeaturesInput(showBars: false),
                ),
            ),
            logo: new FileUpload('logo.png', "\x89PNG\r\n\x1a\n", 'image/png'),
        );

        $request = $http->requests[0];
        self::assertSame('POST', $request->method->value);
        self::assertSame('https://api.uptimerobot.com/v3/psps', $request->uri);
        self::assertSame(
            [
                ['friendlyName', null, null, 'Shop status'],
                ['monitorIds[]', null, null, '803767164'],
                ['monitorIds[]', null, null, '803872200'],
                ['hideUrlLinks', null, null, 'true'],
                ['status', null, null, 'ENABLED'],
                // Nested fields, which the validator reads as the object (verified live).
                ['customSettings[font][family]', null, null, 'Roboto'],
                ['customSettings[page][theme]', null, null, 'light'],
                ['customSettings[colors][main]', null, null, '#131a26'],
                ['customSettings[features][showBars]', null, null, 'false'],
                ['logo', 'logo.png', 'image/png', "\x89PNG\r\n\x1a\n"],
            ],
            self::formParts($request),
        );
    }

    public function testUploadsOnlyAnIconForAnEmptyUpdate(): void
    {
        $http = new MockHttpClient(new Response(200, self::PAGE));

        $page = self::client($http)->statusPages->update(81234, new StatusPageUpdate(), icon: new FileUpload('icon.jpg', 'jpeg bytes', 'image/jpeg'));

        $request = $http->requests[0];
        self::assertSame('PATCH', $request->method->value);
        self::assertSame('https://api.uptimerobot.com/v3/psps/81234', $request->uri);
        self::assertSame([['icon', 'icon.jpg', 'image/jpeg', 'jpeg bytes']], self::formParts($request));
        self::assertSame(81234, $page->id);
    }

    public function testSendsAnEmptyListAsASingleEmptyField(): void
    {
        $http = new MockHttpClient(new Response(200, self::PAGE));

        self::client($http)->statusPages->update(
            81234,
            new StatusPageUpdate(monitorIds: [], pinnedAnnouncementId: 77),
            logo: new FileUpload('logo.png', 'png', 'image/png'),
            icon: new FileUpload('icon.png', 'png', 'image/png'),
        );

        // As the specification documents it: "monitorIds[]=" is the empty list.
        self::assertSame(
            [
                ['monitorIds[]', null, null, ''],
                ['pinnedAnnouncementId', null, null, '77'],
                ['logo', 'logo.png', 'image/png', 'png'],
                ['icon', 'icon.png', 'image/png', 'png'],
            ],
            self::formParts($http->requests[0]),
        );
    }

    public function testUpdatesOnlyTheSetPropertiesAsJson(): void
    {
        $http = new MockHttpClient(new Response(200, self::PAGE));

        self::client($http)->statusPages->update(81234, new StatusPageUpdate(
            status: StatusPageStatus::Paused,
            customSettings: new StatusPageCustomSettingsInput(font: null, page: new StatusPageCustomSettingsPageInput(density: StatusPageDensity::Normal)),
        ));

        $request = $http->requests[0];
        self::assertSame('PATCH', $request->method->value);
        self::assertSame('application/json', $request->headers['Content-Type'] ?? null);
        self::assertSame('{"status":"PAUSED","customSettings":{"font":null,"page":{"density":"normal"}}}', $request->body);
    }

    /**
     * @return iterable<string, array{Closure(StatusPageResource, FileUpload): mixed, string}>
     */
    public static function valuesAFormCannotExpress(): iterable
    {
        yield 'null' => [
            static fn(StatusPageResource $pages, FileUpload $logo): mixed => $pages->update(
                81234,
                new StatusPageUpdate(customSettings: new StatusPageCustomSettingsInput(font: null)),
                logo: $logo,
            ),
            'customSettings.font is null, which multipart/form-data cannot express; send it without the logo and icon, which a separate update() can upload.',
        ];
        yield 'an empty object' => [
            static fn(StatusPageResource $pages, FileUpload $logo): mixed => $pages->create(
                new StatusPageCreate(friendlyName: 'Shop status', customSettings: new StatusPageCustomSettingsInput()),
                logo: $logo,
            ),
            'customSettings is an empty object, which multipart/form-data cannot express; send it without the logo and icon, which a separate update() can upload.',
        ];
        yield 'an empty nested object' => [
            static fn(StatusPageResource $pages, FileUpload $logo): mixed => $pages->update(
                81234,
                new StatusPageUpdate(customSettings: new StatusPageCustomSettingsInput(colors: new StatusPageCustomSettingsColorsInput())),
                icon: $logo,
            ),
            'customSettings.colors is an empty object, which multipart/form-data cannot express; send it without the logo and icon, which a separate update() can upload.',
        ];
    }

    /**
     * @param Closure(StatusPageResource, FileUpload): mixed $call
     */
    #[DataProvider('valuesAFormCannotExpress')]
    public function testRejectsAFileWithAValueAFormCannotExpressBeforeSendingIt(Closure $call, string $message): void
    {
        $http = new MockHttpClient();

        try {
            $call(self::client($http)->statusPages, new FileUpload('logo.png', 'png', 'image/png'));
            self::fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException $e) {
            self::assertSame($message, $e->getMessage());
        }

        self::assertSame(0, $http->callCount());
    }

    public function testSendsAnEmptyDesignWithoutAFileAsJson(): void
    {
        $http = new MockHttpClient(new Response(200, self::PAGE));

        self::client($http)->statusPages->update(81234, new StatusPageUpdate(customSettings: new StatusPageCustomSettingsInput()));

        self::assertSame('{"customSettings":{}}', $http->requests[0]->body);
    }

    public function testReportsTheValidationErrorsOfTheApi(): void
    {
        // The API's answer to "customSettings":{"page":{"theme":"Light"}} (verified live).
        $http = new MockHttpClient(new Response(
            400,
            '{"message":["customSettings.page.theme must be one of the following values: light, dark"],"error":"Bad Request","statusCode":400}',
        ));

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('customSettings.page.theme must be one of the following values: light, dark');

        self::client($http)->statusPages->update(81234, new StatusPageUpdate(friendlyName: 'Shop status'), logo: new FileUpload('logo.png', 'png', 'image/png'));
    }

    public function testReportsAnUnknownPage(): void
    {
        // Verified live for GET, PATCH and DELETE of /psps/999999999.
        $http = new MockHttpClient(
            new Response(404, '{"message":"PSP not found","code":"000-004"}'),
            new Response(404, '{"code":"000-004","message":"Resource you were trying to access is not found."}'),
        );
        $pages = self::client($http)->statusPages;

        foreach ([static fn(): mixed => $pages->get(999999999), static fn(): mixed => $pages->update(999999999, new StatusPageUpdate(friendlyName: 'x'))] as $call) {
            try {
                $call();
                self::fail('Expected a NotFoundException.');
            } catch (NotFoundException $e) {
                self::assertSame('000-004', $e->errorCode);
            }
        }
    }

    /**
     * The parts of a multipart/form-data request: name, file name, content
     * type and content.
     *
     * @return list<array{string, string|null, string|null, string}>
     */
    private static function formParts(Request $request): array
    {
        $contentType = $request->headers['Content-Type'] ?? '';
        self::assertMatchesRegularExpression('~^multipart/form-data; boundary=\S+$~', $contentType);
        $boundary = substr($contentType, \strlen('multipart/form-data; boundary='));
        $body = (string) $request->body;

        self::assertStringEndsWith("--{$boundary}--\r\n", $body);
        $chunks = explode("--{$boundary}\r\n", substr($body, 0, -\strlen("--{$boundary}--\r\n")));
        self::assertSame('', array_shift($chunks));

        $parts = [];

        foreach ($chunks as $chunk) {
            [$head, $content] = explode("\r\n\r\n", $chunk, 2);
            self::assertStringEndsWith("\r\n", $content);

            if (preg_match('~^Content-Disposition: form-data; name="([^"]*)"(?:; filename="([^"]*)")?(?:\r\nContent-Type: (.+))?$~', $head, $match) !== 1) {
                self::fail("Unexpected part headers: {$head}");
            }

            $parts[] = [$match[1], ($match[2] ?? '') === '' ? null : $match[2], $match[3] ?? null, substr($content, 0, -2)];
        }

        return $parts;
    }

    private static function client(MockHttpClient $http): UptimeRobot
    {
        return new UptimeRobot('secret', httpClient: $http);
    }
}
