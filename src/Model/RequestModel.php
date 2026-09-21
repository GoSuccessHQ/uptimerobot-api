<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Model;

/**
 * A model that is sent as a request payload.
 */
interface RequestModel
{
    /**
     * Convert the model into its JSON payload.
     *
     * Only the fields that were actually provided are included, so the payload
     * is suitable for partial updates. A field explicitly set to `null` is sent
     * as JSON `null`; see {@see Undefined}.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
