<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator\Writer;

use GoSuccess\UptimeRobot\Tools\Generator\Definition\ModelDefinition;
use GoSuccess\UptimeRobot\Tools\Generator\Definition\UnionDefinition;
use GoSuccess\UptimeRobot\Tools\Generator\Naming;
use GoSuccess\UptimeRobot\Tools\Generator\Registry;
use LogicException;
use RuntimeException;

/**
 * Renders the interface of a union and, for unions read from responses, the
 * fallback model for discriminator values this client does not know yet.
 *
 * The interface of a union read from responses declares the properties that
 * all its variants share (see {@see Registry::sharedProperties()}) as
 * read-only properties, which the variants and the fallback have as well.
 */
final class UnionWriter
{
    public function __construct(
        private readonly Registry $registry,
        private readonly ModelWriter $models,
    ) {}

    public function renderInterface(UnionDefinition $union, string $source): string
    {
        [$namespace, $short] = self::split($union->interface);
        $file = new CodeFile($namespace, $short);

        $parents = [];

        if ($union->request) {
            $parents[] = $file->alias(Expressions::REQUEST_MODEL);
        }

        if ($union->response) {
            $parents[] = $file->alias(Expressions::RESPONSE_MODEL);
        }

        $variants = array_map(static fn(string $class): string => '{@see ' . self::split($class)[1] . '}', array_values($union->variants));
        $told = "Implemented by one model per \"{$union->discriminator}\": " . implode(', ', $variants) . '.';

        if ($union->fallback !== null) {
            $told .= ' A value this client does not know yet is read as {@see ' . self::split($union->fallback)[1] . '}.';
        }

        $properties = [];
        $first = $this->firstVariant($union);

        foreach ($this->registry->sharedProperties($union) as $property) {
            $read = $this->models->readProperty($property, $first, $file);
            $properties[] = $this->models->propertyDoc($property, $read, '    ') . "    public {$read['native']} \${$property->phpName} { get; }\n";
        }

        if ($properties !== []) {
            $told .= ' Each of them has the properties declared here; the others need an instanceof check.';
        }

        $doc = Doc::block([Doc::lines($union->description), Doc::lines(wordwrap($told, 100)), ["Schema: {$union->source}"]]);
        $extends = $parents === [] ? '' : ' extends ' . implode(', ', $parents);
        $body = $properties === [] ? " {}\n" : "\n{\n" . implode("\n", $properties) . "}\n";

        return $file->render("{$doc}interface {$short}{$extends}{$body}", $source);
    }

    public function renderFallback(UnionDefinition $union, string $source): string
    {
        $fallback = $union->fallback ?? throw new LogicException("{$union->source} has no fallback.");
        [$namespace, $short] = self::split($fallback);
        $file = new CodeFile($namespace, $short);
        $interface = $file->alias($union->interface);
        $property = Naming::camel($union->discriminator);
        $key = str_replace(['\\', "'"], ['\\\\', "\\'"], $union->discriminator);
        $cast = $file->alias(Expressions::CAST);
        $first = $this->firstVariant($union);

        $parameters = "        public ?{$union->backing} \${$property} = null,\n        public array \$data = [],\n";
        $arguments = "            {$property}: {$cast}::{$union->backing}(\$data['{$key}'] ?? null),\n            data: \$data,\n";
        $shared = $this->registry->sharedProperties($union);

        foreach ($shared as $sharedProperty) {
            if (\in_array($sharedProperty->phpName, [$property, 'data'], true)) {
                throw new RuntimeException("unions.{$union->source}: the variants share the property \${$sharedProperty->phpName}, which {$short} uses for the payload; rename it in 'properties'.");
            }

            $read = $this->models->readProperty($sharedProperty, $first, $file);
            $parameters .= $this->models->propertyDoc($sharedProperty, $read, '        ');
            $parameters .= "        public {$read['native']} \${$sharedProperty->phpName} = {$read['default']},\n";
            $arguments .= "            {$sharedProperty->phpName}: {$read['expression']},\n";
        }

        $kept = $shared === []
            ? 'It keeps the payload as the API sent it, so nothing is lost until the client learns the new kind.'
            : 'It keeps the payload as the API sent it, so nothing is lost until the client learns the new kind, and reads the properties that every kind has.';

        $doc = Doc::block([
            ["A {@see {$interface}} of a kind this client does not know yet."],
            Doc::lines(wordwrap($kept, 100)),
            ["Schema: {$union->source}"],
        ]);

        return $file->render(
            "{$doc}final readonly class {$short} implements {$interface}\n{\n"
            . "    /**\n"
            . "     * @param {$union->backing}|null \${$property} The \"{$union->discriminator}\" the API sent, if any.\n"
            . "     * @param array<array-key, mixed> \$data The payload as the API sent it.\n"
            . "     */\n"
            . "    public function __construct(\n{$parameters}    ) {}\n\n"
            . "    public static function fromArray(array \$data): static\n    {\n"
            . "        return new self(\n{$arguments}        );\n"
            . "    }\n}\n",
            $source,
        );
    }

    private function firstVariant(UnionDefinition $union): ModelDefinition
    {
        $class = array_values($union->variants)[0] ?? throw new LogicException("{$union->source} has no variants.");

        return $this->registry->models[$class];
    }

    /**
     * @return array{string, string} Namespace and short name.
     */
    private static function split(string $class): array
    {
        $position = (int) strrpos($class, '\\');

        return [substr($class, 0, $position), substr($class, $position + 1)];
    }
}
