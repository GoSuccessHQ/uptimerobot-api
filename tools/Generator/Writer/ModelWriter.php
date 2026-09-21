<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator\Writer;

use GoSuccess\UptimeRobot\Tools\Generator\Definition\ModelDefinition;
use GoSuccess\UptimeRobot\Tools\Generator\Definition\PropertyDefinition;
use GoSuccess\UptimeRobot\Tools\Generator\Definition\UnionDefinition;
use GoSuccess\UptimeRobot\Tools\Generator\Naming;
use GoSuccess\UptimeRobot\Tools\Generator\PhpType;
use GoSuccess\UptimeRobot\Tools\Generator\Registry;
use LogicException;

/**
 * Renders model classes.
 *
 * Three shapes exist, depending on where a model is used:
 *
 * - response only: promoted readonly properties with plain types and fromArray();
 * - request only: promoted readonly properties typed `T|Undefined(|null)`, so a
 *   payload holds exactly what the caller passed, and toArray();
 * - both: plain-typed properties for reading plus a record of which fields were
 *   provided, so toArray() sends only those (partial updates).
 *
 * Reading rules: lists and maps are never null; enums, dates and unions are
 * always nullable (an unknown case or an unparsable date must not break a
 * response); other values follow the specification's nullability and fall back
 * to their zero value when the API omits a field the specification promises.
 *
 * Writing rules: null is sent where the specification allows it. A shared model
 * clears a list or map with an empty value instead, since it reads them as
 * never null; a request-only model sends null for a nullable list or map too,
 * where null means something else than empty (e.g. "remove the key" in a
 * merged object).
 *
 * The variant of a union has its discriminator value as a constant instead of
 * a property, and sends it with every payload; with an envelope, its own
 * properties are nested in that property.
 */
final class ModelWriter
{
    private readonly Expressions $expressions;

    public function __construct(private readonly Registry $registry)
    {
        $this->expressions = new Expressions($registry);
    }

    public function render(ModelDefinition $model, string $source): string
    {
        $namespace = substr($model->class, 0, (int) strrpos($model->class, '\\'));
        $short = substr($model->class, (int) strrpos($model->class, '\\') + 1);
        $file = new CodeFile($namespace, $short);

        $body = match (true) {
            $model->request && $model->response => $this->shared($model, $file, $short),
            $model->request => $this->requestOnly($model, $file, $short),
            default => $this->responseOnly($model, $file, $short),
        };

        return $file->render($body, $source);
    }

    private function responseOnly(ModelDefinition $model, CodeFile $file, string $short): string
    {
        $parameters = '';
        $arguments = '';

        foreach ($model->properties as $property) {
            $type = $this->readType($property, $model, $file);
            $parameters .= $this->propertyDoc($property, $type, '        ');
            $parameters .= "        public {$type['native']} \${$property->phpName} = {$type['default']},\n";
            $arguments .= "            {$property->phpName}: {$this->readExpression($property, $model, $file)},\n";
        }

        $constructor = $parameters === '' ? "    public function __construct() {}\n" : "    public function __construct(\n{$parameters}    ) {}\n";
        $fromArray = $arguments === ''
            ? "    public static function fromArray(array \$data): static\n    {\n        return new self();\n    }\n"
            : "    public static function fromArray(array \$data): static\n    {\n{$this->unwrapEnvelope($model)}        return new self(\n{$arguments}        );\n    }\n";

        return $this->classDoc($model) . "final readonly class {$short} implements {$this->interfaces($model, $file)}\n{\n{$this->constant($model)}{$constructor}\n{$fromArray}}\n";
    }

    private function requestOnly(ModelDefinition $model, CodeFile $file, string $short): string
    {
        $undefined = $file->alias(Expressions::UNDEFINED);
        $properties = array_values(array_filter($model->properties, static fn(PropertyDefinition $property): bool => !$property->readOnly));
        // PHP requires required parameters before optional ones.
        usort($properties, static fn(PropertyDefinition $a, PropertyDefinition $b): int => (int) $b->required <=> (int) $a->required);

        $parameters = '';
        $body = '';

        foreach ($properties as $property) {
            $type = $this->writeType($property, $file, !$property->required, requestOnly: true);
            $parameters .= $this->propertyDoc($property, $type, '        ');
            $default = $property->required ? '' : " = {$undefined}::Value";
            $parameters .= "        public {$type['native']} \${$property->phpName}{$default},\n";

            $value = $this->expressions->serialize($property->type, "\$this->{$property->phpName}", $this->writeNullable($property, requestOnly: true), $file);
            $assignment = "\$data['{$this->escape($property->jsonName)}'] = {$value};";
            $body .= $property->required
                ? "        {$assignment}\n"
                : "\n        if (!\$this->{$property->phpName} instanceof {$undefined}) {\n            {$assignment}\n        }\n";
        }

        $constructor = $parameters === '' ? "    public function __construct() {}\n" : "    public function __construct(\n{$parameters}    ) {}\n";

        return $this->classDoc($model) . "final readonly class {$short} implements {$this->interfaces($model, $file)}\n{\n{$this->constant($model)}{$constructor}\n{$this->toArray($model, $body, $file)}}\n";
    }

    private function shared(ModelDefinition $model, CodeFile $file, string $short): string
    {
        $undefined = $file->alias(Expressions::UNDEFINED);

        $declarations = '';
        $parameters = '';
        $paramDocs = [];
        $assignments = '';
        $provided = '';
        $arguments = '';
        $serialization = '';

        foreach ($model->properties as $property) {
            $read = $this->readType($property, $model, $file);
            $declarations .= $this->propertyDoc($property, $read, '    ');
            $declarations .= "    public {$read['native']} \${$property->phpName};\n\n";

            $write = $this->writeType($property, $file, true);
            $parameters .= "        {$write['native']} \${$property->phpName} = {$undefined}::Value,\n";

            if ($write['doc'] !== null) {
                $paramDocs[] = "@param {$write['doc']} \${$property->phpName}";
            }

            $name = $property->phpName;
            $value = "\${$name}";

            if ($property->type->kind === PhpType::DATE) {
                // The constructor takes any DateTimeInterface; the property is immutable.
                $immutable = $file->alias('DateTimeImmutable');
                $value = str_contains($write['native'], 'null')
                    ? "(\${$name} === null ? null : {$immutable}::createFromInterface(\${$name}))"
                    : "{$immutable}::createFromInterface(\${$name})";
            }

            $assignments .= "        \$this->{$name} = \${$name} instanceof {$undefined} ? {$read['default']} : {$value};\n";

            $key = $this->escape($property->jsonName);

            if (!$property->readOnly) {
                $provided .= "            '{$key}' => !\${$name} instanceof {$undefined},\n";
                $value = $this->expressions->serialize($property->type, "\$this->{$name}", $read['nullable'], $file);
                $serialization .= "\n        if (isset(\$this->provided['{$key}'])) {\n            \$data['{$key}'] = {$value};\n        }\n";
            }

            $expression = $this->readExpression($property, $model, $file, raw: true);
            $arguments .= $property->type->isCollection()
                ? "            {$name}: {$expression} ?: {$undefined}::Value,\n"
                : "            {$name}: {$expression} ?? {$undefined}::Value,\n";
        }

        $constructorDoc = $paramDocs === [] ? '' : Doc::block([$paramDocs], '    ');
        $providedDoc = Doc::block([['Payload keys the caller provided; toArray() sends exactly these.'], ['@var array<string, true>']], '    ');
        $providedArray = $provided === '' ? '[]' : "array_filter([\n{$provided}        ])";
        $fromArray = $arguments === ''
            ? "    public static function fromArray(array \$data): static\n    {\n        return new self();\n    }\n\n"
            : "    public static function fromArray(array \$data): static\n    {\n{$this->unwrapEnvelope($model)}        return new self(\n{$arguments}        );\n    }\n\n";

        return $this->classDoc($model)
            . "final readonly class {$short} implements {$this->interfaces($model, $file)}\n{\n"
            . $this->constant($model)
            . $declarations
            . $providedDoc . "    private array \$provided;\n\n"
            . $constructorDoc
            . ($parameters === '' ? "    public function __construct()\n    {\n" : "    public function __construct(\n{$parameters}    ) {\n")
            . $assignments
            . "        \$this->provided = {$providedArray};\n    }\n\n"
            . $fromArray
            . $this->toArray($model, $serialization, $file)
            . "}\n";
    }

    /**
     * toArray() from the statements that fill $data: the discriminator of a
     * variant comes first, an envelope wraps the fields.
     */
    private function toArray(ModelDefinition $model, string $statements, CodeFile $file): string
    {
        $union = $model->union;
        $initial = '[]';
        $result = '$data';

        if ($union !== null) {
            $discriminator = "'{$this->escape($union->discriminator)}' => self::" . Naming::constant($union->discriminator);

            if ($union->envelope === null) {
                $initial = "[{$discriminator}]";
            } else {
                $result = "[{$discriminator}, '{$this->escape($union->envelope)}' => {$file->alias(Expressions::JSON)}::map(\$data)]";
            }
        }

        if ($statements === '') {
            $value = $result === '$data' ? $initial : str_replace('$data)', "{$initial})", $result);

            return "    public function toArray(): array\n    {\n        return {$value};\n    }\n";
        }

        return "    public function toArray(): array\n    {\n        \$data = {$initial};\n{$statements}\n        return {$result};\n    }\n";
    }

    /**
     * The discriminator value of a variant, as a constant.
     */
    private function constant(ModelDefinition $model): string
    {
        $union = $model->union;

        if ($union === null) {
            return '';
        }

        $value = \is_int($model->discriminatorValue)
            ? (string) $model->discriminatorValue
            : "'{$this->escape((string) $model->discriminatorValue)}'";
        $doc = Doc::block([["The \"{$union->discriminator}\" of this variant of {@see " . self::short($union->interface) . '}.']], '    ');

        return "{$doc}    public const {$union->backing} " . Naming::constant($union->discriminator) . " = {$value};\n\n";
    }

    /**
     * Statement reading the properties of a variant from its envelope.
     */
    private function unwrapEnvelope(ModelDefinition $model): string
    {
        $envelope = $model->union?->envelope;

        if ($envelope === null) {
            return '';
        }

        $key = $this->escape($envelope);

        return "        \$data = \\is_array(\$data['{$key}'] ?? null) ? \$data['{$key}'] : [];\n\n";
    }

    private function interfaces(ModelDefinition $model, CodeFile $file): string
    {
        $interfaces = [];
        $union = $model->union;

        if ($union !== null) {
            $interfaces[] = $file->alias($union->interface);
        }

        if ($model->request && !self::covers($union, request: true)) {
            $interfaces[] = $file->alias(Expressions::REQUEST_MODEL);
        }

        if ($model->response && !self::covers($union, request: false)) {
            $interfaces[] = $file->alias(Expressions::RESPONSE_MODEL);
        }

        return implode(', ', $interfaces);
    }

    /**
     * Whether the interface of a union already extends RequestModel or ResponseModel.
     */
    private static function covers(?UnionDefinition $union, bool $request): bool
    {
        return $union !== null && ($request ? $union->request : $union->response);
    }

    /**
     * @return array{native: string, doc: string|null, default: string, nullable: bool}
     */
    private function readType(PropertyDefinition $property, ModelDefinition $model, CodeFile $file): array
    {
        $type = $property->type;
        $alias = $file->alias(...);

        if ($type->isCollection()) {
            return ['native' => 'array', 'doc' => $type->doc($alias), 'default' => '[]', 'nullable' => false];
        }

        $nullable = $this->isNullableRead($property, $model);
        $native = $type->native($alias);

        if ($type->kind === PhpType::MIXED) {
            return ['native' => 'mixed', 'doc' => null, 'default' => 'null', 'nullable' => true];
        }

        if ($nullable) {
            return [
                'native' => "?{$native}",
                'doc' => $type->needsDoc() ? $type->doc($alias) . '|null' : null,
                'default' => 'null',
                'nullable' => true,
            ];
        }

        $default = match ($type->kind) {
            PhpType::STRING => "''",
            PhpType::INT => '0',
            PhpType::FLOAT => '0.0',
            PhpType::BOOL => 'false',
            PhpType::OBJECT => '[]',
            PhpType::MODEL => "new {$native}()",
            default => throw new LogicException("No zero value for {$type->kind}."),
        };

        return ['native' => $native, 'doc' => $type->needsDoc() ? $type->doc($alias) : null, 'default' => $default, 'nullable' => false];
    }

    private function isNullableRead(PropertyDefinition $property, ModelDefinition $model): bool
    {
        return match ($property->type->kind) {
            PhpType::ENUM, PhpType::DATE, PhpType::MIXED, PhpType::UNION => true,
            PhpType::MODEL => $property->nullable || $this->reaches($property->type->classOrFail(), $model->class, []),
            default => $property->nullable,
        };
    }

    /**
     * Whether $from can reach $target through non-nullable model properties;
     * such a cycle must be broken with a nullable property.
     *
     * @param array<string, true> $seen
     */
    private function reaches(string $from, string $target, array $seen): bool
    {
        if ($from === $target) {
            return true;
        }

        if (isset($seen[$from])) {
            return false;
        }

        $seen[$from] = true;

        foreach ($this->registry->models[$from]->properties as $property) {
            if ($property->type->kind === PhpType::MODEL && !$property->nullable && $this->reaches($property->type->classOrFail(), $target, $seen)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{native: string, doc: string|null}
     */
    private function writeType(PropertyDefinition $property, CodeFile $file, bool $optional, bool $requestOnly = false): array
    {
        $type = $property->type;
        $alias = $file->alias(...);
        $undefined = $optional ? '|' . $file->alias(Expressions::UNDEFINED) : '';
        $nullable = $this->writeNullable($property, $requestOnly);

        if ($type->kind === PhpType::MIXED) {
            return ['native' => 'mixed', 'doc' => null];
        }

        $native = $type->native($alias, true) . $undefined . ($nullable ? '|null' : '');
        $doc = $type->needsDoc() ? $type->doc($alias, true) . $undefined . ($nullable ? '|null' : '') : null;

        if (!$optional && $nullable) {
            $native = '?' . $type->native($alias, true);
        }

        return ['native' => $native, 'doc' => $doc];
    }

    /**
     * Whether null can be sent. A shared model clears lists and maps with an
     * empty value rather than null, since its properties read them as never null.
     */
    private function writeNullable(PropertyDefinition $property, bool $requestOnly = false): bool
    {
        return $property->nullable && ($requestOnly || !$property->type->isCollection()) && $property->type->kind !== PhpType::MIXED;
    }

    private function readExpression(PropertyDefinition $property, ModelDefinition $model, CodeFile $file, bool $raw = false): string
    {
        $type = $property->type;
        $expression = $this->expressions->read($type, "\$data['{$this->escape($property->jsonName)}'] ?? null", $file);

        if ($raw || $type->isCollection() || $type->kind === PhpType::MIXED) {
            return $expression;
        }

        $read = $this->readType($property, $model, $file);

        return $read['nullable'] ? $expression : "{$expression} ?? {$read['default']}";
    }

    /**
     * @param array{native: string, doc: string|null} $type
     */
    private function propertyDoc(PropertyDefinition $property, array $type, string $indent): string
    {
        $tags = [];

        if ($type['doc'] !== null) {
            $tags[] = "@var {$type['doc']}";
        }

        if ($property->deprecated) {
            $tags[] = '@deprecated';
        }

        return Doc::block([Doc::lines($property->description), $tags], $indent);
    }

    private function classDoc(ModelDefinition $model): string
    {
        $tags = [];

        if ($model->deprecated) {
            $tags[] = '@deprecated';
        }

        $schema = $model->variantSource === null
            ? $model->source
            : "{$model->variantSource} (\"{$model->union?->envelope}\": {$model->source})";

        return Doc::block([Doc::lines($model->description), ["Schema: {$schema}"], $tags]);
    }

    private function escape(string $key): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $key);
    }

    private static function short(string $class): string
    {
        return substr($class, (int) strrpos($class, '\\') + 1);
    }
}
