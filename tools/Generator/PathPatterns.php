<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator;

use RuntimeException;

/**
 * Configuration entries keyed by a location in the specification, with `*`
 * as the only wildcard; it matches any characters, dots included.
 *
 * Locations are written as the generator walks the specification:
 *
 * - `UserDto.activeSubscription.plan`: a property, also of an inline object;
 * - `SimpleTagPaginationDto.data[].id`: `[]` for the items of a list;
 * - `MonitorDto.customHttpHeaders{}`: `{}` for the values of a map;
 * - `TagsController_deleteTag.id`: a path or query parameter of an operation;
 * - `IntegrationsController_create.body`, `….response`: a request or response
 *   body that is not a reference to a component;
 * - `ActivityLogResponseDto.data[]<COMMENT>.date`: `<value>` for the inline
 *   variant of a union with that discriminator value.
 *
 * Every entry must match something, so a typo or an obsolete entry fails
 * instead of silently doing nothing. Entries that match the same location
 * must agree.
 *
 * @template T
 */
final class PathPatterns
{
    /** @var array<string, true> Patterns that matched at least once. */
    private array $used = [];

    /** @var array<string, string> Pattern => regular expression. */
    private array $expressions = [];

    /**
     * @param array<string, T> $entries Pattern => value.
     */
    public function __construct(private readonly string $key, private readonly array $entries)
    {
        foreach (array_keys($entries) as $pattern) {
            $pattern = (string) $pattern;
            $this->expressions[$pattern] = '/^' . str_replace('\\*', '.*', preg_quote($pattern, '/')) . '$/D';
        }
    }

    /**
     * A list of patterns, each with the value true.
     *
     * @param list<string> $patterns
     *
     * @return self<bool>
     */
    public static function of(string $key, array $patterns): self
    {
        $entries = [];

        foreach ($patterns as $pattern) {
            $entries[$pattern] = true;
        }

        return new self($key, $entries);
    }

    /**
     * The value of the entries matching the location, or null if none does.
     *
     * @return T|null
     */
    public function match(string $path): mixed
    {
        $found = null;
        $foundPattern = null;

        foreach ($this->expressions as $pattern => $expression) {
            if (preg_match($expression, $path) !== 1) {
                continue;
            }

            $this->used[$pattern] = true;
            $value = $this->entries[$pattern];

            if ($foundPattern !== null && $found !== $value) {
                throw new RuntimeException("'{$this->key}': {$foundPattern} and {$pattern} both match {$path} but disagree.");
            }

            $found = $value;
            $foundPattern = $pattern;
        }

        return $found;
    }

    public function matches(string $path): bool
    {
        return $this->match($path) !== null;
    }

    /**
     * Patterns that have not matched anything.
     *
     * @return list<string>
     */
    public function unused(): array
    {
        return array_values(array_filter(
            array_map(strval(...), array_keys($this->entries)),
            fn(string $pattern): bool => !isset($this->used[$pattern]),
        ));
    }

    public function key(): string
    {
        return $this->key;
    }
}
