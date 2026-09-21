<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator\Definition;

/**
 * A backed enum to generate.
 */
final readonly class EnumDefinition
{
    /**
     * @param string          $class       Fully qualified class name.
     * @param 'int'|'string'  $backing
     * @param list<EnumCase>  $cases
     * @param list<string>    $schemas     Schemas and locations this enum was generated from.
     */
    public function __construct(
        public string $class,
        public string $backing,
        public array $cases,
        public ?string $description,
        public array $schemas,
    ) {}

    /**
     * A comparable fingerprint of the values and names.
     */
    public function signature(): string
    {
        return $this->backing . ':' . implode(',', array_map(static fn(EnumCase $case): string => "{$case->name}={$case->value}", $this->cases));
    }
}
