<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Tools\Generator;

use GoSuccess\UptimeRobot\Http\Response;
use GoSuccess\UptimeRobot\Tests\Support\Generated\GeneratedCode;
use GoSuccess\UptimeRobot\Tests\Support\Generator\KitchenSink;
use GoSuccess\UptimeRobot\Tests\Support\MockHttpClient;
use GoSuccess\UptimeRobot\Tools\Generator\Docs\DocsGenerator;
use GoSuccess\UptimeRobot\Tools\Generator\Docs\ExampleBuilder;
use GoSuccess\UptimeRobot\Tools\Generator\Docs\ExampleCall;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

/**
 * Every example of the reference pages is run against a mocked transport: it
 * must be valid PHP for the real signatures, pass everything the method
 * requires and send a request, so a page never shows a call that fails.
 */
#[CoversClass(DocsGenerator::class)]
#[CoversClass(ExampleBuilder::class)]
final class DocsExamplesTest extends TestCase
{
    /** @var array<string, array<string, string>> Generated code => page => example. */
    private static array $examples = [];

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function examples(): iterable
    {
        foreach (array_keys(GeneratedCode::all()) as $code) {
            foreach (array_keys(self::examplesOf($code)) as $page) {
                yield "{$code} {$page}" => [$code, $page];
            }
        }
    }

    /**
     * The example of a deprecated operation reports the deprecation, like every call.
     */
    #[DataProvider('examples')]
    #[IgnoreDeprecations('^Method .+ is deprecated, This endpoint is deprecated by UptimeRobot\.$')]
    public function testRunsAgainstTheRealSignatures(string $code, string $page): void
    {
        $analysis = GeneratedCode::analysis($code);
        $docs = DocsGenerator::forAnalysis($analysis);
        $example = self::examplesOf($code)[$page];

        self::assertStringContainsString("\n{$docs->setup}\n", $example);

        $http = new MockHttpClient(...array_fill(0, 3, new Response(200, '{}')));
        $client = GeneratedCode::client($analysis, $http);

        // The example with the client of the test instead of a real one.
        self::runExample(str_replace($docs->setup, "{$docs->accessor} = \$client;", $example), $client);

        self::assertSame(1, $http->callCount(), 'The example sends one request.');
    }

    public function testPassesTheConfiguredArgumentsByTheirTypes(): void
    {
        $examples = self::examplesOf('kitchen-sink');

        self::assertStringContainsString(<<<'PHP'
            $result = $uptimeRobot->widgets->update(
                id: 123,
                changes: new WidgetUpdate(
                    kind: WidgetKind::SmallOne,
                    startsAt: new DateTimeImmutable('2026-10-01T00:00:00Z'),
                    groupIds: [1, 2],
                    retry: new RetryPolicy(onTimeout: true),
                ),
            );
            PHP, $examples['widgets/update.md']);
        // The paginator takes the arguments of the list, but not its cursor.
        self::assertStringContainsString('$result = $uptimeRobot->widgets->list(cursor: \'abc2\', groupId: 5);', $examples['widgets/list.md']);
        self::assertStringContainsString('foreach ($uptimeRobot->widgets->all(groupId: 5) as $item) {', $examples['widgets/all.md']);
    }

    public function testFillsInTheRequiredArgumentsOfModelsAndInterfaces(): void
    {
        $examples = self::examplesOf('kitchen-sink');

        // WidgetCreate is passed as its first variant, with its required fields.
        self::assertStringContainsString(
            "\$result = \$uptimeRobot->widgets->create(\n    widget: new HttpWidgetCreate(\n        friendlyName: 'Example',\n        url: 'https://example.com/',\n    ),\n);",
            $examples['widgets/create.md'],
        );
        self::assertStringContainsString('use ' . KitchenSink::class('Model\\HttpWidgetCreate') . ";\n", $examples['widgets/create.md']);
        // A flattened body: the required, nullable date is a date all the same.
        self::assertStringContainsString("content: 'example',\n    publishedAt: new DateTimeImmutable('-7 days'),", $examples['widgets/addNote.md']);
        // Short calls stay on one line.
        self::assertStringContainsString("\$result = \$uptimeRobot->widgets->get(id: 123);\n", $examples['widgets/get.md']);
        self::assertStringContainsString("\$uptimeRobot->widgets->delete(id: 123);\n", $examples['widgets/delete.md']);
    }

    public function testRejectsArgumentsTheMethodDoesNotTake(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('widgets->update(): the example passes $changez, which update() does not take.');

        self::arguments('update', ['changez' => []]);
    }

    public function testRejectsArgumentsTheModelDoesNotTake(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('widgets->update() $changes WidgetUpdate: the example passes $colour, which __construct() does not take.');

        self::arguments('update', ['changes' => ['colour' => 'red']]);
    }

    public function testRejectsValuesAnEnumDoesNotHave(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('widgets->update() $changes WidgetUpdate $kind: ' . KitchenSink::class('Enum\\WidgetKind') . " has no case with the value 'medium'.");

        self::arguments('update', ['changes' => ['kind' => 'medium']]);
    }

    public function testRejectsValuesThatFitNoTypeOfTheParameter(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('widgets->update() $id: string does not fit the type int.');

        self::arguments('update', ['id' => 'seven']);
    }

    /**
     * @param array<string, mixed> $given
     */
    private static function arguments(string $method, array $given): ExampleCall
    {
        $builder = new ExampleBuilder();

        return new ExampleCall($method, $builder->arguments(new ReflectionMethod(KitchenSink::class('Resource\\WidgetResource'), $method), $given, true, "widgets->{$method}()"));
    }

    /**
     * The examples of the reference pages of generated code, by page.
     *
     * @return array<string, string>
     */
    private static function examplesOf(string $code): array
    {
        if (!isset(self::$examples[$code])) {
            self::$examples[$code] = [];

            foreach (DocsGenerator::forAnalysis(GeneratedCode::analysis($code))->render() as $page => $content) {
                if (preg_match('/^## Example\n\n```php\n(.*?)```$/ms', $content, $match) === 1) {
                    self::$examples[$code][$page] = $match[1];
                }
            }
        }

        return self::$examples[$code];
    }

    /**
     * Run the code of an example; $client is in its scope.
     */
    private static function runExample(string $code, object $client): void
    {
        (static function (string $code, object $client): void {
            eval($code);
        })($code, $client);
    }
}
