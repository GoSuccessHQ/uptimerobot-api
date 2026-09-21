<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator\Definition;

use GoSuccess\UptimeRobot\Tools\Generator\PhpType;

/**
 * One property of a model.
 */
final readonly class PropertyDefinition
{
    /**
     * @param string $jsonName The key in the API payload.
     * @param string $phpName  The PHP property and parameter name.
     * @param bool   $nullable Whether the specification allows null.
     * @param bool   $required Whether the specification lists the property as required.
     */
    public function __construct(
        public string $jsonName,
        public string $phpName,
        public PhpType $type,
        public bool $nullable,
        public bool $required,
        public bool $readOnly,
        public bool $deprecated,
        public ?string $description,
    ) {}
}
