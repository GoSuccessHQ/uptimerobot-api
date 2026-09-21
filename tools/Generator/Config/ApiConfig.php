<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator\Config;

use RuntimeException;

/**
 * Generator configuration, loaded from tools/config/<name>.php.
 *
 * All naming decisions and every deviation from the specification live in
 * that file, so they are explicit and reviewable. Keys that address a
 * location in the specification accept `*` as a wildcard; see
 * {@see \GoSuccess\UptimeRobot\Tools\Generator\PathPatterns} for the notation.
 */
final readonly class ApiConfig
{
    /**
     * The namespace of the hand-written runtime (connection, casts, pagination).
     */
    public const string RUNTIME = 'GoSuccess\\UptimeRobot';

    /**
     * @param string                                   $name                  Short name, e.g. "uptimerobot".
     * @param string                                   $title                 Human-readable API name.
     * @param string                                   $spec                  Snapshot name under resources/specs/.
     * @param string                                   $namespace             Root namespace of the generated code; models
     *                                                                        go to <namespace>\Model, enums to \Enum,
     *                                                                        resources to \Resource, the client to the root.
     * @param string                                   $directory             Directory of the root namespace, relative to
     *                                                                        the project root.
     * @param string                                   $client                Class name of the API client.
     * @param string                                   $baseUri               Default base URI.
     * @param string                                   $credential            Name of the constructor argument holding the key.
     * @param string                                   $credentialDescription How to obtain the key, for the docblock.
     * @param list<string>                             $stripPrefixes         Prefixes left out of automatic class names.
     * @param list<string>                             $stripSuffixes         Suffixes left out of automatic class names,
     *                                                                        the first that matches.
     * @param array<string, string|false>              $schemas               Schema or inline object => class name, or false
     *                                                                        to exclude it.
     * @param array<string, string>                    $properties            "Schema.property" => PHP property name.
     * @param array<string, string|false>              $enums                 Location of an inline enum => enum class name, or
     *                                                                        false to keep the plain scalar.
     * @param array<string, array<int|string, string>> $enumCases             Enum class name => value => case name.
     * @param array<string, array<array-key, mixed>>   $types                 Location => schema that replaces the one the
     *                                                                        specification gives.
     * @param list<string>                             $integers              Locations of `number`s that are integers.
     * @param list<string>                             $floats                Locations of `number`s that are floats.
     * @param list<string>                             $mixed                 Locations without a type that hold any JSON value.
     * @param list<string>                             $excludedProperties    Properties left out of the models, e.g. file uploads.
     * @param list<string>                             $nullableProperties    Properties the API sends as null although the
     *                                                                        specification says it never does.
     * @param list<string>                             $commaSeparated        Query parameters whose lists are sent as one
     *                                                                        comma-separated value.
     * @param array<string, UnionConfig>               $unions                Location of a `oneOf` of objects => how to
     *                                                                        generate it.
     * @param list<string>                             $extraModels           Schemas generated as response models for
     *                                                                        hand-written code.
     * @param list<string>                             $extraRequestModels    Schemas generated as request models for
     *                                                                        hand-written code, e.g. a body the method
     *                                                                        completes itself.
     * @param array<string, array<array-key, mixed>>   $additionalSchemas     Schemas the specification lacks, by name.
     * @param array<string, array<array-key, mixed>>   $additionalProperties  Properties the specification lacks, by
     *                                                                        "Schema.property".
     * @param array<string, PaginationConfig>          $pagination            Pagination styles, keyed by name.
     * @param array<string, ResourceConfig>            $resources             Keyed by the client property name.
     * @param array<string, string>                    $ignored               Operation id => reason for not implementing it.
     */
    public function __construct(
        public string $name,
        public string $title,
        public string $spec,
        public string $namespace,
        public string $directory,
        public string $client,
        public string $baseUri,
        public string $credential,
        public string $credentialDescription,
        public array $stripPrefixes,
        public array $stripSuffixes,
        public array $schemas,
        public array $properties,
        public array $enums,
        public array $enumCases,
        public array $types,
        public array $integers,
        public array $floats,
        public array $mixed,
        public array $excludedProperties,
        public array $nullableProperties,
        public array $commaSeparated,
        public array $unions,
        public array $extraModels,
        public array $extraRequestModels,
        public array $additionalSchemas,
        public array $additionalProperties,
        public array $pagination,
        public array $resources,
        public array $ignored,
    ) {}

    public static function load(string $file): self
    {
        $config = require $file;

        if (!\is_array($config)) {
            throw new RuntimeException("{$file} must return an array.");
        }

        return self::fromArray(basename($file, '.php'), $config);
    }

    /**
     * @param array<array-key, mixed> $config
     */
    public static function fromArray(string $name, array $config): self
    {
        $reader = new ConfigReader($config, "{$name}.php");

        $resources = [];

        foreach ($reader->map('resources') as $property => $resource) {
            $resources[(string) $property] = ResourceConfig::fromArray((string) $property, $reader->nested($resource, "resources.{$property}"));
        }

        $pagination = [];

        foreach ($reader->map('pagination') as $style => $definition) {
            $pagination[(string) $style] = PaginationConfig::fromArray((string) $style, $reader->nested($definition, "pagination.{$style}"));
        }

        $unions = [];

        foreach ($reader->map('unions') as $path => $definition) {
            $unions[(string) $path] = UnionConfig::fromArray((string) $path, $reader->nested($definition, "unions.{$path}"));
        }

        $naming = $reader->nested($reader->map('naming'), 'naming');
        $stripPrefixes = $naming->stringList('stripPrefixes');
        $stripSuffixes = $naming->stringList('stripSuffixes');
        $naming->assertNoUnknownKeys();

        // Additions to the specification, each backed by evidence.
        $additions = $reader->nested($reader->map('additions'), 'additions');
        $additionalSchemas = self::fragments($additions->map('schemas'), 'additions.schemas');
        $additionalProperties = self::fragments($additions->map('properties'), 'additions.properties');
        $additions->assertNoUnknownKeys();

        $enumCases = [];

        foreach ($reader->map('enumCases') as $enum => $cases) {
            $enumCases[(string) $enum] = $reader->nested($cases, "enumCases.{$enum}")->stringMap();
        }

        $instance = new self(
            name: $name,
            title: $reader->string('title'),
            spec: $reader->string('spec'),
            namespace: trim($reader->string('namespace'), '\\'),
            directory: trim($reader->string('directory', 'src'), '/'),
            client: $reader->string('client'),
            baseUri: $reader->string('baseUri'),
            credential: $reader->string('credential', 'apiKey'),
            credentialDescription: $reader->string('credentialDescription', 'The API key.'),
            stripPrefixes: $stripPrefixes,
            stripSuffixes: $stripSuffixes,
            schemas: $reader->schemaMap('schemas'),
            properties: $reader->stringMapAt('properties'),
            enums: $reader->schemaMap('enums'),
            enumCases: $enumCases,
            types: self::fragments($reader->map('types'), 'types'),
            integers: $reader->stringList('integers'),
            floats: $reader->stringList('floats'),
            mixed: $reader->stringList('mixed'),
            excludedProperties: $reader->stringList('excludedProperties'),
            nullableProperties: $reader->stringList('nullableProperties'),
            commaSeparated: $reader->stringList('commaSeparated'),
            unions: $unions,
            extraModels: $reader->stringList('extraModels'),
            extraRequestModels: $reader->stringList('extraRequestModels'),
            additionalSchemas: $additionalSchemas,
            additionalProperties: $additionalProperties,
            pagination: $pagination,
            resources: $resources,
            ignored: $reader->stringMapAt('ignored'),
        );

        $reader->assertNoUnknownKeys();

        return $instance;
    }

    /**
     * Schema fragments keyed by name or location.
     *
     * @param array<array-key, mixed> $map
     *
     * @return array<string, array<array-key, mixed>>
     */
    private static function fragments(array $map, string $context): array
    {
        $fragments = [];

        foreach ($map as $name => $fragment) {
            if (!\is_string($name) || !\is_array($fragment)) {
                throw new RuntimeException("{$context}: expected schema fragments keyed by name.");
            }

            $fragments[$name] = $fragment;
        }

        return $fragments;
    }

    /**
     * Fully qualified name of a generated class, e.g. fqcn('Model', 'Monitor').
     */
    public function fqcn(string $subNamespace, string $class): string
    {
        return $subNamespace === '' ? "{$this->namespace}\\{$class}" : "{$this->namespace}\\{$subNamespace}\\{$class}";
    }

    /**
     * The class of a "Class::method" reference in the configuration, relative
     * to the runtime namespace (e.g. "Pagination\Cursor"), or absolute with a
     * leading backslash.
     */
    public function referencedClass(string $class): string
    {
        return str_starts_with($class, '\\') ? substr($class, 1) : self::RUNTIME . "\\{$class}";
    }
}
