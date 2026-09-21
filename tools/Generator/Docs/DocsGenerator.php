<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator\Docs;

use BackedEnum;
use ReflectionClass;
use ReflectionEnum;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use RuntimeException;
use UnitEnum;

/**
 * Renders one Markdown reference page per resource method, plus an index.
 *
 * Everything is read from the resource classes themselves (signatures and
 * docblocks), so hand-written methods are documented the same way as
 * generated ones and the pages cannot drift from the code.
 */
final class DocsGenerator
{
    /**
     * @param string                $title           Human-readable API name.
     * @param string                $client          Fully qualified class name of the client.
     * @param string                $accessor        PHP expression reaching the client, e.g. `$uptimeRobot`.
     * @param string                $setup           Code that prepares the accessor.
     * @param list<DocTarget>       $targets
     * @param array<string, string> $implementations Interface => a class implementing it, for examples
     *                                               of parameters typed with an interface.
     */
    public function __construct(
        private readonly string $title,
        private readonly string $client,
        private readonly string $accessor,
        private readonly string $setup,
        private readonly array $targets,
        private readonly array $implementations = [],
    ) {}

    /**
     * @return array<string, string> Relative path under docs/ => content.
     */
    public function render(): array
    {
        $files = [];
        $client = $this->short($this->client);
        $index = "# API Reference\n\nOne page per method of the {$this->title} client `{$client}`. See the [README](../README.md) for an introduction and [examples/](../examples/) for runnable scripts.\n\n```php\n{$this->setup}\n```\n";

        foreach ($this->targets as $target) {
            if (!class_exists($target->class)) {
                throw new RuntimeException("{$target->class} does not exist; run tools/generate.php first.");
            }

            $class = new ReflectionClass($target->class);
            $chain = "{$this->accessor}->{$target->property}";
            $index .= "\n## `{$target->property}`\n\n{$target->description}\n\n";

            foreach ($this->methods($class, $target->methods) as $method) {
                $doc = DocBlock::parse((string) $method->getDocComment());
                $path = "{$target->property}/{$method->getName()}.md";
                $files[$path] = $this->page($chain, $method, $doc);
                $summary = $doc->summary === '' ? '' : ' — ' . rtrim($doc->summary, '.');
                $index .= "- [`{$method->getName()}()`]({$path}){$summary}\n";
            }
        }

        $files['README.md'] = $index;

        return $files;
    }

    /**
     * The documented methods in the given order; every public method must be listed.
     *
     * @param ReflectionClass<object> $class
     * @param list<string>            $names
     *
     * @return list<ReflectionMethod>
     */
    private function methods(ReflectionClass $class, array $names): array
    {
        $public = [];

        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() === $class->getName() && !str_starts_with($method->getName(), '__')) {
                $public[$method->getName()] = $method;
            }
        }

        $methods = [];

        foreach ($names as $name) {
            $methods[] = $public[$name] ?? throw new RuntimeException("{$class->getName()}::{$name}() does not exist.");
            unset($public[$name]);
        }

        if ($public !== []) {
            throw new RuntimeException("{$class->getName()} has undocumented methods: " . implode(', ', array_keys($public)) . '.');
        }

        return $methods;
    }

    private function page(string $chain, ReflectionMethod $method, DocBlock $doc): string
    {
        $name = $method->getName();
        $out = "# `{$chain}->{$name}()`\n\n";
        $out .= "> {$this->title}" . ($doc->endpoint !== null ? " · `{$doc->endpoint}`" : '') . "\n\n";

        if ($doc->deprecated) {
            $out .= "> **Deprecated** by UptimeRobot.\n\n";
        }

        if ($doc->summary !== '') {
            $out .= "{$doc->summary}\n\n";
        }

        foreach ($doc->paragraphs as $paragraph) {
            $out .= "{$paragraph}\n\n";
        }

        $out .= "## Signature\n\n```php\n{$this->signature($method)}\n```\n\n";

        if ($method->getParameters() !== []) {
            $out .= "## Parameters\n\n| Name | Type | Required | Description |\n| --- | --- | --- | --- |\n";

            foreach ($method->getParameters() as $parameter) {
                $type = $doc->params[$parameter->getName()]['type'] ?? $this->typeName($parameter->getType());
                $description = $doc->params[$parameter->getName()]['description'] ?? '';
                $required = $parameter->isOptional() ? 'no' : 'yes';
                $out .= "| `\${$parameter->getName()}` | `" . str_replace('|', '\\|', $type) . "` | {$required} | " . str_replace('|', '\\|', $description) . " |\n";
            }

            $out .= "\n";
        }

        $returns = $doc->return ?? $this->typeName($method->getReturnType());
        $out .= "## Returns\n\n`{$returns}`\n\n";
        $out .= "## Example\n\n```php\n{$this->example($chain, $method)}```\n";

        return $out;
    }

    private function signature(ReflectionMethod $method): string
    {
        $parameters = array_map(fn(ReflectionParameter $parameter): string => $this->parameter($parameter), $method->getParameters());
        $inline = implode(', ', $parameters);
        $return = $this->typeName($method->getReturnType());

        if (\strlen($inline) <= 70) {
            return "public function {$method->getName()}({$inline}): {$return}";
        }

        return "public function {$method->getName()}(\n    " . implode(",\n    ", $parameters) . ",\n): {$return}";
    }

    private function parameter(ReflectionParameter $parameter): string
    {
        $code = $this->typeName($parameter->getType()) . " \${$parameter->getName()}";

        if ($parameter->isDefaultValueAvailable()) {
            $code .= ' = ' . $this->defaultValue($parameter);
        }

        return $code;
    }

    private function defaultValue(ReflectionParameter $parameter): string
    {
        if ($parameter->isDefaultValueConstant()) {
            $constant = (string) $parameter->getDefaultValueConstantName();

            return substr($constant, (int) strrpos($constant, '\\') + ($constant[0] === '\\' ? 1 : 0));
        }

        $value = $parameter->getDefaultValue();

        return match (true) {
            $value === null => 'null',
            \is_bool($value) => $value ? 'true' : 'false',
            \is_int($value), \is_float($value) => (string) $value,
            \is_string($value) => "'{$value}'",
            $value instanceof BackedEnum, $value instanceof UnitEnum => $this->short($value::class) . "::{$value->name}",
            \is_object($value) => 'new ' . $this->short($value::class) . '()',
            default => '[]',
        };
    }

    private function typeName(?ReflectionType $type): string
    {
        if ($type === null) {
            return 'mixed';
        }

        if ($type instanceof ReflectionUnionType) {
            return implode('|', array_map(fn(ReflectionType $inner): string => $this->typeName($inner), $type->getTypes()));
        }

        if (!$type instanceof ReflectionNamedType) {
            return (string) $type;
        }

        $name = $type->isBuiltin() ? $type->getName() : $this->short($type->getName());

        return $type->allowsNull() && $name !== 'mixed' && $name !== 'null' ? "?{$name}" : $name;
    }

    private function example(string $chain, ReflectionMethod $method): string
    {
        $uses = [$this->client];
        $arguments = [];

        foreach ($method->getParameters() as $parameter) {
            if ($parameter->isOptional()) {
                continue;
            }

            [$value, $class] = $this->sampleArgument($parameter);
            $arguments[] = "{$parameter->getName()}: {$value}";

            if ($class !== null) {
                $uses[] = $class;
            }
        }

        $call = "{$chain}->{$method->getName()}(" . implode(', ', $arguments) . ')';
        $returns = $this->typeName($method->getReturnType());
        $statement = match (true) {
            $returns === 'void' => "{$call};\n",
            str_ends_with($returns, 'Paginator') => "foreach ({$call} as \$item) {\n    // ...\n}\n",
            default => "\$result = {$call};\n",
        };

        // Global classes need no import; "use DateTimeImmutable;" would even warn.
        $uses = array_unique(array_filter($uses, static fn(string $class): bool => str_contains($class, '\\')));
        sort($uses);
        $useBlock = implode('', array_map(static fn(string $class): string => "use {$class};\n", $uses));

        return "{$useBlock}\n{$this->setup}\n\n{$statement}";
    }

    /**
     * @return array{string, string|null} The argument code and a class to import.
     */
    private function sampleArgument(ReflectionParameter $parameter): array
    {
        $type = $parameter->getType();
        $name = strtolower($parameter->getName());
        $types = $type instanceof ReflectionUnionType ? $type->getTypes() : ($type === null ? [] : [$type]);
        $first = $types[0] ?? null;
        $typeName = $first instanceof ReflectionNamedType ? $first->getName() : 'mixed';

        if ($first instanceof ReflectionNamedType && !$first->isBuiltin()) {
            if (enum_exists($typeName)) {
                $case = new ReflectionEnum($typeName)->getCases()[0] ?? null;

                return [$this->short($typeName) . '::' . ($case?->getName() ?? 'Value'), $typeName];
            }

            if ($typeName === 'DateTimeInterface' || $typeName === 'DateTimeImmutable') {
                return ["new DateTimeImmutable('-7 days')", 'DateTimeImmutable'];
            }

            // An interface is passed as one of its implementations.
            $typeName = $this->implementations[$typeName] ?? $typeName;

            return ['new ' . $this->short($typeName) . '(/* ... */)', $typeName];
        }

        return [match ($typeName) {
            'int' => '123',
            'float' => '1.5',
            'bool' => 'true',
            'array' => '[/* ... */]',
            default => match (true) {
                str_contains($name, 'url') => "'https://example.com/'",
                str_contains($name, 'email') => "'ops@example.com'",
                str_ends_with($name, 'id') => "'123456789'",
                default => "'example'",
            },
        }, null];
    }

    private function short(string $class): string
    {
        return substr($class, (int) strrpos($class, '\\') + (str_contains($class, '\\') ? 1 : 0));
    }
}
