<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Support\Generated;

use BackedEnum;
use DateTimeImmutable;
use GoSuccess\UptimeRobot\Model\RequestModel;
use GoSuccess\UptimeRobot\Model\Undefined;
use GoSuccess\UptimeRobot\Tools\Generator\Definition\ModelDefinition;
use GoSuccess\UptimeRobot\Tools\Generator\Definition\PropertyDefinition;
use GoSuccess\UptimeRobot\Tools\Generator\Definition\UnionDefinition;
use GoSuccess\UptimeRobot\Tools\Generator\PhpType;
use GoSuccess\UptimeRobot\Tools\Generator\Registry;
use LogicException;
use stdClass;

/**
 * Builds sample values for generated models from the generator's definitions:
 * the JSON an API would send and the PHP value the client should turn it into.
 */
final class Samples
{
    private const string DATE = '2026-01-02T03:04:05Z';

    /** Nested models deeper than this are left out to break cycles. */
    private const int MAX_DEPTH = 3;

    public function __construct(private readonly Registry $registry) {}

    /**
     * A JSON payload populating every property of a model; for the variant of
     * a union, with its discriminator and envelope.
     *
     * @return array<string, mixed>
     */
    public function payload(ModelDefinition $model, int $depth = 0, bool $forRequest = false): array
    {
        $payload = [];

        foreach ($model->properties as $property) {
            if ($forRequest && $property->readOnly) {
                continue;
            }

            $value = $this->json($property->type, $depth, $forRequest);

            if ($value !== null) {
                $payload[$property->jsonName] = $value;
            } elseif ($forRequest && ($property->required || !$model->response)) {
                // Too deep to nest: requestModel() passes an empty value instead.
                $empty = $this->emptyValue($property);

                if ($empty !== Undefined::Value) {
                    $payload[$property->jsonName] = $empty;
                }
            }
        }

        $union = $model->union;

        if ($union === null) {
            return $payload;
        }

        $discriminator = [$union->discriminator => $model->discriminatorValue];

        return $union->envelope === null ? [...$discriminator, ...$payload] : [...$discriminator, $union->envelope => $payload];
    }

    /**
     * The JSON representation of a sample value of the type, or null when the
     * nesting is too deep.
     */
    public function json(PhpType $type, int $depth = 0, bool $forRequest = false): mixed
    {
        return match ($type->kind) {
            PhpType::STRING => 'sample',
            PhpType::INT => 7,
            PhpType::FLOAT => 1.5,
            PhpType::BOOL => true,
            PhpType::DATE => self::DATE,
            PhpType::ENUM => $this->enumCase($type)->value,
            PhpType::MODEL => $depth >= self::MAX_DEPTH ? null : $this->payload($this->model($type), $depth + 1, $forRequest),
            PhpType::UNION => $depth >= self::MAX_DEPTH ? null : $this->payload($this->firstVariant($type), $depth + 1, $forRequest),
            PhpType::LIST => ($item = $this->json($type->itemOrFail(), $depth, $forRequest)) === null ? null : [$item],
            PhpType::MAP => ($item = $this->json($type->itemOrFail(), $depth, $forRequest)) === null ? null : ['key' => $item],
            PhpType::OBJECT => ['key' => 'value'],
            PhpType::MIXED => 'mixed',
            default => throw new LogicException("Unknown kind {$type->kind}."),
        };
    }

    /**
     * The PHP value a request model or method argument takes for the type.
     */
    public function php(PhpType $type, int $depth = 0): mixed
    {
        return match ($type->kind) {
            PhpType::STRING => 'sample',
            PhpType::INT => 7,
            PhpType::FLOAT => 1.5,
            PhpType::BOOL => true,
            PhpType::DATE => new DateTimeImmutable(self::DATE),
            PhpType::ENUM => $this->enumCase($type),
            PhpType::MODEL => $depth >= self::MAX_DEPTH ? null : $this->requestModel($this->model($type), $depth + 1),
            PhpType::UNION => $depth >= self::MAX_DEPTH ? null : $this->requestModel($this->firstVariant($type), $depth + 1),
            PhpType::LIST => ($item = $this->php($type->itemOrFail(), $depth)) === null ? null : [$item],
            PhpType::MAP => ($item = $this->php($type->itemOrFail(), $depth)) === null ? null : ['key' => $item],
            PhpType::OBJECT => ['key' => 'value'],
            PhpType::MIXED => 'mixed',
            default => throw new LogicException("Unknown kind {$type->kind}."),
        };
    }

    /**
     * Construct a request model with a sample value for every writable property.
     *
     * @param array<string, mixed> $overrides Values to pass instead, by PHP name.
     */
    public function requestModel(ModelDefinition $model, int $depth = 0, array $overrides = []): RequestModel
    {
        $arguments = [];

        foreach ($model->properties as $property) {
            if ($property->readOnly) {
                continue;
            }

            $value = $this->php($property->type, $depth);

            if ($value !== null) {
                $arguments[$property->phpName] = $value;
            } elseif ($property->required || !$model->response) {
                $arguments[$property->phpName] = $this->emptyValue($property);
            }
        }

        $instance = new ($model->class)(...[...$arguments, ...$overrides]);

        if (!$instance instanceof RequestModel) {
            throw new LogicException("{$model->class} is not a request model.");
        }

        return $instance;
    }

    /**
     * The value a property holds after reading the sample JSON.
     */
    public function read(PhpType $type, mixed $json): mixed
    {
        return match ($type->kind) {
            PhpType::DATE => \is_string($json) ? new DateTimeImmutable($json) : null,
            PhpType::ENUM => $this->enumCase($type),
            default => $json,
        };
    }

    /**
     * The class a read value of the type has: the model, the enum or, for a
     * union, its first variant.
     *
     * @return class-string
     */
    public function classOf(PhpType $type): string
    {
        $class = $type->kind === PhpType::UNION ? $this->firstVariant($type)->class : $type->classOrFail();

        if (!class_exists($class) && !enum_exists($class)) {
            throw new LogicException("{$class} does not exist.");
        }

        return $class;
    }

    public function model(PhpType $type): ModelDefinition
    {
        return $this->registry->models[$type->classOrFail()] ?? throw new LogicException("Unknown model {$type->class}.");
    }

    public function union(PhpType $type): UnionDefinition
    {
        return $this->registry->unions[$type->classOrFail()] ?? throw new LogicException("Unknown union {$type->class}.");
    }

    public function firstVariant(PhpType $type): ModelDefinition
    {
        $class = array_values($this->union($type)->variants)[0] ?? throw new LogicException("{$type->class} has no variants.");

        return $this->registry->models[$class];
    }

    public function enumCase(PhpType $type): BackedEnum
    {
        $class = $type->classOrFail();
        $cases = $class::cases();

        if (!\is_array($cases) || !($cases[0] ?? null) instanceof BackedEnum) {
            throw new LogicException("{$class} has no cases.");
        }

        return $cases[0];
    }

    /**
     * Normalize a toArray() result for comparison with a JSON payload:
     * empty maps (stdClass) become arrays.
     */
    public static function normalize(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            return self::normalize((array) $value);
        }

        if (\is_array($value)) {
            return array_map(self::normalize(...), $value);
        }

        return $value;
    }

    private function emptyValue(PropertyDefinition $property): mixed
    {
        if ($property->type->isCollection() || $property->type->kind === PhpType::OBJECT) {
            return [];
        }

        return $property->required ? null : Undefined::Value;
    }
}
