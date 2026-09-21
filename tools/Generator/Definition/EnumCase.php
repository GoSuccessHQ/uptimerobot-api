<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator\Definition;

final readonly class EnumCase
{
    public function __construct(
        public string $name,
        public int|string $value,
        public ?string $description = null,
    ) {}
}
