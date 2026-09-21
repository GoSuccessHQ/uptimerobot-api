<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator\Config;

use RuntimeException;

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
     * @param string|null           $body        Request body schema override, "none", or
     *                                           "empty" to send `{}` as application/json
     *                                           where the specification declares no body.
     * @param bool                  $flatten     Expose the properties of the request body as
     *                                           method parameters instead of a model.
     * @param array<string, string> $parameters  Spec parameter name => PHP parameter name;
     *                                           "@body" names the request body parameter.
     * @param list<string>          $hidden      Query and header parameters that are not exposed.
     * @param list<string>          $required    Query parameters and flattened body properties to
     *                                           treat as required where the specification marks
     *                                           them optional.
     * @param string|null           $note        Extra paragraph for the docblock.
     * @param list<string>          $ownParameters Keys of $parameters the method configures
     *                                             itself, unlike the names the resource
     *                                             shares with all its methods; each must
     *                                             match a parameter of the operation.
     * @param array<string, mixed>  $example     Arguments for the example of the reference
     *                                           page, by PHP parameter name, in addition to
     *                                           the required ones: e.g. what the API needs
     *                                           although the signature cannot require it.
     *                                           Scalars, null and arrays only; see
     *                                           {@see \GoSuccess\UptimeRobot\Tools\Generator\Docs\ExampleBuilder}.
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
        public array $ownParameters = [],
        public array $example = [],
    ) {}

    /**
     * @param array<string, string> $parameters Parameter names of the resource, overridden by the method's.
     */
    public static function fromArray(string $name, ConfigReader $reader, array $parameters = []): self
    {
        $own = $reader->stringMapAt('parameters');
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
            parameters: [...$parameters, ...$own],
            hidden: $reader->stringList('hidden'),
            required: $reader->stringList('required'),
            note: $reader->optionalString('note'),
            ownParameters: array_map(strval(...), array_keys($own)),
            example: self::example($reader->map('example'), $name),
        );

        $reader->assertNoUnknownKeys();

        return $instance;
    }

    /**
     * @param array<array-key, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private static function example(array $arguments, string $method): array
    {
        $checked = [];

        foreach ($arguments as $name => $value) {
            if (!\is_string($name)) {
                throw new RuntimeException("methods.{$method}.example: name the parameters, e.g. ['groupId' => 123].");
            }

            self::assertPlain($value, "methods.{$method}.example.{$name}");
            $checked[$name] = $value;
        }

        return $checked;
    }

    /**
     * Examples are rendered as PHP code: only values with a literal.
     */
    private static function assertPlain(mixed $value, string $context): void
    {
        if (\is_array($value)) {
            foreach ($value as $key => $item) {
                self::assertPlain($item, "{$context}.{$key}");
            }

            return;
        }

        if ($value !== null && !\is_scalar($value)) {
            throw new RuntimeException("{$context}: only scalars, null and arrays can be written as an example.");
        }
    }
}
