<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator;

use GoSuccess\UptimeRobot\Tools\Generator\Config\MethodConfig;
use GoSuccess\UptimeRobot\Tools\Generator\Config\ResourceConfig;
use GoSuccess\UptimeRobot\Tools\Generator\Definition\MethodDefinition;
use GoSuccess\UptimeRobot\Tools\Generator\Definition\ParameterDefinition;
use GoSuccess\UptimeRobot\Tools\Generator\Definition\ResourceDefinition;
use RuntimeException;

/**
 * Turns the resource configuration plus the specification into method definitions.
 */
final class ResourceBuilder
{
    /**
     * Content types that mark an operation as non-JSON; such operations must be
     * implemented by hand.
     */
    private const array RAW_TYPES = ['application/octet-stream', 'text/csv', 'application/pdf'];

    /** @var list<string> */
    public array $notes = [];

    public function __construct(private readonly Registry $registry) {}

    public function build(ResourceConfig $config): ResourceDefinition
    {
        $class = $this->registry->config->fqcn('Resource', $config->class);
        $resource = new ResourceDefinition($config, $class);

        foreach ($config->methods as $name => $methodConfig) {
            $method = $this->method($methodConfig);

            if ($methodConfig->handwritten) {
                if (!$config->handwritten) {
                    throw new RuntimeException("{$config->class}::{$name} is hand-written, but the resource does not mix in its trait.");
                }

                $resource->handwritten[$name] = $method;
            } else {
                $resource->methods[] = $method;
            }
        }

        return $resource;
    }

    private function method(MethodConfig $config): MethodDefinition
    {
        $operation = $this->registry->spec->operation($config->operation);
        $method = new MethodDefinition($config, $operation);
        $context = "{$operation->id} ({$config->name})";

        if ($config->pagination !== null) {
            $method->pagination = $this->registry->config->pagination[$config->pagination]
                ?? throw new RuntimeException("{$context}: unknown pagination style {$config->pagination}.");
        }

        if ($config->all !== null && $config->pagination === null) {
            throw new RuntimeException("{$context}: \"all\" needs a pagination style.");
        }

        $path = $this->pathParameters($operation, $config, $context);

        // Hand-written methods build their own request bodies.
        [$bodyRequired, $bodyOptional] = $config->handwritten ? [[], []] : $this->body($method, $config, $context);

        $required = [];
        $optional = [];
        $cursor = null;
        $size = null;
        $queryNames = [];

        foreach ($operation->parametersIn('query') as $parameter) {
            $queryNames[] = $parameter->name;

            if (\in_array($parameter->name, $config->hidden, true)) {
                continue;
            }

            $definition = $this->queryParameter($parameter, $method, $context);

            match (true) {
                $method->pagination?->cursor === $parameter->name => $cursor = $definition,
                $method->pagination?->size === $parameter->name => $size = $definition,
                $definition->isRequired() => $required[] = $definition,
                default => $optional[] = $definition,
            };
        }

        if ($method->pagination !== null && $cursor === null) {
            throw new RuntimeException("{$context}: there is no query parameter {$method->pagination->cursor} to request further pages with.");
        }

        // "@body" for a request body parameter, else the flattened properties.
        $bodyNames = array_map(static fn(ParameterDefinition $parameter): string => $parameter->specName, [...$bodyRequired, ...$bodyOptional]);

        foreach (array_diff($config->required, $queryNames, $bodyNames) as $unknown) {
            throw new RuntimeException("{$context}: {$unknown} is marked required, but is neither a query parameter nor a flattened body property.");
        }

        $headerNames = [];

        foreach ($operation->parametersIn('header') as $parameter) {
            $headerNames[] = $parameter->name;

            if (!\in_array($parameter->name, $config->hidden, true)) {
                throw new RuntimeException("{$context}: header parameter {$parameter->name} is not supported; hide it or write the method by hand.");
            }
        }

        // A typo, or a parameter the specification renamed, would otherwise
        // silently expose the parameter or change its PHP name.
        foreach (array_diff($config->hidden, $queryNames, $headerNames) as $unknown) {
            throw new RuntimeException("{$context}: {$unknown} is hidden, but is neither a query nor a header parameter.");
        }

        foreach (array_diff($config->ownParameters, $operation->pathPlaceholders(), $queryNames, $bodyNames) as $unknown) {
            throw new RuntimeException("{$context}: 'parameters' renames {$unknown}, which is neither a path or query parameter nor the body or one of its flattened properties.");
        }

        // The cursor and the page size lead the optional parameters, so that
        // list($cursor) works positionally.
        $paging = array_values(array_filter([$cursor, $size]));
        $method->parameters = [...$path, ...$bodyRequired, ...$required, ...$paging, ...$optional, ...$bodyOptional];

        // Hand-written methods read their responses themselves; the models they
        // use are listed as extraModels.
        if (!$config->handwritten) {
            $this->response($method, $config, $operation, $context);
        }

        return $method;
    }

    /**
     * @return list<ParameterDefinition>
     */
    private function pathParameters(Operation $operation, MethodConfig $config, string $context): array
    {
        $byName = [];

        foreach ($operation->parametersIn('path') as $parameter) {
            $byName[$parameter->name] = $parameter;
        }

        $definitions = [];

        foreach ($operation->pathPlaceholders() as $placeholder) {
            $parameter = $byName[$placeholder] ?? throw new RuntimeException("{$context}: path placeholder {$placeholder} is not declared.");
            unset($byName[$placeholder]);

            $type = $this->registry->type($parameter->schema, "{$operation->id}.{$placeholder}");

            if (!\in_array($type->kind, [PhpType::INT, PhpType::STRING, PhpType::ENUM, PhpType::DATE], true)) {
                throw new RuntimeException("{$context}: unsupported path parameter type {$type->kind} for {$placeholder}.");
            }

            $definitions[] = new ParameterDefinition(
                specName: $placeholder,
                phpName: $config->parameters[$placeholder] ?? Naming::camel($placeholder),
                type: $type,
                location: ParameterDefinition::PATH,
                nullable: false,
                default: null,
                description: $parameter->description(),
            );
        }

        foreach (array_keys($byName) as $stray) {
            $this->notes[] = "{$context}: ignored path parameter {$stray}, which does not occur in {$operation->path}.";
        }

        return $definitions;
    }

    /**
     * @return array{list<ParameterDefinition>, list<ParameterDefinition>}
     */
    private function body(MethodDefinition $method, MethodConfig $config, string $context): array
    {
        $operation = $method->operation;

        if ($config->body === 'none') {
            return [[], []];
        }

        // With several content types, e.g. JSON or multipart/form-data for a
        // file upload, the JSON variant is used.
        $schema = $config->body !== null ? $this->componentRef($config->body) : $operation->requestSchema();

        if ($schema === null) {
            foreach ($operation->requestContentTypes() as $type) {
                if (!str_contains($type, 'json')) {
                    throw new RuntimeException("{$context}: request body {$type} needs a hand-written method.");
                }
            }

            return [[], []];
        }

        if ($config->flatten) {
            return $this->flattenedBody($schema, $operation, $config, $context);
        }

        $type = $this->registry->type($schema, "{$operation->id}.body");

        // A bare {"type": "object"}: the API accepts no content, but insists on
        // an application/json body, so an empty object is sent.
        if ($type->kind === PhpType::OBJECT && $schema->resolve()->isBareObject()) {
            $method->emptyBody = true;

            return [[], []];
        }

        if (!\in_array($type->kind, [PhpType::MODEL, PhpType::MAP, PhpType::UNION], true)) {
            throw new RuntimeException("{$context}: request body of type {$type->kind} is not supported.");
        }

        $this->registry->markUsage($type, true);

        $required = $operation->isRequestBodyRequired();
        // Named after the model, e.g. $stormProtectionUpdate; a map has no class.
        $name = $config->parameters['@body'] ?? ($type->class === null ? 'payload' : lcfirst(substr($type->class, (int) strrpos($type->class, '\\') + 1)));
        $definition = new ParameterDefinition(
            specName: '@body',
            phpName: $name === 'array' ? 'payload' : $name,
            type: $type,
            location: ParameterDefinition::PAYLOAD,
            nullable: !$required,
            default: $required ? null : 'null',
            description: $schema->resolve()->description(),
        );

        return $required ? [[$definition], []] : [[], [$definition]];
    }

    /**
     * @return array{list<ParameterDefinition>, list<ParameterDefinition>}
     */
    private function flattenedBody(Schema $schema, Operation $operation, MethodConfig $config, string $context): array
    {
        $resolved = $schema->resolve();
        $source = $schema->resolvedName() ?? "{$operation->id}.body";
        $requiredNames = $resolved->required();
        $required = [];
        $optional = [];

        foreach ($resolved->properties() as $json => $property) {
            if ($property->isReadOnly()) {
                continue;
            }

            $type = $this->registry->type($property, "{$source}.{$json}");
            $this->registry->markUsage($type, true);

            // The body repeats a path parameter: send the same value.
            if (\in_array($json, $operation->pathPlaceholders(), true)) {
                $required[] = new ParameterDefinition(
                    specName: $json,
                    phpName: $config->parameters[$json] ?? Naming::camel($json),
                    type: $type,
                    location: ParameterDefinition::BOUND,
                    nullable: false,
                    default: null,
                    description: null,
                );

                continue;
            }

            // Marked required in the configuration: a value is expected, not null.
            $forced = \in_array($json, $config->required, true);
            $isRequired = $forced || \in_array($json, $requiredNames, true);
            $propertyNullable = !$forced && ($property->isNullable() || $property->resolve()->isNullable());

            $definition = new ParameterDefinition(
                specName: $json,
                phpName: $config->parameters[$json] ?? Naming::camel($json),
                type: $type,
                location: ParameterDefinition::BODY,
                nullable: !$isRequired || $propertyNullable,
                default: $isRequired ? null : 'null',
                description: $property->description() ?? $property->resolve()->description(),
            );

            if ($isRequired) {
                $required[] = $definition;
            } else {
                $optional[] = $definition;
            }
        }

        if ($required === [] && $optional === []) {
            throw new RuntimeException("{$context}: flattened body has no properties.");
        }

        return [$required, $optional];
    }

    private function queryParameter(Parameter $parameter, MethodDefinition $method, string $context): ParameterDefinition
    {
        $path = "{$method->operation->id}.{$parameter->name}";
        $type = $this->registry->type($parameter->schema, $path);

        if (\in_array($type->kind, [PhpType::MODEL, PhpType::MAP, PhpType::UNION], true)) {
            throw new RuntimeException("{$context}: query parameter {$parameter->name} of type {$type->kind} is not supported.");
        }

        $configured = $this->registry->isCommaSeparated($path);

        if ($configured && $parameter->isCommaSeparated()) {
            throw new RuntimeException("commaSeparated: the specification documents {$path} as comma-separated now; remove the entry.");
        }

        $commaSeparated = $configured || $parameter->isCommaSeparated();

        if ($commaSeparated && ($type->kind !== PhpType::LIST || !\in_array($type->itemOrFail()->kind, [PhpType::STRING, PhpType::INT, PhpType::ENUM], true))) {
            throw new RuntimeException("{$context}: the comma-separated query parameter {$parameter->name} must be a list of strings, integers or enums; give it an array type in 'types'.");
        }

        $name = $method->config->parameters[$parameter->name] ?? Naming::camel($parameter->name);
        $pagination = $method->pagination;
        $required = $parameter->required || \in_array($parameter->name, $method->config->required, true);
        $default = $required ? null : 'null';
        $nullable = !$required;
        $description = $parameter->description();

        if ($pagination !== null && $parameter->name === $pagination->cursor) {
            if ($type->kind !== PhpType::INT && $type->kind !== PhpType::STRING) {
                throw new RuntimeException("{$context}: the cursor {$parameter->name} must be an integer or a string, not {$type->kind}.");
            }

            $description = 'The cursor of the page to return, as the previous page reported it; null for the first page.';
            $default = 'null';
            $nullable = true;
        } elseif ($pagination !== null && $parameter->name === $pagination->size) {
            $description ??= 'The number of items per page.';

            if ($pagination->pageSize !== null) {
                $default = (string) $pagination->pageSize;
                $nullable = false;
            }
        }

        return new ParameterDefinition(
            specName: $parameter->name,
            phpName: $name,
            type: $type,
            location: ParameterDefinition::QUERY,
            nullable: $nullable,
            default: $default,
            description: $description,
            commaSeparated: $commaSeparated,
        );
    }

    private function response(MethodDefinition $method, MethodConfig $config, Operation $operation, string $context): void
    {
        $method->unwrap = $config->unwrap;
        $responses = $operation->successResponses();

        foreach ($operation->successContentTypes() as $type) {
            if (\in_array($type, self::RAW_TYPES, true) && $config->response === null) {
                throw new RuntimeException("{$context}: returns {$type} and needs a hand-written method.");
            }
        }

        if ($config->response === 'void') {
            if ($config->unwrap !== null) {
                throw new RuntimeException("{$context}: a void response has nothing to unwrap.");
            }

            return;
        }

        $schema = null;

        if ($config->response !== null) {
            $isList = str_starts_with($config->response, 'list:');
            $schema = $this->componentRef($isList ? substr($config->response, 5) : $config->response);

            if ($isList) {
                $schema = new Schema($schema->spec, ['type' => 'array', 'items' => $schema->node]);
            }
        } else {
            foreach ($responses as $candidate) {
                if ($candidate !== null) {
                    $schema = $candidate;

                    break;
                }
            }

            $method->nullable = $schema !== null && \in_array(null, $responses, true);
        }

        $method->nullable = $method->nullable || $config->nullable;

        if ($schema === null) {
            if ($config->unwrap !== null || $method->pagination !== null) {
                throw new RuntimeException("{$context}: the operation returns no content.");
            }

            return;
        }

        if ($method->pagination !== null) {
            $method->returns = $this->pageItemType($schema, $method, $config, $context);

            return;
        }

        if ($method->unwrap !== null) {
            $property = $schema->resolve()->properties()[$method->unwrap] ?? throw new RuntimeException("{$context}: response has no property {$method->unwrap} to unwrap.");
            $type = $this->registry->type($property, ($schema->resolvedName() ?? "{$operation->id}.response") . ".{$method->unwrap}");
        } else {
            $type = $this->registry->type($schema, "{$operation->id}.response");
        }

        $this->registry->markUsage($type, false);
        $method->returns = $type;
    }

    /**
     * The item type of a paginated response: the items property of the page
     * envelope, or the configured item schema when the specification documents
     * the response wrongly.
     */
    private function pageItemType(Schema $schema, MethodDefinition $method, MethodConfig $config, string $context): PhpType
    {
        $pagination = $method->pagination ?? throw new RuntimeException("{$context}: not paginated.");

        if ($config->response !== null) {
            $type = $this->registry->type($schema, "{$method->operation->id}.item");
        } else {
            $properties = $schema->resolve()->properties();
            $items = $properties[$pagination->items] ?? throw new RuntimeException("{$context}: response has no {$pagination->items} property.");

            if (!isset($properties[$pagination->next])) {
                // The factory would find no next page, so all() would end after the first.
                throw new RuntimeException("{$context}: response has no {$pagination->next} property, from which the {$pagination->name} pagination reads the next page.");
            }

            $list = $this->registry->type($items, ($schema->resolvedName() ?? "{$method->operation->id}.response") . ".{$pagination->items}");

            if ($list->kind !== PhpType::LIST) {
                throw new RuntimeException("{$context}: {$pagination->items} is not a list.");
            }

            $type = $list->itemOrFail();
        }

        $this->registry->markUsage($type, false);

        return $type;
    }

    private function componentRef(string $name): Schema
    {
        if (!$this->registry->spec->hasSchema($name)) {
            throw new RuntimeException("Unknown schema {$name}.");
        }

        return new Schema($this->registry->spec, ['$ref' => "#/components/schemas/{$name}"]);
    }
}
