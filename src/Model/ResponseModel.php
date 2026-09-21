<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Model;

/**
 * A model that is read from an API response.
 */
interface ResponseModel
{
    /**
     * Build the model from a decoded JSON object.
     *
     * Missing or mistyped fields never cause an error: they fall back to null,
     * an empty list or the type's zero value, so an API that adds or changes a
     * field cannot break the client.
     *
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): static;
}
