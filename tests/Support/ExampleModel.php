<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Support;

use GoSuccess\UptimeRobot\Model\Cast;
use GoSuccess\UptimeRobot\Model\ResponseModel;

final readonly class ExampleModel implements ResponseModel
{
    public function __construct(public ?string $name = null) {}

    public static function fromArray(array $data): static
    {
        return new self(Cast::string($data['name'] ?? null));
    }
}
