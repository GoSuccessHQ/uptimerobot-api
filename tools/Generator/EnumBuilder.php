<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator;

use GoSuccess\UptimeRobot\Tools\Generator\Definition\EnumCase;
use GoSuccess\UptimeRobot\Tools\Generator\Definition\EnumDefinition;
use RuntimeException;

/**
 * Builds enum definitions and resolves their case names.
 *
 * Names are taken, in this order, from:
 *
 * 1. the configuration (`enumCases`), verbatim,
 * 2. "<value> = <Name>" lines in the description,
 * 3. `x-enumNames`, if it has exactly one name per value,
 * 4. the `title` of each `oneOf` variant,
 * 5. the value itself, for string enums.
 *
 * Names from 2. to 5. are converted to PascalCase ("LOOKS_DOWN" → "LooksDown",
 * "not_equals" → "NotEquals"), so that the copies of an enum that carry
 * `x-enumNames` and those that do not agree. An int enum without names fails:
 * its cases must be named in the configuration. When two sources disagree,
 * generation fails instead of guessing.
 */
final class EnumBuilder
{
    /** @var list<string> */
    public array $notes = [];

    /**
     * @param array<int|string, string>|null $configured Value => case name from the configuration.
     * @param string                         $source     Schema name or location, for messages and the docblock.
     */
    public function build(Schema $schema, string $class, ?array $configured, string $source): EnumDefinition
    {
        [$values, $titles, $caseDescriptions] = $this->values($schema);
        $backing = $this->backing($schema, $values, $source);

        $fromDescription = $this->namesFromDescription($schema->description());
        $fromExtension = $this->namesFromExtension($schema, $values);
        $names = [];

        if ($configured !== null) {
            $names = array_map(Naming::enumCase(...), $configured);

            foreach (array_diff(array_map(strval(...), array_keys($configured)), array_map(strval(...), $values)) as $unknown) {
                throw new RuntimeException("{$source}: enumCases names the value {$unknown}, which the enum does not have.");
            }
        } elseif ($fromDescription !== []) {
            $names = $fromDescription;

            foreach ($fromExtension as $value => $name) {
                if (isset($names[$value]) && $names[$value] !== $name) {
                    throw new RuntimeException("{$source}: description names {$value} \"{$names[$value]}\", x-enumNames says \"{$name}\". Configure the names explicitly.");
                }
            }

            foreach (array_diff_key($names, array_flip($values)) as $value => $name) {
                $this->notes[] = "{$source}: {$value} = {$name} is only documented in the description; included.";
            }
        } elseif ($fromExtension !== []) {
            $names = $fromExtension;
        } elseif ($titles !== []) {
            $names = array_map(Naming::enumCaseFromValue(...), $titles);
        } elseif ($backing === 'string') {
            foreach ($values as $value) {
                $names[$value] = Naming::enumCaseFromValue((string) $value);
            }
        }

        $allValues = array_values(array_unique([...$values, ...array_keys($names)], \SORT_REGULAR));
        $cases = [];
        $usedNames = [];

        foreach ($allValues as $value) {
            $case = $names[$value] ?? null;

            if ($case === null) {
                throw new RuntimeException("{$source}: no name for value {$value}. Name the cases in enumCases.");
            }

            if (isset($usedNames[strtolower($case)])) {
                throw new RuntimeException("{$source}: case name {$case} is used twice. Name the cases in enumCases.");
            }

            $usedNames[strtolower($case)] = true;
            $cases[] = new EnumCase($case, $backing === 'int' ? (int) $value : (string) $value, $caseDescriptions[$value] ?? null);
        }

        return new EnumDefinition($class, $backing, $cases, $this->cleanDescription($schema->description()), [$source]);
    }

    /**
     * @return array{list<int|string>, array<int|string, string>, array<int|string, string>}
     */
    private function values(Schema $schema): array
    {
        $values = $schema->enum();

        if ($values !== null) {
            return [$values, [], []];
        }

        $values = [];
        $titles = [];
        $descriptions = [];

        foreach ($schema->variants('oneOf') as $variant) {
            $value = ($variant->enum() ?? [])[0] ?? null;

            if ($value === null) {
                continue;
            }

            $values[] = $value;

            if ($variant->title() !== null) {
                $titles[$value] = $variant->title();
            }

            if ($variant->description() !== null) {
                $descriptions[$value] = $variant->description();
            }
        }

        return [$values, $titles, $descriptions];
    }

    /**
     * @param list<int|string> $values
     *
     * @return 'int'|'string'
     */
    private function backing(Schema $schema, array $values, string $source): string
    {
        $type = $schema->type();
        $allInts = $values !== [] && array_filter($values, is_int(...)) === $values;
        $allStrings = $values !== [] && array_filter($values, is_string(...)) === $values;

        return match (true) {
            // The request DTOs type every number as "number", enums included.
            \in_array($type, ['integer', 'number', null], true) && $allInts => 'int',
            \in_array($type, ['string', null], true) && $allStrings => 'string',
            default => throw new RuntimeException("{$source}: unsupported enum type " . ($type ?? 'none') . ' or mixed values.'),
        };
    }

    /**
     * Parse "0 = Name" lines. Repeated values keep their first name.
     *
     * @return array<int|string, string>
     */
    private function namesFromDescription(?string $description): array
    {
        if ($description === null || preg_match_all('/^\s*(-?\d+)\s*=\s*([A-Za-z_][A-Za-z0-9_]*)\s*$/m', $description, $matches, \PREG_SET_ORDER) === 0) {
            return [];
        }

        $names = [];

        foreach ($matches as $match) {
            $value = (int) $match[1];

            if (isset($names[$value])) {
                $this->notes[] = "alias {$match[2]} for {$value} dropped (keeping {$names[$value]}).";

                continue;
            }

            $names[$value] = Naming::enumCaseFromValue($match[2]);
        }

        return $names;
    }

    /**
     * @param list<int|string> $values
     *
     * @return array<int|string, string>
     */
    private function namesFromExtension(Schema $schema, array $values): array
    {
        $names = $schema->stringList('x-enumNames');

        if ($names === null || \count($names) !== \count($values)) {
            return [];
        }

        return array_combine($values, array_map(Naming::enumCaseFromValue(...), $names));
    }

    /**
     * Drop the "0 = Name" lines, which the enum cases already express.
     */
    private function cleanDescription(?string $description): ?string
    {
        if ($description === null) {
            return null;
        }

        $cleaned = trim((string) preg_replace('/^\s*-?\d+\s*=\s*[A-Za-z_][A-Za-z0-9_]*\s*$/m', '', $description));

        return $cleaned === '' ? null : $cleaned;
    }
}
