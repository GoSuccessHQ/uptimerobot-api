<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator\Config;

/**
 * Configuration of one resource method.
 */
final readonly class MethodConfig
{
    /**
     * @param string                $name        Method name.
     * @param string                $operation   Operation id in the specification.
     * @param bool                  $handwritten Implemented by hand; the generator only
     *                                           documents it and checks coverage.
     * @param string|null           $pagination  Pagination style; generates a page method
     *                                           and, if $all is set, a paginator method.
     * @param string|null           $all         Name of the paginator method.
     * @param string|null           $response    Response override: a schema name (the item
     *                                           schema of paginated methods), "list:<Schema>"
     *                                           or "void".
     * @param string|null           $unwrap      Property of an envelope that holds the payload,
     *                                           e.g. "data" of `{"data": [...]}`.
     * @param bool                  $nullable    Whether a successful response may have no body.
     * @param string|null           $body        Request body schema override, or "none".
     * @param bool                  $flatten     Expose the properties of the request body as
     *                                           method parameters instead of a model.
     * @param array<string, string> $parameters  Spec parameter name => PHP parameter name;
     *                                           "@body" names the request body parameter.
     * @param list<string>          $hidden      Spec parameters that are not exposed.
     * @param list<string>          $required    Query parameters and flattened body properties to
     *                                           treat as required where the specification marks
     *                                           them optional.
     * @param string|null           $note        Extra paragraph for the docblock.
     */
    public function __construct(
        public string $name,
        public string $operation,
        public bool $handwritten = false,
        public ?string $pagination = null,
        public ?string $all = null,
        public ?string $response = null,
        public ?string $unwrap = null,
        public bool $nullable = false,
        public ?string $body = null,
        public bool $flatten = false,
        public array $parameters = [],
        public array $hidden = [],
        public array $required = [],
        public ?string $note = null,
    ) {}

    /**
     * @param array<string, string> $parameters Parameter names of the resource, overridden by the method's.
     */
    public static function fromArray(string $name, ConfigReader $reader, array $parameters = []): self
    {
        $instance = new self(
            name: $name,
            operation: $reader->string('operation'),
            handwritten: $reader->bool('handwritten'),
            pagination: $reader->optionalString('pagination'),
            all: $reader->optionalString('all'),
            response: $reader->optionalString('response'),
            unwrap: $reader->optionalString('unwrap'),
            nullable: $reader->bool('nullable'),
            body: $reader->optionalString('body'),
            flatten: $reader->bool('flatten'),
            parameters: [...$parameters, ...$reader->stringMapAt('parameters')],
            hidden: $reader->stringList('hidden'),
            required: $reader->stringList('required'),
            note: $reader->optionalString('note'),
        );

        $reader->assertNoUnknownKeys();

        return $instance;
    }
}
