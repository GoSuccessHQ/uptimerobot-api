<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator\Docs;

/**
 * A resource whose public methods are documented.
 */
final readonly class DocTarget
{
    /**
     * @param string       $property    Property of the client holding the resource, e.g. "monitors".
     * @param string       $class       Fully qualified class name.
     * @param list<string> $methods     Method names in documentation order.
     */
    public function __construct(
        public string $property,
        public string $class,
        public string $description,
        public array $methods,
    ) {}
}
