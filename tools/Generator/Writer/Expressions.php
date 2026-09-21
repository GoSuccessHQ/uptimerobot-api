<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator\Writer;

use GoSuccess\UptimeRobot\Tools\Generator\Config\ApiConfig;
use GoSuccess\UptimeRobot\Tools\Generator\Naming;
use GoSuccess\UptimeRobot\Tools\Generator\PhpType;
use GoSuccess\UptimeRobot\Tools\Generator\Registry;
use LogicException;

/**
 * PHP expressions that convert between decoded JSON and the generated types,
 * shared by the model and resource writers.
 */
final readonly class Expressions
{
    public const string CAST = ApiConfig::RUNTIME . '\\Model\\Cast';
    public const string JSON = ApiConfig::RUNTIME . '\\Model\\Json';
    public const string UNDEFINED = ApiConfig::RUNTIME . '\\Model\\Undefined';
    public const string REQUEST_MODEL = ApiConfig::RUNTIME . '\\Model\\RequestModel';
    public const string RESPONSE_MODEL = ApiConfig::RUNTIME . '\\Model\\ResponseModel';

    public function __construct(private Registry $registry) {}

    /**
     * An expression reading a decoded JSON value as the type; null (or an empty
     * array for lists and maps) when the value is missing or mistyped.
     */
    public function read(PhpType $type, string $input, CodeFile $file): string
    {
        $cast = $file->alias(self::CAST);
        $alias = $file->alias(...);

        return match ($type->kind) {
            PhpType::STRING => "{$cast}::string({$input})",
            PhpType::INT => "{$cast}::int({$input})",
            PhpType::FLOAT => "{$cast}::float({$input})",
            PhpType::BOOL => "{$cast}::bool({$input})",
            PhpType::DATE => "{$cast}::dateTime({$input})",
            PhpType::ENUM => "{$cast}::{$type->backing}Enum({$alias($type->classOrFail())}::class, {$input})",
            PhpType::MODEL => "{$cast}::model({$alias($type->classOrFail())}::class, {$input})",
            PhpType::UNION => $this->union($type, $input, $file),
            PhpType::LIST => $type->itemOrFail()->kind === PhpType::MODEL
                ? "{$cast}::modelList({$alias($type->itemOrFail()->classOrFail())}::class, {$input})"
                : "{$cast}::listOf({$input}, {$this->converter($type->itemOrFail(), $file)})",
            PhpType::MAP => "{$cast}::mapOf({$input}, {$this->converter($type->itemOrFail(), $file)})",
            PhpType::OBJECT => "{$cast}::object({$input})",
            PhpType::MIXED => $input,
            default => throw new LogicException("Unknown kind {$type->kind}."),
        };
    }

    /**
     * A closure expression converting one element: Closure(mixed): ?T.
     */
    public function converter(PhpType $type, CodeFile $file): string
    {
        $cast = $file->alias(self::CAST);
        $alias = $file->alias(...);

        return match ($type->kind) {
            PhpType::STRING => "{$cast}::string(...)",
            PhpType::INT => "{$cast}::int(...)",
            PhpType::FLOAT => "{$cast}::float(...)",
            PhpType::BOOL => "{$cast}::bool(...)",
            PhpType::DATE => "{$cast}::dateTime(...)",
            PhpType::OBJECT => "{$cast}::object(...)",
            PhpType::ENUM, PhpType::MODEL, PhpType::UNION => "static fn(mixed \$value): ?{$alias($type->classOrFail())} => {$this->read($type, '$value', $file)}",
            PhpType::LIST, PhpType::MAP => "static fn(mixed \$value): array => {$this->read($type, '$value', $file)}",
            PhpType::MIXED => 'static fn(mixed $value): mixed => $value',
            default => throw new LogicException("Unknown kind {$type->kind}."),
        };
    }

    /**
     * An expression turning a PHP value into its JSON payload form. Nested
     * models and maps become objects even when empty, i.e. `{}` rather than
     * the `[]` json_encode() writes for an empty array.
     */
    public function serialize(PhpType $type, string $expression, bool $nullable, CodeFile $file): string
    {
        $safe = $nullable ? '?->' : '->';

        return match ($type->kind) {
            PhpType::STRING, PhpType::INT, PhpType::FLOAT, PhpType::BOOL, PhpType::MIXED => $expression,
            PhpType::ENUM => "{$expression}{$safe}value",
            PhpType::MODEL, PhpType::UNION => $nullable
                ? "{$expression} === null ? null : {$file->alias(self::JSON)}::map({$expression}->toArray())"
                : "{$file->alias(self::JSON)}::map({$expression}->toArray())",
            PhpType::DATE => $nullable
                ? "{$expression} === null ? null : {$file->alias(self::JSON)}::date({$expression})"
                : "{$file->alias(self::JSON)}::date({$expression})",
            PhpType::OBJECT => $nullable
                ? "{$expression} === null ? null : {$file->alias(self::JSON)}::map({$expression})"
                : "{$file->alias(self::JSON)}::map({$expression})",
            PhpType::LIST, PhpType::MAP => $this->collection($type, $expression, $nullable, $file),
            default => throw new LogicException("Unknown kind {$type->kind}."),
        };
    }

    /**
     * A list as a JSON array, a map as a JSON object (`{}` when empty); a null
     * is sent as it is.
     */
    private function collection(PhpType $type, string $expression, bool $nullable, CodeFile $file): string
    {
        $item = $type->itemOrFail();
        $mapped = self::needsMapping($item) ? "array_map({$this->serializer($item, $file)}, {$expression})" : $expression;
        $value = $type->kind === PhpType::MAP ? "{$file->alias(self::JSON)}::map({$mapped})" : $mapped;

        return $nullable && $value !== $expression ? "{$expression} === null ? null : {$value}" : $value;
    }

    /**
     * An expression turning a request body parameter into the array that
     * Connection::json() takes, or null for no body. Connection sends an
     * empty array as `{}` itself.
     */
    public function payload(PhpType $type, string $expression, bool $nullable, CodeFile $file): string
    {
        return match ($type->kind) {
            PhpType::MODEL, PhpType::UNION => $nullable ? "{$expression}?->toArray()" : "{$expression}->toArray()",
            PhpType::MAP => match (true) {
                !self::needsMapping($type->itemOrFail()) => $expression,
                $nullable => "{$expression} === null ? null : array_map({$this->serializer($type->itemOrFail(), $file)}, {$expression})",
                default => "array_map({$this->serializer($type->itemOrFail(), $file)}, {$expression})",
            },
            default => throw new LogicException("A request body of type {$type->kind} is not supported."),
        };
    }

    /**
     * Reading a union: the variant the discriminator names, else the fallback.
     */
    private function union(PhpType $type, string $input, CodeFile $file): string
    {
        $union = $this->registry->unions[$type->classOrFail()] ?? throw new LogicException("Unknown union {$type->class}.");
        $fallback = $union->fallback ?? throw new LogicException("{$union->source}: a union read from responses needs a fallback.");
        $constant = Naming::constant($union->discriminator);
        $variants = [];

        foreach ($union->variants as $class) {
            $short = $file->alias($class);
            $variants[] = "{$short}::{$constant} => {$short}::class";
        }

        $discriminator = str_replace(['\\', "'"], ['\\\\', "\\'"], $union->discriminator);

        return "{$file->alias(self::CAST)}::union({$input}, '{$discriminator}', [" . implode(', ', $variants) . "], {$file->alias($fallback)}::class)";
    }

    private static function needsMapping(PhpType $item): bool
    {
        return !$item->isScalar() && $item->kind !== PhpType::MIXED;
    }

    /**
     * A closure serializing one element of a list or map.
     */
    private function serializer(PhpType $item, CodeFile $file): string
    {
        $alias = $file->alias(...);

        return match ($item->kind) {
            PhpType::ENUM => "static fn({$alias($item->classOrFail())} \$item): {$item->backing} => \$item->value",
            PhpType::MODEL, PhpType::UNION => "static fn({$alias($item->classOrFail())} \$item): array|{$alias('stdClass')} => {$file->alias(self::JSON)}::map(\$item->toArray())",
            PhpType::DATE => "{$file->alias(self::JSON)}::date(...)",
            default => "static fn(mixed \$item): mixed => {$this->serialize($item, '$item', false, $file)}",
        };
    }
}
