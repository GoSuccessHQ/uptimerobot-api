<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Support;

use GoSuccess\UptimeRobot\Model\ResponseModel;

/**
 * A fallback model for union values the reader does not know.
 */
final readonly class ExampleUnknownModel implements ResponseModel
{
    /**
     * @param array<array-key, mixed> $data
     */
    public function __construct(public array $data = []) {}

    public static function fromArray(array $data): static
    {
        return new self($data);
    }
}
