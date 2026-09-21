<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator\Writer;

use GoSuccess\UptimeRobot\Tools\Generator\Definition\EnumCase;
use GoSuccess\UptimeRobot\Tools\Generator\Definition\EnumDefinition;

/**
 * Renders backed enums, with the description of each case and the
 * deprecation of the values the API phases out.
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
            // A case with a docblock or an attribute stands apart.
            $documented = $case->description !== null || $case->deprecated;

            if ($index > 0 && $documented) {
                $cases .= "\n";
            }

            $cases .= Doc::block([Doc::lines($case->description), $case->deprecated ? ['@deprecated'] : []], '    ');
            $cases .= $this->deprecation($case, $file);
            $value = \is_int($case->value) ? (string) $case->value : "'" . $this->escape($case->value) . "'";
            $cases .= "    case {$case->name} = {$value};\n";

            if ($documented && $index < \count($enum->cases) - 1) {
                $cases .= "\n";
            }
        }

        $cases = (string) preg_replace("/\n{3,}/", "\n\n", $cases);
        $doc = Doc::block([Doc::lines($enum->description), ['Schema: ' . implode(', ', $enum->schemas)]]);

        return $file->render("{$doc}enum {$short}: {$enum->backing}\n{\n{$cases}}\n", $source);
    }

    /**
     * PHP reports the use of a deprecated case at runtime, with its
     * description as the message; reading the value (tryFrom) stays silent.
     */
    private function deprecation(EnumCase $case, CodeFile $file): string
    {
        if (!$case->deprecated) {
            return '';
        }

        $attribute = $file->alias('Deprecated');
        $message = trim((string) preg_replace('/\s+/', ' ', $case->description ?? ''));

        return $message === '' ? "    #[{$attribute}]\n" : "    #[{$attribute}('{$this->escape($message)}')]\n";
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }
}
