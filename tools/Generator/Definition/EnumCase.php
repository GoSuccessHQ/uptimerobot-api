<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator\Definition;

final readonly class EnumCase
{
    /**
     * @param bool $deprecated Whether the API is phasing the value out; the
     *                         description then tells what replaces it.
     */
    public function __construct(
        public string $name,
        public int|string $value,
        public ?string $description = null,
        public bool $deprecated = false,
    ) {}
}
