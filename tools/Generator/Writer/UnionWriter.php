<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator\Writer;

use GoSuccess\UptimeRobot\Tools\Generator\Definition\UnionDefinition;
use GoSuccess\UptimeRobot\Tools\Generator\Naming;
use LogicException;

/**
 * Renders the interface of a union and, for unions read from responses, the
 * fallback model for discriminator values this client does not know yet.
 */
final class UnionWriter
{
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

        $doc = Doc::block([Doc::lines($union->description), Doc::lines(wordwrap($told, 100)), ["Schema: {$union->source}"]]);
        $extends = $parents === [] ? '' : ' extends ' . implode(', ', $parents);

        return $file->render("{$doc}interface {$short}{$extends} {}\n", $source);
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

        $doc = Doc::block([
            ["A {@see {$interface}} of a kind this client does not know yet."],
            ['It keeps the payload as the API sent it, so nothing is lost until the client learns the new kind.'],
            ["Schema: {$union->source}"],
        ]);

        return $file->render(
            "{$doc}final readonly class {$short} implements {$interface}\n{\n"
            . "    /**\n"
            . "     * @param {$union->backing}|null \${$property} The \"{$union->discriminator}\" the API sent, if any.\n"
            . "     * @param array<array-key, mixed> \$data The payload as the API sent it.\n"
            . "     */\n"
            . "    public function __construct(\n"
            . "        public ?{$union->backing} \${$property} = null,\n"
            . "        public array \$data = [],\n"
            . "    ) {}\n\n"
            . "    public static function fromArray(array \$data): static\n    {\n"
            . "        return new self({$cast}::{$union->backing}(\$data['{$key}'] ?? null), \$data);\n"
            . "    }\n}\n",
            $source,
        );
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
