<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator;

use GoSuccess\UptimeRobot\Tools\Generator\Config\ApiConfig;
use GoSuccess\UptimeRobot\Tools\Generator\Definition\ResourceDefinition;

/**
 * The definitions of one API, as the generator sees them.
 */
final readonly class Analysis
{
    /**
     * @param list<ResourceDefinition> $resources
     * @param list<string>             $notes
     */
    public function __construct(
        public ApiConfig $config,
        public Registry $registry,
        public array $resources,
        public array $notes,
    ) {}
}
