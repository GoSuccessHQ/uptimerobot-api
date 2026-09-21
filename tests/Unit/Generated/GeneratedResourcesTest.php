<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Generated;

use BackedEnum;
use GoSuccess\UptimeRobot\Http\Query;
use GoSuccess\UptimeRobot\Http\Response;
use GoSuccess\UptimeRobot\Model\RequestModel;
use GoSuccess\UptimeRobot\Pagination\Page;
use GoSuccess\UptimeRobot\Pagination\Paginator;
use GoSuccess\UptimeRobot\Tests\Support\Generated\GeneratedCode;
use GoSuccess\UptimeRobot\Tests\Support\Generated\Samples;
use GoSuccess\UptimeRobot\Tests\Support\MockHttpClient;
use GoSuccess\UptimeRobot\Tools\Generator\Analysis;
use GoSuccess\UptimeRobot\Tools\Generator\Definition\MethodDefinition;
use GoSuccess\UptimeRobot\Tools\Generator\Definition\ParameterDefinition;
use GoSuccess\UptimeRobot\Tools\Generator\PhpType;
use LogicException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;

/**
 * Calls every generated resource method against a mocked transport and checks
 * that the request matches the specification: HTTP method, path, query
 * parameters and body; and that the response is mapped onto the declared type.
 */
#[CoversNothing]
final class GeneratedResourcesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function methods(): iterable
    {
        foreach (GeneratedCode::all() as $code => $analysis) {
            foreach ($analysis->resources as $resource) {
                foreach ($resource->methods as $method) {
                    yield "{$code} {$resource->config->property}->{$method->config->name}()" => [$code, $resource->config->property, $method->config->name];
                }
            }
        }
    }

    /**
     * Deprecated operations report every call, which the test expects.
     */
    #[DataProvider('methods')]
    #[IgnoreDeprecations('^Method .+ is deprecated, This endpoint is deprecated by UptimeRobot\.$')]
    public function testSendsTheSpecifiedRequestAndMapsTheResponse(string $code, string $property, string $name): void
    {
        $analysis = GeneratedCode::analysis($code);
        $method = $this->method($analysis, $property, $name);
        $samples = new Samples($analysis->registry);

        [$arguments, $expectedPath, $expectedQuery, $expectedBody] = $this->arguments($method, $samples);
        $http = new MockHttpClient(new Response($method->returns === null ? 204 : 200, $this->responseBody($method, $samples)));
        $resource = GeneratedCode::client($analysis, $http)->{$property};
        self::assertIsObject($resource);

        if ($method->operation->isDeprecated()) {
            // Reported by PHP on every call; the request is still sent.
            $this->expectUserDeprecationMessage('Method ' . $resource::class . "::{$name}() is deprecated, This endpoint is deprecated by UptimeRobot.");
        }

        $result = $resource->{$name}(...$arguments);

        self::assertSame(1, $http->callCount());
        $request = $http->requests[0];
        self::assertSame($method->operation->method, $request->method->value);

        $uri = parse_url($request->uri);
        self::assertIsArray($uri);
        // The base URI has a path of its own: https://api.uptimerobot.com/v3.
        $basePath = rtrim((string) parse_url($analysis->config->baseUri, \PHP_URL_PATH), '/');
        self::assertSame("{$basePath}/{$expectedPath}", $uri['path'] ?? null);
        self::assertSame(Query::build($expectedQuery), $uri['query'] ?? '');

        if ($expectedBody === null) {
            self::assertNull($request->body);
        } else {
            self::assertSame('application/json', $request->headers['Content-Type'] ?? null);
            self::assertEquals(Samples::normalize($expectedBody), $http->jsonBody());
        }

        $this->assertResult($method, $samples, $result);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function paginatedMethods(): iterable
    {
        foreach (GeneratedCode::all() as $code => $analysis) {
            foreach ($analysis->resources as $resource) {
                foreach ($resource->methods as $method) {
                    if ($method->pagination !== null && $method->config->all !== null) {
                        yield "{$code} {$resource->config->property}->{$method->config->all}()" => [$code, $resource->config->property, $method->config->name];
                    }
                }
            }
        }
    }

    #[DataProvider('paginatedMethods')]
    public function testPaginatorsIterateOverAllItems(string $code, string $property, string $name): void
    {
        $analysis = GeneratedCode::analysis($code);
        $method = $this->method($analysis, $property, $name);
        $cursor = $method->cursor() ?? throw new LogicException('No cursor.');
        $all = $method->config->all ?? throw new LogicException('No paginator method.');

        $samples = new Samples($analysis->registry);
        [$arguments] = $this->arguments($method, $samples);
        unset($arguments[$cursor->phpName]);

        $http = new MockHttpClient(new Response(200, $this->responseBody($method, $samples)));
        $paginator = GeneratedCode::client($analysis, $http)->{$property}->{$all}(...$arguments);

        self::assertInstanceOf(Paginator::class, $paginator);
        self::assertCount(1, iterator_to_array($paginator));
        self::assertSame(1, $http->callCount());
        self::assertStringNotContainsString('cursor=', $http->requests[0]->uri);
    }

    private function method(Analysis $analysis, string $property, string $name): MethodDefinition
    {
        foreach ($analysis->resources as $resource) {
            if ($resource->config->property !== $property) {
                continue;
            }

            foreach ($resource->methods as $method) {
                if ($method->config->name === $name) {
                    return $method;
                }
            }
        }

        throw new LogicException("Unknown method {$property}->{$name}().");
    }

    /**
     * @return array{array<string, mixed>, string, array<string, mixed>, array<array-key, mixed>|null}
     */
    private function arguments(MethodDefinition $method, Samples $samples): array
    {
        $arguments = [];
        $query = [];
        $fields = [];
        $payload = null;
        $path = ltrim($method->operation->path, '/');

        foreach ($method->parameters as $parameter) {
            if ($parameter->location === ParameterDefinition::BOUND) {
                // Filled from the path parameter, which precedes it.
                $fields[$parameter->specName] = $arguments[$parameter->phpName];

                continue;
            }

            $value = $samples->php($parameter->type);
            $arguments[$parameter->phpName] = $value;

            match ($parameter->location) {
                ParameterDefinition::PATH => $path = str_replace("{{$parameter->specName}}", rawurlencode(Query::format('path', $value)), $path),
                ParameterDefinition::QUERY => $query[$parameter->specName] = $parameter->commaSeparated ? self::joined($value) : $value,
                ParameterDefinition::BODY => $fields[$parameter->specName] = $samples->json($parameter->type),
                ParameterDefinition::PAYLOAD => $payload = $value instanceof RequestModel ? $value->toArray() : (\is_array($value) ? $value : null),
                default => throw new LogicException("Unknown location {$parameter->location}."),
            };
        }

        // A bare object body is sent as {}.
        $body = $payload ?? ($fields === [] ? ($method->emptyBody ? [] : null) : $fields);

        return [$arguments, $path, $query, $body];
    }

    /**
     * A list as a comma-separated query value.
     */
    private static function joined(mixed $value): string
    {
        self::assertIsArray($value);

        return implode(',', array_map(static fn(mixed $item): string => $item instanceof BackedEnum ? (string) $item->value : Query::format('item', $item), $value));
    }

    private function responseBody(MethodDefinition $method, Samples $samples): string
    {
        if ($method->returns === null) {
            return '';
        }

        $value = $samples->json($method->returns);

        if ($method->pagination !== null) {
            $value = [$method->pagination->items => [$value]];
        } elseif ($method->unwrap !== null) {
            $value = [$method->unwrap => $value];
        }

        return json_encode($value, \JSON_THROW_ON_ERROR);
    }

    private function assertResult(MethodDefinition $method, Samples $samples, mixed $result): void
    {
        $type = $method->returns;

        if ($type === null) {
            self::assertNull($result);

            return;
        }

        if ($method->pagination !== null) {
            self::assertInstanceOf(Page::class, $result);
            self::assertCount(1, $result->items);
            self::assertInstanceOf($samples->classOf($type), $result->items[0]);
            self::assertFalse($result->hasMore);

            return;
        }

        match ($type->kind) {
            PhpType::MODEL, PhpType::ENUM, PhpType::UNION => self::assertInstanceOf($samples->classOf($type), $result),
            PhpType::LIST => $this->assertList($samples, $type, $result),
            PhpType::MAP, PhpType::OBJECT => self::assertIsArray($result),
            PhpType::STRING => self::assertIsString($result),
            PhpType::INT => self::assertIsInt($result),
            PhpType::FLOAT => self::assertIsFloat($result),
            PhpType::BOOL => self::assertIsBool($result),
            default => self::assertNotNull($result),
        };
    }

    private function assertList(Samples $samples, PhpType $type, mixed $result): void
    {
        self::assertIsList($result);
        self::assertCount(1, $result);
        $item = $type->itemOrFail();

        if (\in_array($item->kind, [PhpType::MODEL, PhpType::UNION, PhpType::ENUM], true)) {
            self::assertInstanceOf($samples->classOf($item), $result[0]);
        }
    }
}
