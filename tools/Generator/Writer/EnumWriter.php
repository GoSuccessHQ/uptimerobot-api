<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator\Writer;

use GoSuccess\UptimeRobot\Tools\Generator\Definition\EnumDefinition;

/**
 * Renders backed enums.
 */
final class EnumWriter
{
    public function render(EnumDefinition $enum, string $source): string
    {
        $namespace = substr($enum->class, 0, (int) strrpos($enum->class, '\\'));
        $short = substr($enum->class, (int) strrpos($enum->class, '\\') + 1);
        $file = new CodeFile($namespace, $short);

        $cases = '';

        foreach ($enum->cases as $index => $case) {
            if ($index > 0 && $case->description !== null) {
                $cases .= "\n";
            }

            $cases .= Doc::block([Doc::lines($case->description)], '    ');
            $value = \is_int($case->value) ? (string) $case->value : "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $case->value) . "'";
            $cases .= "    case {$case->name} = {$value};\n";

            if ($case->description !== null && $index < \count($enum->cases) - 1) {
                $cases .= "\n";
            }
        }

        $cases = (string) preg_replace("/\n{3,}/", "\n\n", $cases);
        $doc = Doc::block([Doc::lines($enum->description), ['Schema: ' . implode(', ', $enum->schemas)]]);

        return $file->render("{$doc}enum {$short}: {$enum->backing}\n{\n{$cases}}\n", $source);
    }
}
