<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator;

use GoSuccess\UptimeRobot\Tools\Generator\Config\ApiConfig;
use GoSuccess\UptimeRobot\Tools\Generator\Config\UnionConfig;
use GoSuccess\UptimeRobot\Tools\Generator\Definition\EnumDefinition;
use GoSuccess\UptimeRobot\Tools\Generator\Definition\ModelDefinition;
use GoSuccess\UptimeRobot\Tools\Generator\Definition\PropertyDefinition;
use GoSuccess\UptimeRobot\Tools\Generator\Definition\UnionDefinition;
use RuntimeException;

/**
 * Maps schemas to PHP types and collects the enums, models and unions to
 * generate.
 *
 * Only what the configured operations reach is registered. Where the
 * specification leaves a decision open (an inline enum without a name, a
 * `number` that may be an integer, a property without a type), the location
 * is recorded and reported by {@see problems()} once the whole configuration
 * has been walked, so that one run lists everything the configuration lacks.
 */
final class Registry
{
    /** @var array<string, EnumDefinition> Keyed by class. */
    public array $enums = [];

    /** @var array<string, ModelDefinition> Keyed by class. */
    public array $models = [];

    /** @var array<string, UnionDefinition> Keyed by interface. */
    public array $unions = [];

    public readonly EnumBuilder $enumBuilder;

    /** @var array<string, array{string, string, bool}> Lower-cased class => [source, kind, shareable]. */
    private array $claims = [];

    /** @var array<string, string> Model class => structure of its schema, see signature(). */
    private array $signatures = [];

    /** @var PathPatterns<string|false> */
    private readonly PathPatterns $enumNames;

    /** @var PathPatterns<array<array-key, mixed>> */
    private readonly PathPatterns $types;

    /** @var PathPatterns<bool> */
    private readonly PathPatterns $integers;

    /** @var PathPatterns<bool> */
    private readonly PathPatterns $floats;

    /** @var PathPatterns<bool> */
    private readonly PathPatterns $mixed;

    /** @var PathPatterns<bool> */
    private readonly PathPatterns $excluded;

    /** @var PathPatterns<bool> */
    private readonly PathPatterns $nullable;

    /** @var PathPatterns<bool> */
    private readonly PathPatterns $optional;

    /** @var PathPatterns<bool> */
    private readonly PathPatterns $commaSeparated;

    /** @var PathPatterns<string> */
    private readonly PathPatterns $parameterDescriptions;

    /** @var array<string, true> Locations of inline enums without a configured name. */
    private array $unnamedEnums = [];

    /** @var array<string, true> Locations of numbers classified neither as integers nor as floats. */
    private array $unclassifiedNumbers = [];

    /** @var array<string, true> Locations without a type that are not accepted as mixed. */
    private array $untyped = [];

    /** @var array<string, true> Configured schema names, property names and unions that were used. */
    private array $usedNames = [];

    public function __construct(
        public readonly Spec $spec,
        public readonly ApiConfig $config,
    ) {
        $this->enumBuilder = new EnumBuilder();
        $this->enumNames = new PathPatterns('enums', $config->enums);
        $this->types = new PathPatterns('types', $config->types);
        $this->integers = PathPatterns::of('integers', $config->integers);
        $this->floats = PathPatterns::of('floats', $config->floats);
        $this->mixed = PathPatterns::of('mixed', $config->mixed);
        $this->excluded = PathPatterns::of('excludedProperties', $config->excludedProperties);
        $this->nullable = PathPatterns::of('nullableProperties', $config->nullableProperties);
        $this->optional = PathPatterns::of('optionalProperties', $config->optionalProperties);
        $this->commaSeparated = PathPatterns::of('commaSeparated', $config->commaSeparated);
        $this->parameterDescriptions = new PathPatterns('parameterDescriptions', $config->parameterDescriptions);
    }

    /**
     * Map a schema to a PHP type, registering the enums, models and unions it needs.
     *
     * @param string $path Location of the schema, e.g. "UserDto.activeSubscription";
     *                     see {@see PathPatterns}.
     */
    public function type(Schema $schema, string $path): PhpType
    {
        $schema = $this->override($schema, $path);
        $union = $this->config->unions[$path] ?? null;

        if ($union !== null) {
            $this->usedNames["union:{$path}"] = true;

            return $this->union($schema, $union);
        }

        $name = $schema->resolvedName();

        if ($name !== null && $this->spec->hasSchema($name)) {
            $component = $this->spec->schema($name);

            if ($component->isEnum()) {
                return $this->enumType($component, $this->className($name, 'Enum'), $name);
            }

            if ($this->isModel($component)) {
                return new PhpType(PhpType::MODEL, $this->model($component, $name));
            }

            // A component that merely aliases a scalar, list or map.
            return $this->structural($component, $name);
        }

        $resolved = $schema->resolve();

        if ($resolved->isEnum()) {
            return $this->inlineEnum($resolved, $path);
        }

        if ($this->isModel($resolved)) {
            return new PhpType(PhpType::MODEL, $this->model($resolved, $path));
        }

        return $this->structural($resolved, $path);
    }

    /**
     * Register a component schema as a model (e.g. for hand-written code).
     */
    public function requireModel(string $schemaName): string
    {
        $schema = $this->spec->schema($schemaName);

        if (!$this->isModel($schema)) {
            throw new RuntimeException("{$schemaName} is not an object schema.");
        }

        return $this->model($schema, $schemaName);
    }

    /**
     * Mark a type (and everything it contains) as used in requests or responses.
     */
    public function markUsage(PhpType $type, bool $request): void
    {
        $this->mark($type, $request, []);
    }

    public function isModel(Schema $schema): bool
    {
        return $schema->properties() !== [] || ($schema->variants('allOf') !== [] && $schema->additionalProperties() === null);
    }

    /**
     * Whether a query parameter is configured to be sent comma-separated.
     */
    public function isCommaSeparated(string $path): bool
    {
        return $this->commaSeparated->matches($path);
    }

    /**
     * Whether a property must be sent: the specification requires it, and
     * the configuration does not make it optional.
     *
     * @param string $path     Location of the property, e.g. "CreateMaintenanceWindowDto.date".
     * @param bool   $required Whether the specification requires it.
     */
    public function isRequired(string $path, bool $required): bool
    {
        if (!$this->optional->matches($path)) {
            return $required;
        }

        if (!$required) {
            throw new RuntimeException("optionalProperties: the specification does not require {$path}; remove the entry.");
        }

        return false;
    }

    /**
     * The description configured for a method parameter, e.g.
     * "MonitorsController_list.status", or null to keep the specification's.
     */
    public function parameterDescription(string $path): ?string
    {
        return $this->parameterDescriptions->match($path);
    }

    /**
     * Everything the configuration lacks or has too much of, as messages.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        $problems = [];
        $lists = [
            "Inline enums without a name; name them in 'enums' (or keep the plain type with false)" => $this->unnamedEnums,
            "Numbers that are neither 'integers' nor 'floats'" => $this->unclassifiedNumbers,
            "Locations without a type; give them one in 'types' or accept any JSON value in 'mixed'" => $this->untyped,
        ];

        foreach ($lists as $message => $paths) {
            if ($paths !== []) {
                $problems[] = "{$message}:\n    " . implode("\n    ", array_keys($paths));
            }
        }

        foreach ([$this->enumNames, $this->types, $this->integers, $this->floats, $this->mixed, $this->excluded, $this->nullable, $this->optional, $this->commaSeparated, $this->parameterDescriptions] as $patterns) {
            if ($patterns->unused() !== []) {
                $problems[] = "'{$patterns->key()}' entries that match nothing the configured operations use:\n    " . implode("\n    ", $patterns->unused());
            }
        }

        $unused = [];

        foreach ($this->config->schemas as $source => $class) {
            if ($class !== false && !isset($this->usedNames["schema:{$source}"])) {
                $unused[] = "schemas: {$source}";
            }
        }

        foreach (array_keys($this->config->properties) as $path) {
            if (!isset($this->usedNames["property:{$path}"])) {
                $unused[] = "properties: {$path}";
            }
        }

        $enumClasses = array_map(static fn(string $class): string => substr($class, (int) strrpos($class, '\\') + 1), array_keys($this->enums));

        foreach (array_keys($this->config->enumCases) as $enum) {
            if (!\in_array($enum, $enumClasses, true)) {
                $unused[] = "enumCases: {$enum}";
            }
        }

        foreach ($this->config->unions as $path => $union) {
            $definition = $this->unions[$this->config->fqcn('Model', $union->interface)] ?? null;

            if ($definition === null || !isset($this->usedNames["union:{$path}"])) {
                $unused[] = "unions: {$path}";
            } elseif ($definition->response && $definition->fallback === null) {
                $problems[] = "unions.{$path}: the union is read from responses and needs a \"fallback\" model for unknown discriminator values.";
            } elseif (!$definition->response && $definition->fallback !== null) {
                $problems[] = "unions.{$path}: the union is only sent, so its \"fallback\" would never be used.";
            }
        }

        if ($unused !== []) {
            $problems[] = "Configured names that the configured operations do not use:\n    " . implode("\n    ", $unused);
        }

        return $problems;
    }

    /**
     * The schema configured in 'types' for a location, or the given one. The
     * description, deprecation, nullability and readOnly of the replaced
     * schema are kept unless the replacement states its own: an override
     * fixes the type, and a null the API sends must still be read as null.
     */
    private function override(Schema $schema, string $path): Schema
    {
        $replacement = $this->types->match($path);

        if ($replacement === null) {
            return $schema;
        }

        foreach (['description', 'deprecated'] as $key) {
            if (!\array_key_exists($key, $replacement) && \array_key_exists($key, $schema->node)) {
                $replacement[$key] = $schema->node[$key];
            }
        }

        // Also where a wrapper or reference carries them, as resolve() and model() read them.
        $inherited = [
            'nullable' => $schema->isNullable() || $schema->resolve()->isNullable(),
            'readOnly' => $schema->isReadOnly() || $schema->resolve()->isReadOnly(),
        ];

        foreach ($inherited as $key => $value) {
            if ($value && !\array_key_exists($key, $replacement)) {
                $replacement[$key] = true;
            }
        }

        if ($replacement == $schema->node) {
            throw new RuntimeException("types: the specification documents {$path} like this now; remove the entry.");
        }

        return new Schema($this->spec, $replacement);
    }

    /**
     * @param array<string, true> $seen
     */
    private function mark(PhpType $type, bool $request, array $seen): void
    {
        if ($type->isCollection()) {
            $this->mark($type->itemOrFail(), $request, $seen);

            return;
        }

        if ($type->kind === PhpType::UNION) {
            $union = $this->unions[$type->classOrFail()];

            if ($request) {
                $union->request = true;
            } else {
                $union->response = true;
            }

            foreach ($union->variants as $class) {
                $this->mark(new PhpType(PhpType::MODEL, $class), $request, $seen);
            }

            return;
        }

        if ($type->kind !== PhpType::MODEL || isset($seen[$type->classOrFail()])) {
            return;
        }

        $model = $this->models[$type->classOrFail()];
        $seen[$model->class] = true;

        if ($request) {
            $model->request = true;
        } else {
            $model->response = true;
        }

        foreach ($model->properties as $property) {
            if (!$request || !$property->readOnly) {
                $this->mark($property->type, $request, $seen);
            }
        }
    }

    private function inlineEnum(Schema $schema, string $path): PhpType
    {
        $name = $this->enumNames->match($path);

        if ($name === false) {
            return $this->structural($schema, $path);
        }

        if ($name === null) {
            $this->unnamedEnums[$path] = true;

            // A placeholder; the analysis fails with the list of unnamed enums.
            return PhpType::scalar(PhpType::STRING);
        }

        return $this->enumType($schema, $this->claim($this->config->fqcn('Enum', $name), $path, 'enum', true), $path);
    }

    private function enumType(Schema $schema, string $class, string $source): PhpType
    {
        $short = substr($class, (int) strrpos($class, '\\') + 1);
        $definition = $this->enumBuilder->build($schema, $class, $this->config->enumCases[$short] ?? null, $source);
        $existing = $this->enums[$class] ?? null;

        if ($existing !== null) {
            if ($existing->signature() !== $definition->signature()) {
                throw new RuntimeException("{$source} and " . implode(', ', $existing->schemas) . " both map to {$class} but differ:\n    {$definition->signature()}\n    {$existing->signature()}\nName the cases in enumCases or use different names.");
            }

            if (!\in_array($source, $existing->schemas, true)) {
                $this->enums[$class] = new EnumDefinition($class, $existing->backing, $existing->cases, $existing->description, [...$existing->schemas, $source]);
            }
        } else {
            $this->enums[$class] = $definition;
        }

        return new PhpType(PhpType::ENUM, $class, $definition->backing);
    }

    /**
     * @param string|null $class Class to use instead of the configured or automatic one.
     * @param string|null $skip  Property left out, e.g. the discriminator of a variant.
     */
    private function model(Schema $schema, string $source, ?string $class = null, ?string $skip = null): string
    {
        $class ??= $this->className($source, 'Model');

        if (isset($this->models[$class])) {
            // Several sources may share a class if their structure is identical,
            // e.g. copies of one inline object.
            if ($this->models[$class]->source !== $source && $this->signatures[$class] !== self::signature($schema)) {
                throw new RuntimeException("{$source} and {$this->models[$class]->source} both map to {$class} but differ.");
            }

            return $class;
        }

        $this->signatures[$class] = self::signature($schema);

        $model = new ModelDefinition($class, $source, $schema->description(), $schema->isDeprecated());
        // Register before the properties, so self-references terminate.
        $this->models[$class] = $model;

        $required = $schema->required();
        $names = [];

        foreach ($schema->properties() as $json => $property) {
            $path = "{$source}.{$json}";

            if ($json === $skip || $this->excluded->matches($path)) {
                continue;
            }

            $configured = $this->config->properties[$path] ?? null;

            if ($configured !== null) {
                $this->usedNames["property:{$path}"] = true;
            }

            $phpName = $configured ?? Naming::camel($json);

            if (isset($names[strtolower($phpName)])) {
                throw new RuntimeException("{$source}: properties {$json} and {$names[strtolower($phpName)]} both map to \${$phpName}.");
            }

            $names[strtolower($phpName)] = $json;
            $type = $this->type($property, $path);
            $replaced = $this->override($property, $path);
            $resolved = $replaced->resolve();
            $description = $replaced->description() ?? $resolved->description();

            $model->properties[] = new PropertyDefinition(
                jsonName: $json,
                phpName: $phpName,
                type: $type,
                nullable: $replaced->isNullable() || $resolved->isNullable() || $this->nullable->matches($path),
                required: $this->isRequired($path, \in_array($json, $required, true)),
                readOnly: $replaced->isReadOnly() || $resolved->isReadOnly(),
                deprecated: $replaced->isDeprecated(),
                description: $description,
            );
        }

        return $class;
    }

    /**
     * A `oneOf` of objects that a single-value discriminator property tells apart.
     */
    private function union(Schema $schema, UnionConfig $config): PhpType
    {
        $path = $config->path;
        $interface = $this->claim($this->config->fqcn('Model', $config->interface), $path, 'union', true);
        $variants = $schema->resolve()->variants('oneOf');

        if (\count($variants) < 2) {
            throw new RuntimeException("unions.{$path}: the schema is not a oneOf of several variants.");
        }

        $declared = $schema->resolve()->discriminator();

        if ($declared !== null && $declared['property'] !== $config->discriminator) {
            throw new RuntimeException("unions.{$path}: the specification discriminates by {$declared['property']}, not by {$config->discriminator}.");
        }

        // Discriminator value => [variant schema, source].
        $found = [];

        foreach ($variants as $index => $variant) {
            $resolved = $variant->resolve();
            $name = $variant->resolvedName();
            $property = $resolved->properties()[$config->discriminator] ?? null;
            $values = $property?->resolve()->enum() ?? [];

            if (\count($values) !== 1) {
                throw new RuntimeException("unions.{$path}: variant " . ($name ?? "#{$index}") . " has no {$config->discriminator} with a single value.");
            }

            $value = $values[0];

            if (isset($found[$value])) {
                throw new RuntimeException("unions.{$path}: two variants have the {$config->discriminator} {$value}.");
            }

            $mapped = ($declared['mapping'] ?? [])[(string) $value] ?? null;

            if ($mapped !== null && $mapped !== ($variant->node['$ref'] ?? null)) {
                throw new RuntimeException("unions.{$path}: the discriminator mapping assigns {$value} to {$mapped}, but the variant with that value is " . ($name ?? "#{$index}") . '.');
            }

            $found[$value] = [$resolved, $name ?? "{$path}<{$value}>"];
        }

        $configured = array_map(strval(...), array_keys($config->variants));
        $actual = array_map(strval(...), array_keys($found));

        if (array_diff($actual, $configured) !== [] || array_diff($configured, $actual) !== []) {
            throw new RuntimeException("unions.{$path}: \"variants\" must name exactly the discriminator values " . implode(', ', $actual) . '.');
        }

        $backing = array_filter(array_keys($found), is_int(...)) === array_keys($found) ? 'int' : 'string';
        $classes = [];

        foreach ($found as $value => [, $source]) {
            if (isset($this->config->schemas[$source])) {
                throw new RuntimeException("unions.{$path}: {$source} is named in \"variants\"; remove it from 'schemas'.");
            }

            $classes[$value] = $this->claim($this->config->fqcn('Model', $config->variants[$value]), $source, 'model', true);
        }

        $definition = new UnionDefinition(
            interface: $interface,
            source: $path,
            discriminator: $config->discriminator,
            backing: $backing,
            variants: $classes,
            envelope: $config->envelope,
            fallback: $config->fallback === null ? null : $this->claim($this->config->fqcn('Model', $config->fallback), $path, 'fallback', true),
            description: $config->description ?? $schema->resolve()->description(),
        );

        $existing = $this->unions[$interface] ?? null;

        if ($existing !== null) {
            if ($existing->signature() !== $definition->signature()) {
                throw new RuntimeException("unions.{$path} and unions.{$existing->source} share the interface {$config->interface} but differ.");
            }

            return new PhpType(PhpType::UNION, $interface);
        }

        $this->unions[$interface] = $definition;

        foreach ($found as $value => [$variant, $source]) {
            $class = $classes[$value];

            if ($config->envelope === null) {
                $this->model($variant, $source, $class, $config->discriminator);
            } else {
                $this->envelopeModel($variant, $source, $class, $config);
            }

            $this->models[$class]->union = $definition;
            $this->models[$class]->discriminatorValue = $value;
        }

        return new PhpType(PhpType::UNION, $interface);
    }

    /**
     * A variant shaped `{"type": …, "data": {…}}`: the model takes the
     * properties of "data" as its own.
     */
    private function envelopeModel(Schema $variant, string $source, string $class, UnionConfig $config): void
    {
        $envelope = $config->envelope ?? throw new RuntimeException("unions.{$config->path}: no envelope.");
        $properties = $variant->properties();
        $other = array_diff(array_keys($properties), [$config->discriminator, $envelope]);

        if ($other !== []) {
            throw new RuntimeException("unions.{$config->path}: {$source} has properties besides {$config->discriminator} and {$envelope} (" . implode(', ', $other) . '), so it cannot be flattened.');
        }

        $data = $properties[$envelope] ?? throw new RuntimeException("unions.{$config->path}: {$source} has no property {$envelope}.");
        $data = $this->override($data, "{$source}.{$envelope}");
        $dataSource = $data->resolvedName() ?? "{$source}.{$envelope}";
        $resolved = $data->resolve();

        if (!$this->isModel($resolved)) {
            throw new RuntimeException("unions.{$config->path}: {$source}.{$envelope} is not an object with properties.");
        }

        $this->model($resolved, $dataSource, $class);
        $this->models[$class]->variantSource = $source;
    }

    private function structural(Schema $schema, string $path): PhpType
    {
        return match ($schema->type()) {
            'string' => $this->string($schema, $path),
            'integer' => PhpType::scalar(PhpType::INT),
            'number' => $this->number($path),
            'boolean' => PhpType::scalar(PhpType::BOOL),
            'array' => PhpType::listOf($schema->items() === null ? $this->untypedValue("{$path}[]") : $this->type($schema->items(), "{$path}[]")),
            'object', null => $this->objectType($schema, $path),
            default => throw new RuntimeException("{$path}: unsupported type {$schema->type()}."),
        };
    }

    private function string(Schema $schema, string $path): PhpType
    {
        if ($schema->isBinary()) {
            throw new RuntimeException("{$path} is a file upload (format: binary), which a JSON model cannot carry. Leave it out with 'excludedProperties' and upload it with a hand-written multipart method.");
        }

        return \in_array($schema->format(), ['date-time', 'datetime'], true) ? PhpType::scalar(PhpType::DATE) : PhpType::scalar(PhpType::STRING);
    }

    /**
     * The specification types IDs, counts and durations as "number"; the
     * configuration says which are integers.
     */
    private function number(string $path): PhpType
    {
        $integer = $this->integers->matches($path);
        $float = $this->floats->matches($path);

        if ($integer && $float) {
            throw new RuntimeException("{$path} is listed in both 'integers' and 'floats'.");
        }

        if (!$integer && !$float) {
            // The analysis fails with the list; an int placeholder lets it get there.
            $this->unclassifiedNumbers[$path] = true;
        }

        return PhpType::scalar($float ? PhpType::FLOAT : PhpType::INT);
    }

    private function objectType(Schema $schema, string $path): PhpType
    {
        $values = $schema->additionalProperties();

        if ($values !== null && $values->node !== []) {
            return PhpType::mapOf($this->type($values, "{$path}{}"));
        }

        return $schema->type() === 'object' || $values !== null
            ? PhpType::scalar(PhpType::OBJECT)
            : $this->untypedValue($path);
    }

    /**
     * A location without a type, e.g. the `{}` of an erased zod type or a
     * `oneOf` of scalars: acceptable only where the configuration says that
     * any JSON value may occur.
     */
    private function untypedValue(string $path): PhpType
    {
        if (!$this->mixed->matches($path)) {
            $this->untyped[$path] = true;
        }

        return PhpType::scalar(PhpType::MIXED);
    }

    /**
     * @param 'Enum'|'Model' $kind
     */
    private function className(string $source, string $kind): string
    {
        $configured = $this->config->schemas[$source] ?? null;

        if ($configured === false) {
            throw new RuntimeException("{$source} is excluded in the configuration but still referenced.");
        }

        if ($configured !== null) {
            $this->usedNames["schema:{$source}"] = true;
        }

        return $this->claim($this->config->fqcn($kind, $configured ?? $this->automaticName($source)), $source, $kind === 'Enum' ? 'enum' : 'model', $configured !== null);
    }

    /**
     * Claim a class for a source. Only configured names of the same kind may
     * be shared, and only by identical structures (checked in model(),
     * enumType() and union()); automatic names must not collide.
     *
     * @param 'enum'|'model'|'union'|'fallback' $kind
     */
    private function claim(string $class, string $source, string $kind, bool $shareable): string
    {
        $key = strtolower($class);
        [$claimedBy, $claimedKind, $claimedShareable] = $this->claims[$key] ?? [$source, $kind, $shareable];

        if ($claimedBy !== $source && !($shareable && $claimedShareable && $claimedKind === $kind)) {
            throw new RuntimeException("{$source} and {$claimedBy} both map to {$class}.");
        }

        $this->claims[$key] ??= [$source, $kind, $shareable];

        return $class;
    }

    /**
     * The structure of an object schema without its descriptions.
     */
    private static function signature(Schema $schema): string
    {
        $strip = static function (mixed $node) use (&$strip): mixed {
            if (!\is_array($node)) {
                return $node;
            }

            unset($node['description'], $node['example'], $node['title']);

            return array_map($strip, $node);
        };

        return (string) json_encode($strip($schema->node));
    }

    /**
     * "UserDto" → "User" (configured prefixes and suffixes are left out);
     * inline objects "Parent.property" → "ParentProperty", list items
     * "Parent.property[]" → "ParentPropertyItem", map values "{}" → "Value".
     */
    private function automaticName(string $source): string
    {
        if (!str_contains($source, '.') && !str_contains($source, '<')) {
            return Naming::pascal($this->stripAffixes($source));
        }

        $parent = (string) preg_replace('/[.<].*$/s', '', $source);
        $rest = substr($source, \strlen($parent));
        $parentClass = $this->config->schemas[$parent] ?? null;
        $parentShort = \is_string($parentClass) ? $parentClass : Naming::pascal($this->stripAffixes($parent));
        $suffix = str_replace(['[]', '{}'], ['Item', 'Value'], $rest);

        return $parentShort . Naming::pascal($suffix);
    }

    private function stripAffixes(string $name): string
    {
        foreach ($this->config->stripPrefixes as $prefix) {
            if (str_starts_with($name, $prefix) && preg_match('/^[A-Z]/', substr($name, \strlen($prefix))) === 1) {
                $name = substr($name, \strlen($prefix));

                break;
            }
        }

        foreach ($this->config->stripSuffixes as $suffix) {
            if (str_ends_with($name, $suffix) && \strlen($name) > \strlen($suffix)) {
                return substr($name, 0, -\strlen($suffix));
            }
        }

        return $name;
    }
}
