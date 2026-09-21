<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator\Writer;

use GoSuccess\UptimeRobot\Tools\Generator\Config\ApiConfig;
use GoSuccess\UptimeRobot\Tools\Generator\Definition\MethodDefinition;
use GoSuccess\UptimeRobot\Tools\Generator\Definition\ParameterDefinition;
use GoSuccess\UptimeRobot\Tools\Generator\Definition\ResourceDefinition;
use GoSuccess\UptimeRobot\Tools\Generator\PhpType;
use GoSuccess\UptimeRobot\Tools\Generator\Registry;
use LogicException;

/**
 * Renders resource classes.
 */
final class ResourceWriter
{
    private const string METHOD = ApiConfig::RUNTIME . '\\Http\\Method';
    private const string PAGE = ApiConfig::RUNTIME . '\\Pagination\\Page';
    private const string PAGINATOR = ApiConfig::RUNTIME . '\\Pagination\\Paginator';
    private const string BASE = ApiConfig::RUNTIME . '\\Resource\\AbstractResource';

    private readonly Expressions $expressions;

    public function __construct(private readonly ApiConfig $config, Registry $registry)
    {
        $this->expressions = new Expressions($registry);
    }

    public function render(ResourceDefinition $resource, string $source): string
    {
        $namespace = substr($resource->class, 0, (int) strrpos($resource->class, '\\'));
        $short = $resource->config->class;
        $file = new CodeFile($namespace, $short);
        $base = $file->alias(self::BASE);

        $methods = [];

        foreach ($resource->methods as $method) {
            $methods[] = $method->pagination !== null ? $this->paginated($method, $file) : $this->regular($method, $file);
        }

        $trait = '';

        if ($resource->config->handwritten) {
            $trait = '    use ' . $file->alias("{$namespace}\\Handwritten\\{$resource->config->traitName()}") . ";\n\n";
        }

        $doc = Doc::block([[$resource->config->description]]);
        $body = $trait . implode("\n", $methods);

        return $file->render("{$doc}final class {$short} extends {$base}\n{\n" . rtrim($body, "\n") . "\n}\n", $source);
    }

    private function regular(MethodDefinition $method, CodeFile $file): string
    {
        $returns = $this->returnType($method, $file);
        $call = $this->call($method, $file);
        $body = $this->bodyStatements($method, $file);

        if ($method->returns === null) {
            $code = "{$body}        {$call};\n";
        } else {
            $read = $this->read($method->returns, '$data', $method->unwrap, $file);

            if ($method->nullable) {
                $read = "\$data === null ? null : {$read}";
            }

            $code = "{$body}        \$data = {$call};\n\n        return {$read};\n";
        }

        $tags = $returns['doc'] !== null ? ["@return {$returns['doc']}"] : [];

        return $this->docBlock($method, $method->parameters, $file, $tags)
            . $this->deprecation($method, $file)
            . $this->header($method->config->name, $this->signature($method->parameters, $file), $returns['native']) . "{$code}    }\n";
    }

    private function paginated(MethodDefinition $method, CodeFile $file): string
    {
        $pagination = $method->pagination ?? throw new LogicException('Not paginated.');
        $itemType = $method->returns ?? throw new LogicException("{$method->operation->id}: paginated method without item type.");
        $cursor = $method->cursor() ?? throw new LogicException("{$method->operation->id}: paginated method without cursor.");
        $page = $file->alias(self::PAGE);
        $itemDoc = $itemType->doc($file->alias(...));
        $integerCursor = $cursor->type->kind === PhpType::INT;

        [$class, $function] = explode('::', $pagination->factory, 2);
        $factory = $file->alias($this->config->referencedClass($class)) . "::{$function}";

        $items = $this->expressions->read(PhpType::listOf($itemType), "\$data['{$this->escape($pagination->items)}'] ?? null", $file);
        $object = "self::expectObject({$this->call($method, $file)})";

        $list = $this->docBlock($method, $method->parameters, $file, ["@return {$page}<{$itemDoc}>"])
            . $this->deprecation($method, $file)
            . $this->header($method->config->name, $this->signature($method->parameters, $file), $page)
            . $this->bodyStatements($method, $file)
            . "        \$data = {$object};\n\n        return {$factory}(\$data, {$items}, integerCursor: " . ($integerCursor ? 'true' : 'false') . ");\n    }\n";

        if ($method->config->all === null) {
            return $list;
        }

        // The paginator method takes the same parameters, but no cursor.
        $paginator = $file->alias(self::PAGINATOR);
        $allParameters = [];

        foreach ($method->parameters as $parameter) {
            if ($parameter === $cursor) {
                continue;
            }

            if ($parameter->location === ParameterDefinition::QUERY && $parameter->specName === $pagination->size && $pagination->allSize !== null) {
                $parameter = new ParameterDefinition(
                    $parameter->specName,
                    $parameter->phpName,
                    $parameter->type,
                    $parameter->location,
                    false,
                    (string) $pagination->allSize,
                    $parameter->description,
                );
            }

            $allParameters[] = $parameter;
        }

        $arguments = [];

        foreach (self::exposed($method->parameters) as $parameter) {
            $arguments[] = $parameter === $cursor
                ? "{$parameter->phpName}: " . ($integerCursor ? '\\is_int($cursor) ? $cursor : null' : '\\is_string($cursor) ? $cursor : null')
                : "{$parameter->phpName}: \${$parameter->phpName}";
        }

        $summary = "Iterate lazily over every item of {$method->config->name}(), across all pages.";
        $doc = $this->docBlock($method, $allParameters, $file, ["@return {$paginator}<{$itemDoc}>"], $summary);

        $all = $doc
            . $this->deprecation($method, $file)
            . $this->header($method->config->all, $this->signature($allParameters, $file), $paginator)
            . "        return new {$paginator}(fn(int|string|null \$cursor): {$page} => \$this->{$method->config->name}(\n"
            . implode('', array_map(static fn(string $argument): string => "            {$argument},\n", $arguments))
            . "        ));\n    }\n";

        return "{$list}\n{$all}";
    }

    /**
     * Statements preparing a flattened request body.
     */
    private function bodyStatements(MethodDefinition $method, CodeFile $file): string
    {
        $fields = [...$method->parametersIn(ParameterDefinition::BODY), ...$method->parametersIn(ParameterDefinition::BOUND)];

        if ($fields === []) {
            return '';
        }

        $required = [];
        $optional = '';

        foreach ($fields as $field) {
            $key = $this->escape($field->specName);

            if ($field->isRequired()) {
                $required[] = "'{$key}' => " . $this->expressions->serialize($field->type, "\${$field->phpName}", $field->nullable, $file);
            } else {
                $optional .= "\n        if (\${$field->phpName} !== null) {\n            \$body['{$key}'] = {$this->expressions->serialize($field->type, "\${$field->phpName}", false, $file)};\n        }\n";
            }
        }

        $initial = $required === [] ? '[]' : "[\n" . implode('', array_map(static fn(string $pair): string => "            {$pair},\n", $required)) . '        ]';

        return "        \$body = {$initial};\n{$optional}\n";
    }

    /**
     * The connection call, e.g. `$this->connection->json(Method::Get, "monitors/{$id}", [...])`.
     */
    private function call(MethodDefinition $method, CodeFile $file): string
    {
        $httpMethod = $file->alias(self::METHOD) . '::' . ucfirst(strtolower($method->operation->method));
        $arguments = [$httpMethod, $this->path($method)];

        $query = $method->parametersIn(ParameterDefinition::QUERY);
        $hasQuery = $query !== [];

        if ($hasQuery) {
            $pairs = array_map(fn(ParameterDefinition $parameter): string => "            '{$this->escape($parameter->specName)}' => {$this->queryValue($parameter, $file)},\n", $query);
            $arguments[] = "[\n" . implode('', $pairs) . '        ]';
        }

        $payload = $method->payload();
        $body = null;

        if ($payload !== null) {
            $body = $this->expressions->payload($payload->type, "\${$payload->phpName}", $payload->nullable, $file);
        } elseif ($method->parametersIn(ParameterDefinition::BODY) !== [] || $method->parametersIn(ParameterDefinition::BOUND) !== []) {
            $body = '$body';
        } elseif ($method->emptyBody) {
            // Sent as {}.
            $body = '[]';
        }

        if ($body !== null) {
            $arguments[] = $hasQuery ? $body : "body: {$body}";
        }

        return '$this->connection->json(' . implode(', ', $arguments) . ')';
    }

    /**
     * The value of a query parameter; comma-separated lists are joined here,
     * repeated keys are built by the connection.
     */
    private function queryValue(ParameterDefinition $parameter, CodeFile $file): string
    {
        $variable = "\${$parameter->phpName}";

        if (!$parameter->commaSeparated) {
            return $variable;
        }

        $item = $parameter->type->itemOrFail();
        $values = $item->kind === PhpType::ENUM
            ? "array_map(static fn({$file->alias($item->classOrFail())} \$item): {$item->backing} => \$item->value, {$variable})"
            : $variable;
        $joined = "implode(',', {$values})";

        // An empty list filters nothing, so it is left out like null.
        return $parameter->nullable ? "{$variable} === null || {$variable} === [] ? null : {$joined}" : "{$variable} === [] ? null : {$joined}";
    }

    private function path(MethodDefinition $method): string
    {
        $path = ltrim($method->operation->path, '/');
        $byName = [];

        foreach ($method->parametersIn(ParameterDefinition::PATH) as $parameter) {
            $byName[$parameter->specName] = $parameter;
        }

        if ($byName === []) {
            return "'{$this->escape($path)}'";
        }

        $interpolated = preg_replace_callback('/\{([^}]+)\}/', static function (array $match) use ($byName): string {
            $parameter = $byName[$match[1]] ?? throw new LogicException("Unknown placeholder {$match[1]}.");

            return $parameter->type->kind === PhpType::INT ? "{\${$parameter->phpName}}" : "{\$this->segment(\${$parameter->phpName})}";
        }, str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $path));

        return "\"{$interpolated}\"";
    }

    /**
     * The declaration of a method up to its opening brace, which follows a
     * signature wrapped over several lines on the same line (PSR-12).
     */
    private function header(string $name, string $signature, string $returns): string
    {
        $brace = str_contains($signature, "\n") ? " {\n" : "\n    {\n";

        return "    public function {$name}({$signature}): {$returns}{$brace}";
    }

    /**
     * @param list<ParameterDefinition> $parameters
     */
    private function signature(array $parameters, CodeFile $file): string
    {
        $parameters = self::exposed($parameters);

        if ($parameters === []) {
            return '';
        }

        $parts = [];

        foreach ($parameters as $parameter) {
            $type = $this->parameterType($parameter, $file);
            $default = $parameter->default === null ? '' : " = {$parameter->default}";
            $parts[] = "{$type} \${$parameter->phpName}{$default}";
        }

        $inline = implode(', ', $parts);

        if (\strlen($inline) <= 80) {
            return $inline;
        }

        return "\n" . implode('', array_map(static fn(string $part): string => "        {$part},\n", $parts)) . '    ';
    }

    private function parameterType(ParameterDefinition $parameter, CodeFile $file): string
    {
        $native = $parameter->type->native($file->alias(...), true);

        return $parameter->nullable && $native !== 'mixed' ? "?{$native}" : $native;
    }

    /**
     * @return array{native: string, doc: string|null}
     */
    private function returnType(MethodDefinition $method, CodeFile $file): array
    {
        if ($method->returns === null) {
            return ['native' => 'void', 'doc' => null];
        }

        $type = $method->returns;
        $alias = $file->alias(...);
        $native = $type->native($alias);
        $nullable = ($method->nullable || $type->kind === PhpType::UNION) && $native !== 'mixed';

        return [
            'native' => $nullable ? "?{$native}" : $native,
            'doc' => $type->needsDoc() ? $type->doc($alias) . ($nullable ? '|null' : '') : null,
        ];
    }

    /**
     * Expression that reads a decoded response ($data) as the given type.
     */
    private function read(PhpType $type, string $input, ?string $unwrap, CodeFile $file): string
    {
        $alias = $file->alias(...);

        if ($unwrap !== null) {
            $input = "self::expectObject({$input})['{$this->escape($unwrap)}'] ?? null";
        }

        return match ($type->kind) {
            PhpType::MODEL => "self::toModel({$alias($type->classOrFail())}::class, {$input})",
            PhpType::LIST => $type->itemOrFail()->kind === PhpType::MODEL && $input === '$data'
                ? "self::toModelList({$alias($type->itemOrFail()->classOrFail())}::class, {$input})"
                : $this->expressions->read($type, $input, $file),
            PhpType::STRING => "{$this->expressions->read($type, $input, $file)} ?? ''",
            PhpType::INT => "{$this->expressions->read($type, $input, $file)} ?? 0",
            PhpType::FLOAT => "{$this->expressions->read($type, $input, $file)} ?? 0.0",
            PhpType::BOOL => "{$this->expressions->read($type, $input, $file)} ?? false",
            PhpType::OBJECT => "self::expectObject({$input})",
            PhpType::MAP, PhpType::UNION, PhpType::MIXED => $this->expressions->read($type, $input, $file),
            default => throw new LogicException("Unsupported response type {$type->kind}."),
        };
    }

    /**
     * @param list<ParameterDefinition> $parameters
     * @param list<string>              $tags
     */
    private function docBlock(MethodDefinition $method, array $parameters, CodeFile $file, array $tags, ?string $summary = null): string
    {
        $operation = $method->operation;
        $summaryLines = $summary !== null ? [$summary] : Doc::lines($operation->summary());
        $description = $summary !== null ? [] : Doc::lines($operation->description());

        if ($description === $summaryLines) {
            $description = [];
        }

        $endpoint = ["`{$operation->method} {$operation->path}`"];
        $note = Doc::lines($method->config->note);
        $parameters = self::exposed($parameters);

        $paramLines = [];
        $types = [];

        foreach ($parameters as $parameter) {
            $types[] = $this->parameterDocType($parameter, $file);
        }

        $width = $types === [] ? 0 : max(array_map('strlen', $types));
        $nameWidth = $parameters === [] ? 0 : max(array_map(static fn(ParameterDefinition $parameter): int => \strlen($parameter->phpName) + 1, $parameters));

        foreach ($parameters as $index => $parameter) {
            // On one line, escaped like every other text from the specification: a "*/" would end the comment.
            $text = ' ' . preg_replace('/\s+/', ' ', implode(' ', Doc::lines($parameter->description)));
            $paramLines[] = rtrim('@param ' . str_pad($types[$index], $width) . ' ' . str_pad("\${$parameter->phpName}", $nameWidth) . $text);
        }

        if ($method->operation->isDeprecated()) {
            $tags[] = '@deprecated';
        }

        return Doc::block([$summaryLines, $description, $note, $endpoint, $paramLines, $tags], '    ');
    }

    /**
     * The parameters a caller passes; bound body properties come from their
     * path parameter.
     *
     * @param list<ParameterDefinition> $parameters
     *
     * @return list<ParameterDefinition>
     */
    private static function exposed(array $parameters): array
    {
        return array_values(array_filter(
            $parameters,
            static fn(ParameterDefinition $parameter): bool => $parameter->location !== ParameterDefinition::BOUND,
        ));
    }

    private function parameterDocType(ParameterDefinition $parameter, CodeFile $file): string
    {
        $alias = $file->alias(...);
        $doc = $parameter->type->doc($alias, true);

        return $parameter->nullable && $doc !== 'mixed' ? "{$doc}|null" : $doc;
    }

    /**
     * PHP reports every call of a deprecated operation at runtime. The
     * attribute is imported, as php-cs-fixer would otherwise rewrite the file
     * after every run of the generator.
     */
    private function deprecation(MethodDefinition $method, CodeFile $file): string
    {
        if (!$method->operation->isDeprecated()) {
            return '';
        }

        return '    #[' . $file->alias('Deprecated') . "('This endpoint is deprecated by UptimeRobot.')]\n";
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }
}
