<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Generated;

use GoSuccess\UptimeRobot\Model\RequestModel;
use GoSuccess\UptimeRobot\Model\ResponseModel;
use GoSuccess\UptimeRobot\Tests\Support\Generated\GeneratedCode;
use GoSuccess\UptimeRobot\Tests\Support\Generated\Samples;
use GoSuccess\UptimeRobot\Tools\Generator\Definition\ModelDefinition;
use GoSuccess\UptimeRobot\Tools\Generator\Definition\PropertyDefinition;
use GoSuccess\UptimeRobot\Tools\Generator\PhpType;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

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
        $required = array_filter($model->properties, static fn(PropertyDefinition $property): bool => $property->required && !$property->readOnly);

        if ($required !== [] && !$model->response) {
            self::markTestSkipped('The model has required properties.');
        }

        $instance = new $class();
        self::assertInstanceOf(RequestModel::class, $instance);

        // A variant always sends its discriminator, and its envelope if it has one.
        $union = $model->union;
        $expected = match (true) {
            $union === null => [],
            $union->envelope === null => [$union->discriminator => $model->discriminatorValue],
            default => [$union->discriminator => $model->discriminatorValue, $union->envelope => []],
        };

        self::assertSame($expected, Samples::normalize($instance->toArray()));
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
