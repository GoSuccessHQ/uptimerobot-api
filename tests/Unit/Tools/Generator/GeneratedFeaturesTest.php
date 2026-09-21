<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Tools\Generator;

use BackedEnum;
use DateTimeImmutable;
use GoSuccess\UptimeRobot\Http\Response;
use GoSuccess\UptimeRobot\Model\RequestModel;
use GoSuccess\UptimeRobot\Model\ResponseModel;
use GoSuccess\UptimeRobot\Pagination\Page;
use GoSuccess\UptimeRobot\Pagination\Paginator;
use GoSuccess\UptimeRobot\Tests\Support\Generated\GeneratedCode;
use GoSuccess\UptimeRobot\Tests\Support\Generator\KitchenSink;
use GoSuccess\UptimeRobot\Tests\Support\MockHttpClient;
use GoSuccess\UptimeRobot\Tools\Generator\Generator;
use GoSuccess\UptimeRobot\Tools\Generator\Writer\ModelWriter;
use GoSuccess\UptimeRobot\Tools\Generator\Writer\ResourceWriter;
use GoSuccess\UptimeRobot\Tools\Generator\Writer\UnionWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * The code generated for the kitchen sink (see {@see KitchenSink}), called
 * with a mocked transport: what each feature of the generator produces.
 */
#[CoversClass(Generator::class)]
#[CoversClass(ModelWriter::class)]
#[CoversClass(ResourceWriter::class)]
#[CoversClass(UnionWriter::class)]
final class GeneratedFeaturesTest extends TestCase
{
    public function testNamesInlineEnumsWithPascalCaseCases(): void
    {
        $kind = KitchenSink::class('Enum\\WidgetKind');
        $region = KitchenSink::class('Enum\\Region');

        self::assertSame(['BigOne' => 'BIG_ONE', 'SmallOne' => 'small_one'], self::cases($kind));
        // Configured in enumCases.
        self::assertSame(['NorthAmerica' => 'na', 'Europe' => 'eu'], self::cases($region));
        self::assertSame(['Up' => 'UP', 'LooksDown' => 'LOOKS_DOWN', 'Paused' => 'PAUSED'], self::cases(KitchenSink::class('Enum\\WidgetStatus')));
    }

    public function testReadsOverriddenTypesAndClassifiedNumbers(): void
    {
        $widget = KitchenSink::class('Model\\Widget');
        $read = $widget::fromArray([
            'id' => 803767164,
            'status' => 'LOOKS_DOWN',
            'createdAt' => '2026-11-18T18:03:20.000Z',
            'kind' => 'small_one',
            'score' => 99.5,
            'owner' => ['id' => 5, 'IPv6' => '::1', 'MANUAL_SELECTED' => true],
        ]);
        self::assertIsObject($read);

        self::assertSame('int', self::propertyType($widget, 'id'));
        self::assertSame('float', self::propertyType($widget, 'score'));
        self::assertSame(803767164, self::property($read, 'id'));
        self::assertSame('LOOKS_DOWN', self::enumValue(self::property($read, 'status')));
        self::assertSame('small_one', self::enumValue(self::property($read, 'kind')));
        self::assertEquals(new DateTimeImmutable('2026-11-18T18:03:20Z'), self::property($read, 'createdAt'));

        $owner = self::property($read, 'owner');
        self::assertIsObject($owner);
        self::assertSame('::1', self::property($owner, 'ipv6'));
        self::assertTrue(self::property($owner, 'manualSelected'));
    }

    public function testVariantsSendTheirDiscriminatorInsteadOfTakingIt(): void
    {
        $class = KitchenSink::class('Model\\HttpWidgetCreate');
        $parameters = array_map(static fn($parameter): string => $parameter->getName(), new ReflectionClass($class)->getConstructor()?->getParameters() ?? []);

        self::assertSame(['friendlyName', 'url', 'interval', 'kind'], $parameters);
        self::assertSame('HTTP', \constant("{$class}::TYPE"));
        self::assertTrue(is_subclass_of($class, KitchenSink::class('Model\\WidgetCreate')));

        $http = new MockHttpClient(new Response(201, '{"id": 1}'));
        $widget = new $class(friendlyName: 'Shop', url: 'https://example.com');
        self::call(self::resource('widgets', $http), 'create', $widget);

        self::assertSame('{"type":"HTTP","friendlyName":"Shop","url":"https://example.com"}', $http->requests[0]->body);
    }

    public function testFlattensEnvelopedVariants(): void
    {
        $slack = KitchenSink::class('Model\\SlackHookCreate');
        $telegram = KitchenSink::class('Model\\TelegramHookCreate');
        $http = new MockHttpClient(new Response(201), new Response(201));
        $hooks = self::resource('hooks', $http);

        self::call($hooks, 'create', new $slack(webhookURL: 'https://hooks.slack.com/x'));
        // A variant without fields still sends its envelope as an object.
        self::call($hooks, 'create', new $telegram());

        self::assertSame('{"type":"Slack","data":{"webhookURL":"https://hooks.slack.com/x"}}', $http->requests[0]->body);
        self::assertSame('{"type":"Telegram","data":{}}', $http->requests[1]->body);
    }

    public function testReadsTheVariantADiscriminatorNamesAndKeepsUnknownOnes(): void
    {
        $http = new MockHttpClient(new Response(200, json_encode(['data' => [
            ['type' => 'CREATED', 'at' => '2026-09-21T09:06:30.000Z'],
            ['type' => 'DELETED', 'reason' => 'Cleanup'],
            ['type' => 'RENAMED', 'to' => 'Shop'],
            'not an object',
        ]], \JSON_THROW_ON_ERROR)));

        $events = self::call(self::resource('events', $http), 'list');

        self::assertIsArray($events);
        self::assertCount(3, $events);
        self::assertContainsOnlyObject($events);
        self::assertInstanceOf(KitchenSink::class('Model\\CreatedEvent'), $events[0]);
        self::assertEquals(new DateTimeImmutable('2026-09-21T09:06:30Z'), self::property($events[0], 'at'));
        self::assertInstanceOf(KitchenSink::class('Model\\DeletedEvent'), $events[1]);
        self::assertSame('Cleanup', self::property($events[1], 'reason'));
        self::assertInstanceOf(KitchenSink::class('Model\\UnknownEvent'), $events[2]);
        self::assertInstanceOf(KitchenSink::class('Model\\Event'), $events[2]);
        self::assertSame('RENAMED', self::property($events[2], 'type'));
        self::assertSame(['type' => 'RENAMED', 'to' => 'Shop'], self::property($events[2], 'data'));
    }

    public function testJoinsCommaSeparatedParametersAndRepeatsOthers(): void
    {
        $status = KitchenSink::class('Enum\\WidgetStatus');
        $http = new MockHttpClient(self::page([]), self::page([]));
        $widgets = self::resource('widgets', $http);

        self::call($widgets, 'list', tag: ['a', 'b'], status: [\constant("{$status}::Up"), \constant("{$status}::LooksDown")]);
        // An empty list filters nothing and is left out.
        self::call($widgets, 'list', status: []);

        self::assertSame('https://api.example.com/v3/widgets?tag=a&tag=b&status=UP%2CLOOKS_DOWN', $http->requests[0]->uri);
        self::assertSame('https://api.example.com/v3/widgets', $http->requests[1]->uri);
    }

    public function testFollowsTheCursorOfNextLinkWithTheSameFilters(): void
    {
        $http = new MockHttpClient(
            self::page([['id' => 1], ['id' => 2]], 'https://api.example.com/v3/widgets/?limit=2&group_id=5&cursor=abc2'),
            self::page([['id' => 3]]),
        );
        $paginator = self::call(self::resource('widgets', $http), 'all', limit: 2, groupId: 5);

        self::assertInstanceOf(Paginator::class, $paginator);
        self::assertSame([1, 2, 3], array_map(static fn(mixed $widget): mixed => \is_object($widget) ? self::property($widget, 'id') : null, iterator_to_array($paginator)));
        self::assertSame('https://api.example.com/v3/widgets?limit=2&group_id=5', $http->requests[0]->uri);
        self::assertSame('https://api.example.com/v3/widgets?cursor=abc2&limit=2&group_id=5', $http->requests[1]->uri);
    }

    public function testTakesTheCursorAndThePageSizeFirst(): void
    {
        $http = new MockHttpClient(self::page([['id' => 3]], '/v3/widgets?cursor=abc3'));
        $page = self::call(self::resource('widgets', $http), 'list', 'abc2', 1);

        self::assertInstanceOf(Page::class, $page);
        self::assertSame('abc3', $page->next);
        self::assertSame('https://api.example.com/v3/widgets?cursor=abc2&limit=1', $http->requests[0]->uri);
    }

    public function testFollowsIntegerCursorsOfNextCursorId(): void
    {
        $http = new MockHttpClient(
            new Response(200, '{"data": [{"id": 4, "name": "a"}], "nextCursorId": 4}'),
            new Response(200, '{"data": [{"id": 9, "name": "b"}], "nextCursorId": null}'),
        );

        $paginator = self::call(self::resource('tags', $http), 'all');

        self::assertInstanceOf(Paginator::class, $paginator);
        self::assertCount(2, iterator_to_array($paginator));
        self::assertSame('https://api.example.com/v3/tags?cursor=4', $http->requests[1]->uri);
    }

    public function testSendsAnEmptyObjectForABareObjectBodyAndNothingWithoutABody(): void
    {
        $http = new MockHttpClient(new Response(201), new Response(200));
        $widgets = self::resource('widgets', $http);

        self::call($widgets, 'pause', 7);
        self::call($widgets, 'archive', 7);

        self::assertSame('{}', $http->requests[0]->body);
        self::assertSame('application/json', $http->requests[0]->headers['Content-Type'] ?? null);
        self::assertNull($http->requests[1]->body);
        self::assertArrayNotHasKey('Content-Type', $http->requests[1]->headers);
    }

    public function testLeavesExcludedFileUploadsOutOfJsonModels(): void
    {
        $page = KitchenSink::class('Model\\CreatePage');
        $parameters = array_map(static fn($parameter): string => $parameter->getName(), new ReflectionClass($page)->getConstructor()?->getParameters() ?? []);

        self::assertSame(['friendlyName'], $parameters);
    }

    public function testSharedModelsSendOnlyWhatWasProvided(): void
    {
        $settings = KitchenSink::class('Model\\Settings');
        $window = KitchenSink::class('Enum\\SettingsWindow');

        $changes = new $settings(window: \constant("{$window}::Long"));
        self::assertInstanceOf(RequestModel::class, $changes);
        self::assertInstanceOf(ResponseModel::class, $changes);
        self::assertSame(['window' => 'LONG'], $changes->toArray());
    }

    public function testMapsBareArraysAndUnwrappedLists(): void
    {
        $http = new MockHttpClient(
            new Response(200, '[{"id": 1}, {"id": 2}]'),
            new Response(200, '{"data": [{"id": "352577094135060139", "channel": "email"}]}'),
        );
        $widgets = self::resource('widgets', $http);

        $recent = self::call($widgets, 'recent');
        $alerts = self::call($widgets, 'alerts', 1);

        self::assertIsArray($recent);
        self::assertCount(2, $recent);
        self::assertIsArray($alerts);
        self::assertIsObject($alerts[0]);
        self::assertSame('352577094135060139', self::property($alerts[0], 'id'));
    }

    /**
     * A resource of the kitchen sink's client that sends to the given transport.
     */
    private static function resource(string $name, MockHttpClient $http): object
    {
        $resource = GeneratedCode::client(KitchenSink::analysis(), $http)->{$name};
        self::assertIsObject($resource);

        return $resource;
    }

    /**
     * Call a method of generated code, which static analysis cannot know.
     */
    private static function call(object $target, string $method, mixed ...$arguments): mixed
    {
        return $target->{$method}(...$arguments);
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private static function page(array $items, ?string $nextLink = null): Response
    {
        return new Response(200, json_encode(['data' => $items, 'nextLink' => $nextLink], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param class-string $enum
     *
     * @return array<string, int|string>
     */
    private static function cases(string $enum): array
    {
        $cases = [];

        $all = $enum::cases();
        self::assertIsArray($all);

        foreach ($all as $case) {
            self::assertInstanceOf(BackedEnum::class, $case);
            $cases[$case->name] = $case->value;
        }

        return $cases;
    }

    private static function property(object $object, string $name): mixed
    {
        return new ReflectionProperty($object, $name)->getValue($object);
    }

    /**
     * @param class-string $class
     */
    private static function propertyType(string $class, string $name): string
    {
        $type = new ReflectionProperty($class, $name)->getType();
        self::assertInstanceOf(ReflectionNamedType::class, $type);

        return $type->getName();
    }

    private static function enumValue(mixed $case): int|string
    {
        self::assertInstanceOf(BackedEnum::class, $case);

        return $case->value;
    }
}
