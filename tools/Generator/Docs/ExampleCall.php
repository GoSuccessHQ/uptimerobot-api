<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator\Docs;

/**
 * A call in an example with named arguments, e.g.
 * `new HttpMonitorCreate(friendlyName: 'Example', ...)` or
 * `$uptimeRobot->monitors->create(monitor: ...)`.
 */
final readonly class ExampleCall
{
    /**
     * @param string                                   $callee    Code up to the opening parenthesis.
     * @param list<array{string, string|ExampleCall}> $arguments Parameter name => code of the value.
     */
    public function __construct(
        public string $callee,
        public array $arguments,
    ) {}
}
