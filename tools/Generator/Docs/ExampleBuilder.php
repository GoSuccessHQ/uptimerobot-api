<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator\Docs;

use BackedEnum;
use DateTimeInterface;
use GoSuccess\UptimeRobot\Tools\Generator\Config\ApiConfig;
use ReflectionEnum;
use ReflectionFunctionAbstract;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use RuntimeException;

/**
 * Builds the arguments of an example call from the real signatures.
 *
 * Every required parameter gets a sample value that fits its type: an enum
 * its first case, a date one a week ago, a model a `new` with its own
 * required arguments (recursively), an interface its first implementation
 * (the first variant of a union), and a scalar a value chosen by type and
 * name, e.g. 'https://example.com/' for a URL. Arguments given in the
 * configuration ('example' of a method) are added to these; their values are
 * read by the type of the parameter:
 *
 * - an int or string for an enum is the value of its case, e.g. 'paused';
 * - a string for a date is passed to `new DateTimeImmutable()`;
 * - an array for a model (or an interface) holds its constructor arguments,
 *   which are added to its required ones;
 * - anything else is written as a PHP literal.
 *
 * A given argument that names no parameter fails, as does a value that fits
 * no type of its parameter, so an example cannot drift from the code.
 */
final class ExampleBuilder
{
    /** Line length above which a call puts each argument on a line of its own. */
    private const int WIDTH = 80;

    /** Nested models deeper than this are a configuration mistake. */
    private const int MAX_DEPTH = 5;

    private const string UNDEFINED = ApiConfig::RUNTIME . '\\Model\\Undefined';

    /** @var array<string, string> Short name => fully qualified class, of the classes used. */
    private array $imports = [];

    /**
     * @param array<string, string> $implementations Interface => the class to pass for it.
     */
    public function __construct(private readonly array $implementations = []) {}

    /**
     * The arguments of a call: the required parameters with sample values,
     * followed in signature order by the given ones.
     *
     * @param array<string, mixed> $given  Values by parameter name.
     * @param bool                 $strict Whether every given value must name a parameter; the
     *                                     paginator of a list takes the list's values without
     *                                     its cursor.
     * @param string               $context For error messages, e.g. "monitors->create()".
     *
     * @return list<array{string, string|ExampleCall}>
     */
    public function arguments(ReflectionFunctionAbstract $function, array $given, bool $strict, string $context, int $depth = 0): array
    {
        $parameters = [];

        foreach ($function->getParameters() as $parameter) {
            $parameters[$parameter->getName()] = $parameter;
        }

        foreach (array_keys($given) as $name) {
            if ($strict && !isset($parameters[$name])) {
                throw new RuntimeException("{$context}: the example passes \${$name}, which {$function->getName()}() does not take.");
            }
        }

        $arguments = [];

        foreach ($parameters as $name => $parameter) {
            if (\array_key_exists($name, $given)) {
                $arguments[] = [$name, $this->given($parameter->getType(), $given[$name], "{$context} \${$name}", $depth)];
            } elseif (!$parameter->isOptional()) {
                $arguments[] = [$name, $this->sample($parameter->getType(), $name, "{$context} \${$name}", $depth)];
            }
        }

        return $arguments;
    }

    /**
     * The code of a statement around a call, e.g. `$result = ` and `;`,
     * wrapped where a line would get too long.
     */
    public function statement(string $prefix, ExampleCall $call, string $suffix): string
    {
        return $prefix . $this->render($call, '', \strlen($prefix) + \strlen($suffix)) . $suffix;
    }

    /**
     * The classes the examples built so far use, to import.
     *
     * @return list<string>
     */
    public function imports(): array
    {
        return array_values($this->imports);
    }

    private function sample(?ReflectionType $type, string $name, string $context, int $depth): string|ExampleCall
    {
        $first = $this->candidates($type)[0] ?? null;

        if ($first === null) {
            return 'null';
        }

        if ($first->isBuiltin()) {
            return self::scalar($first->getName(), $name);
        }

        return $this->object($first->getName(), [], $context, $depth);
    }

    /**
     * A sample by type and name: what the parameter is for, as far as the name tells.
     */
    private static function scalar(string $type, string $name): string
    {
        $name = strtolower($name);

        return match ($type) {
            'int' => match ($name) {
                'interval' => '300',
                'timeout' => '30',
                'port' => '443',
                'duration' => '60',
                default => '123',
            },
            'float' => '1.5',
            'bool' => 'true',
            'array' => '[]',
            'string' => match (true) {
                str_contains($name, 'url') => "'https://example.com/'",
                str_contains($name, 'email') => "'ops@example.com'",
                $name === 'time' => "'14:30:00'",
                $name === 'date' => "'2026-10-01'",
                str_ends_with($name, 'id') => "'123456789'",
                str_contains($name, 'name') => "'Example'",
                default => "'example'",
            },
            default => 'null',
        };
    }

    /**
     * A value of a class: an enum case, a date or a model.
     *
     * @param array<array-key, mixed> $given Constructor arguments of a model.
     */
    private function object(string $class, array $given, string $context, int $depth): string|ExampleCall
    {
        if (enum_exists($class)) {
            $case = new ReflectionEnum($class)->getCases()[0] ?? throw new RuntimeException("{$context}: {$class} has no cases.");

            return $this->import($class) . "::{$case->getName()}";
        }

        if (is_a($class, DateTimeInterface::class, true)) {
            return "new DateTimeImmutable('-7 days')";
        }

        // An interface is passed as one of its implementations.
        $class = $this->implementations[$class] ?? $class;

        if (!class_exists($class)) {
            throw new RuntimeException("{$context}: no class to pass for {$class}.");
        }

        if ($depth >= self::MAX_DEPTH) {
            throw new RuntimeException("{$context}: the models nest too deeply for an example.");
        }

        $short = $this->import($class);
        $constructor = method_exists($class, '__construct') ? new ReflectionMethod($class, '__construct') : null;

        if ($constructor === null) {
            if ($given !== []) {
                throw new RuntimeException("{$context}: {$short} takes no arguments.");
            }

            return new ExampleCall("new {$short}", []);
        }

        $named = [];

        foreach ($given as $key => $value) {
            if (!\is_string($key)) {
                throw new RuntimeException("{$context}: name the arguments of {$short}.");
            }

            $named[$key] = $value;
        }

        return new ExampleCall("new {$short}", $this->arguments($constructor, $named, true, "{$context} {$short}", $depth + 1));
    }

    /**
     * A configured value, read by the type of its parameter.
     */
    private function given(?ReflectionType $type, mixed $value, string $context, int $depth): string|ExampleCall
    {
        foreach ($this->candidates($type) as $candidate) {
            $class = $candidate->getName();

            if ($candidate->isBuiltin()) {
                continue;
            }

            if (enum_exists($class) && (\is_int($value) || \is_string($value))) {
                if (!is_a($class, BackedEnum::class, true)) {
                    throw new RuntimeException("{$context}: {$class} has no values.");
                }

                $case = $class::tryFrom($value) ?? throw new RuntimeException("{$context}: {$class} has no case with the value " . var_export($value, true) . '.');

                return $this->import($class) . "::{$case->name}";
            }

            if (is_a($class, DateTimeInterface::class, true) && \is_string($value)) {
                return 'new DateTimeImmutable(' . self::literal($value) . ')';
            }

            if (\is_array($value) && !enum_exists($class) && !is_a($class, DateTimeInterface::class, true)) {
                return $this->object($class, $value, $context, $depth);
            }
        }

        if ($value === null && ($type === null || $type->allowsNull())) {
            return 'null';
        }

        foreach ($this->candidates($type) as $candidate) {
            if ($candidate->isBuiltin() && self::fits($candidate->getName(), $value)) {
                return self::literal($value);
            }
        }

        throw new RuntimeException("{$context}: " . get_debug_type($value) . ' does not fit the type ' . ($type ?? 'mixed') . '.');
    }

    private static function fits(string $type, mixed $value): bool
    {
        return match ($type) {
            'mixed' => true,
            'int' => \is_int($value),
            'float' => \is_float($value) || \is_int($value),
            'string' => \is_string($value),
            'bool' => \is_bool($value),
            'true' => $value === true,
            'false' => $value === false,
            'array', 'iterable' => \is_array($value),
            default => false,
        };
    }

    /**
     * The types a value may have, without null and the Undefined of optional
     * request fields.
     *
     * @return list<ReflectionNamedType>
     */
    private function candidates(?ReflectionType $type): array
    {
        $types = match (true) {
            $type instanceof ReflectionUnionType, $type instanceof ReflectionIntersectionType => $type->getTypes(),
            $type instanceof ReflectionNamedType => [$type],
            default => [],
        };

        return array_values(array_filter(
            $types,
            static fn(ReflectionType $inner): bool => $inner instanceof ReflectionNamedType && !\in_array($inner->getName(), ['null', self::UNDEFINED], true),
        ));
    }

    private function render(string|ExampleCall $node, string $indent, int $used): string
    {
        if (\is_string($node)) {
            return $node;
        }

        $inline = $this->inline($node);

        if ($node->arguments === [] || \strlen($indent) + $used + \strlen($inline) <= self::WIDTH) {
            return $inline;
        }

        $lines = '';

        foreach ($node->arguments as [$name, $value]) {
            $prefix = "{$indent}    {$name}: ";
            $lines .= $prefix . $this->render($value, "{$indent}    ", \strlen($prefix) - \strlen("{$indent}    ") + 1) . ",\n";
        }

        return "{$node->callee}(\n{$lines}{$indent})";
    }

    private function inline(string|ExampleCall $node): string
    {
        if (\is_string($node)) {
            return $node;
        }

        $arguments = array_map(fn(array $argument): string => "{$argument[0]}: {$this->inline($argument[1])}", $node->arguments);

        return "{$node->callee}(" . implode(', ', $arguments) . ')';
    }

    private static function literal(mixed $value): string
    {
        if (\is_array($value)) {
            $items = [];

            foreach ($value as $key => $item) {
                $items[] = array_is_list($value) ? self::literal($item) : self::literal($key) . ' => ' . self::literal($item);
            }

            return '[' . implode(', ', $items) . ']';
        }

        return match (true) {
            $value === null => 'null',
            \is_bool($value) => $value ? 'true' : 'false',
            \is_string($value) => "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'",
            \is_int($value) => (string) $value,
            \is_float($value) => var_export($value, true),
            default => throw new RuntimeException('Unsupported example value of type ' . get_debug_type($value) . '.'),
        };
    }

    /**
     * Import a class and return its short name.
     */
    private function import(string $class): string
    {
        $short = substr($class, (int) strrpos($class, '\\') + (str_contains($class, '\\') ? 1 : 0));
        $existing = $this->imports[$short] ?? null;

        if ($existing !== null && $existing !== $class) {
            throw new RuntimeException("An example uses both {$existing} and {$class}, which share the name {$short}.");
        }

        $this->imports[$short] = $class;

        return $short;
    }
}
