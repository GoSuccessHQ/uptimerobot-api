<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator\Writer;

/**
 * Renders PHPDoc blocks from specification texts.
 */
final class Doc
{
    /**
     * Normalize a description from the specification into doc lines.
     *
     * @return list<string>
     */
    public static function lines(?string $text): array
    {
        if ($text === null) {
            return [];
        }

        $text = str_replace(["\r\n", "\r", '*/'], ["\n", "\n", '*\\/'], trim($text));
        $lines = [];
        $blank = false;

        foreach (explode("\n", $text) as $line) {
            $line = rtrim($line);

            if (trim($line) === '') {
                $blank = $lines !== [];

                continue;
            }

            if ($blank) {
                $lines[] = '';
                $blank = false;
            }

            // A leading "@" would be read as a PHPDoc tag.
            $lines[] = preg_replace('/^(\s*)@/', '$1\\@', $line) ?? $line;
        }

        return $lines;
    }

    /**
     * Render a doc block. Groups are separated by a blank line; empty groups are dropped.
     *
     * @param list<list<string>> $groups
     */
    public static function block(array $groups, string $indent = ''): string
    {
        $groups = array_values(array_filter($groups, static fn(array $group): bool => $group !== []));

        if ($groups === []) {
            return '';
        }

        if (\count($groups) === 1 && \count($groups[0]) === 1 && \strlen($groups[0][0]) < 100) {
            return "{$indent}/** {$groups[0][0]} */\n";
        }

        $out = "{$indent}/**\n";

        foreach ($groups as $index => $group) {
            if ($index > 0) {
                $out .= "{$indent} *\n";
            }

            foreach ($group as $line) {
                $out .= $line === '' ? "{$indent} *\n" : "{$indent} * {$line}\n";
            }
        }

        return "{$out}{$indent} */\n";
    }
}
