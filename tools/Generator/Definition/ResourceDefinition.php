<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator\Definition;

use GoSuccess\UptimeRobot\Tools\Generator\Config\ResourceConfig;

/**
 * A resource class to generate.
 */
final class ResourceDefinition
{
    /** @var list<MethodDefinition> */
    public array $methods = [];

    /**
     * Hand-written methods, keyed by name (documented, not generated).
     *
     * @var array<string, MethodDefinition>
     */
    public array $handwritten = [];

    /**
     * @param string $class Fully qualified class name.
     */
    public function __construct(
        public readonly ResourceConfig $config,
        public readonly string $class,
    ) {}
}
