<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Support;

use GoSuccess\UptimeRobot\Model\RequestModel;
use GoSuccess\UptimeRobot\Model\Undefined;

/**
 * A request model shaped like the generated ones: every field defaults to
 * {@see Undefined::Value} and is only sent when provided.
 */
final readonly class ExampleRequestModel implements RequestModel
{
    public function __construct(
        public string|Undefined|null $friendlyName = Undefined::Value,
        public int|Undefined|null $interval = Undefined::Value,
    ) {}

    public function toArray(): array
    {
        $data = [];

        if (!$this->friendlyName instanceof Undefined) {
            $data['friendlyName'] = $this->friendlyName;
        }

        if (!$this->interval instanceof Undefined) {
            $data['interval'] = $this->interval;
        }

        return $data;
    }
}
