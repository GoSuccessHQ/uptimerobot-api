<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Generated;

use GoSuccess\UptimeRobot\Http\Connection;
use GoSuccess\UptimeRobot\Model\ResponseModel;
use GoSuccess\UptimeRobot\Tests\Support\Generated\GeneratedCode;
use GoSuccess\UptimeRobot\Tests\Support\Generated\Samples;
use GoSuccess\UptimeRobot\Tools\Generator\Definition\ModelDefinition;
use GoSuccess\UptimeRobot\Tools\Generator\Definition\PropertyDefinition;
use GoSuccess\UptimeRobot\Tools\Generator\PhpType;
use LogicException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use stdClass;

/**
 * Checks every generated model against the specification it was generated
 * from: each property is read from its JSON key with its type, and request
 * payloads contain exactly what the caller provided, plus the discriminator
 * of a union's variant.
 */
#[CoversNothing]
final class GeneratedModelsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function responseModels(): iterable
    {
        foreach (GeneratedCode::all() as $code => $analysis) {
            foreach ($analysis->registry->models as $model) {
                if ($model->response) {
                    yield "{$code} {$model->source}" => [$code, $model->class];
                }
            }
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function requestModels(): iterable
    {
        foreach (GeneratedCode::all() as $code => $analysis) {
            foreach ($analysis->registry->models as $model) {
                if ($model->request) {
                    yield "{$code} {$model->source}" => [$code, $model->class];
                }
            }
        }
    }

    #[DataProvider('responseModels')]
    public function testReadsEveryPropertyFromItsJsonKey(string $code, string $class): void
    {
        [$model, $samples] = $this->definition($code, $class);
        $payload = $samples->payload($model);
        $fields = $model->union?->envelope === null ? $payload : $payload[$model->union->envelope];
        self::assertIsArray($fields);

        $instance = $class::fromArray($payload);
        self::assertInstanceOf(ResponseModel::class, $instance);

        foreach ($model->properties as $property) {
            if (!\array_key_exists($property->jsonName, $fields)) {
                continue;
            }

            $this->assertRead($samples, $property->type, $fields[$property->jsonName], $instance->{$property->phpName}, "{$class}::\${$property->phpName}");
        }
    }

    /**
     * Unions read from responses, whose fallback reads the properties every variant has.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function responseUnions(): iterable
    {
        foreach (GeneratedCode::all() as $code => $analysis) {
            foreach ($analysis->registry->unions as $union) {
                if ($union->response && $union->fallback !== null) {
                    yield "{$code} {$union->source}" => [$code, $union->interface];
                }
            }
        }
    }

    #[DataProvider('responseUnions')]
    public function testFallbacksReadThePropertiesEveryVariantHas(string $code, string $interface): void
    {
        $registry = GeneratedCode::analysis($code)->registry;
        $samples = new Samples($registry);
        $union = $registry->unions[$interface];
        $fallback = $union->fallback ?? throw new LogicException("{$interface} has no fallback.");

        // A variant's payload, but of a kind this client does not know.
        $payload = [...$samples->payload($samples->firstVariant(new PhpType(PhpType::UNION, $interface))), $union->discriminator => $union->backing === 'int' ? 999 : 'UNKNOWN_KIND'];
        $instance = $fallback::fromArray($payload);

        self::assertTrue(interface_exists($interface));
        self::assertInstanceOf($interface, $instance);
        self::assertSame($payload, new ReflectionProperty($instance, 'data')->getValue($instance));

        foreach ($registry->sharedProperties($union) as $property) {
            self::assertArrayHasKey($property->jsonName, $payload);
            $this->assertRead($samples, $property->type, $payload[$property->jsonName], $instance->{$property->phpName}, "{$fallback}::\${$property->phpName}");
        }
    }

    #[DataProvider('responseModels')]
    public function testToleratesAnEmptyPayload(string $code, string $class): void
    {
        self::assertInstanceOf(ResponseModel::class, $class::fromArray([]));
    }

    #[DataProvider('requestModels')]
    public function testSerializesEveryProvidedProperty(string $code, string $class): void
    {
        [$model, $samples] = $this->definition($code, $class);

        $instance = $samples->requestModel($model);

        self::assertEquals(
            Samples::normalize($samples->payload($model, forRequest: true)),
            Samples::normalize($instance->toArray()),
        );
    }

    #[DataProvider('requestModels')]
    public function testOmitsPropertiesThatWereNotProvided(string $code, string $class): void
    {
        [$model, $samples] = $this->definition($code, $class);

        // Only what the constructor requires, e.g. the name of a new group.
        $instance = $samples->requestModel($model, requiredOnly: true);
        $required = array_map(
            static fn(PropertyDefinition $property): string => $property->jsonName,
            array_filter($model->properties, static fn(PropertyDefinition $property): bool => $property->required && !$property->readOnly),
        );

        $payload = $samples->payload($model, forRequest: true);
        $union = $model->union;
        $fields = $union?->envelope === null ? $payload : $payload[$union->envelope];
        self::assertIsArray($fields);
        $fields = array_intersect_key($fields, array_flip($required));
        self::assertSame([], array_diff($required, array_keys($fields)), 'Every required property has a sample value.');

        // A variant always sends its discriminator, and its envelope if it has one.
        $expected = match (true) {
            $union === null => $fields,
            $union->envelope === null => [$union->discriminator => $model->discriminatorValue, ...$fields],
            default => [$union->discriminator => $model->discriminatorValue, $union->envelope => $fields],
        };

        self::assertEquals(Samples::normalize($expected), Samples::normalize($instance->toArray()));
    }

    /**
     * Request models with a nested model (or a list of them) that can be
     * constructed without any field.
     *
     * @return iterable<string, array{string, string, string}>
     */
    public static function nestedRequestModels(): iterable
    {
        foreach (GeneratedCode::all() as $code => $analysis) {
            foreach ($analysis->registry->models as $model) {
                if (!$model->request) {
                    continue;
                }

                foreach ($model->properties as $property) {
                    $nested = self::nestedModel($property);
                    $definition = $nested === null ? null : $analysis->registry->models[$nested];

                    if (!$property->readOnly && $definition !== null && self::constructibleEmpty($definition)) {
                        yield "{$code} {$model->source}.{$property->jsonName}" => [$code, $model->class, $property->jsonName];
                    }
                }
            }
        }
    }

    #[DataProvider('nestedRequestModels')]
    public function testSendsEmptyNestedModelsAsJsonObjects(string $code, string $class, string $json): void
    {
        [$model, $samples] = $this->definition($code, $class);
        $property = array_values(array_filter($model->properties, static fn(PropertyDefinition $property): bool => $property->jsonName === $json))[0];
        $nested = self::nestedModel($property) ?? throw new LogicException("{$class}::\${$property->phpName} holds no model.");
        $empty = new $nested();
        $isList = $property->type->kind === PhpType::LIST;

        $instance = $samples->requestModel($model, overrides: [$property->phpName => $isList ? [$empty] : $empty]);
        // Decoded with objects as stdClass, so that {} and [] differ.
        $payload = json_decode(Connection::encodeJson($instance->toArray()), flags: \JSON_THROW_ON_ERROR);
        self::assertInstanceOf(stdClass::class, $payload);
        $envelope = $model->union?->envelope;
        $fields = $envelope === null ? $payload : $payload->{$envelope};
        self::assertInstanceOf(stdClass::class, $fields);

        $sent = $fields->{$json};

        if ($isList) {
            self::assertIsArray($sent);
            $sent = $sent[0];
        }

        self::assertInstanceOf(stdClass::class, $sent, "An empty {$nested} must be sent as {}.");
    }

    /**
     * The model a property holds, directly or as list items.
     *
     * @return class-string|null
     */
    private static function nestedModel(PropertyDefinition $property): ?string
    {
        $type = $property->type->kind === PhpType::LIST ? $property->type->itemOrFail() : $property->type;

        if ($type->kind !== PhpType::MODEL || !class_exists($type->classOrFail())) {
            return null;
        }

        return $type->classOrFail();
    }

    private static function constructibleEmpty(ModelDefinition $model): bool
    {
        return array_filter($model->properties, static fn(PropertyDefinition $property): bool => $property->required && !$property->readOnly) === [] || $model->response;
    }

    /**
     * @return array{ModelDefinition, Samples}
     */
    private function definition(string $code, string $class): array
    {
        $registry = GeneratedCode::analysis($code)->registry;

        return [$registry->models[$class], new Samples($registry)];
    }

    private function assertRead(Samples $samples, PhpType $type, mixed $json, mixed $actual, string $context): void
    {
        match ($type->kind) {
            PhpType::DATE => self::assertEquals($samples->read($type, $json), $actual, $context),
            PhpType::ENUM => self::assertSame($samples->read($type, $json), $actual, $context),
            PhpType::MODEL, PhpType::UNION => self::assertInstanceOf($samples->classOf($type), $actual, $context),
            PhpType::LIST, PhpType::MAP => $this->assertCollection($samples, $type, $json, $actual, $context),
            default => self::assertSame($json, $actual, $context),
        };
    }

    private function assertCollection(Samples $samples, PhpType $type, mixed $json, mixed $actual, string $context): void
    {
        self::assertIsArray($json, $context);
        self::assertIsArray($actual, $context);
        self::assertSame(array_keys($json), array_keys($actual), $context);

        foreach ($json as $key => $item) {
            $this->assertRead($samples, $type->itemOrFail(), $item, $actual[$key], "{$context}[{$key}]");
        }
    }
}
