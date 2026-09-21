<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Resource;

use BackedEnum;
use DateTimeInterface;
use GoSuccess\UptimeRobot\Exception\SerializationException;
use GoSuccess\UptimeRobot\Http\Connection;
use GoSuccess\UptimeRobot\Http\Query;
use GoSuccess\UptimeRobot\Model\ResponseModel;

/**
 * Base class of all API resources.
 */
abstract class AbstractResource
{
    public function __construct(protected readonly Connection $connection) {}

    /**
     * Encode a value for use as one path segment; dates become ISO 8601 in UTC.
     */
    protected function segment(string|int|BackedEnum|DateTimeInterface $value): string
    {
        return rawurlencode(Query::format('path', $value));
    }

    /**
     * Require a decoded response to be a JSON object (or array).
     *
     * @return array<array-key, mixed>
     */
    protected static function expectObject(mixed $data): array
    {
        if (!\is_array($data)) {
            $type = get_debug_type($data);

            throw new SerializationException("Expected a JSON object, got {$type}.");
        }

        return $data;
    }

    /**
     * Map a decoded response onto a model, rejecting anything but a JSON object.
     *
     * @template T of ResponseModel
     *
     * @param class-string<T> $model
     *
     * @return T
     */
    protected static function toModel(string $model, mixed $data): ResponseModel
    {
        if (!\is_array($data)) {
            $type = get_debug_type($data);

            throw new SerializationException("Expected a JSON object for {$model}, got {$type}.");
        }

        return $model::fromArray($data);
    }

    /**
     * Map a decoded JSON array of objects onto a list of models.
     *
     * @template T of ResponseModel
     *
     * @param class-string<T> $model
     *
     * @return list<T>
     */
    protected static function toModelList(string $model, mixed $data): array
    {
        if (!\is_array($data) || !array_is_list($data)) {
            $type = get_debug_type($data);

            throw new SerializationException("Expected a JSON array of {$model}, got {$type}.");
        }

        $list = [];

        foreach ($data as $item) {
            $list[] = self::toModel($model, $item);
        }

        return $list;
    }
}
