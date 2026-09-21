<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Support;

use BackedEnum;
use DateTimeInterface;
use GoSuccess\UptimeRobot\Model\ResponseModel;
use GoSuccess\UptimeRobot\Resource\AbstractResource;

/**
 * A resource that exposes the protected helpers generated resources use.
 */
final class ExampleResource extends AbstractResource
{
    public function segmentOf(string|int|BackedEnum|DateTimeInterface $value): string
    {
        return $this->segment($value);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function object(mixed $data): array
    {
        return self::expectObject($data);
    }

    /**
     * @template T of ResponseModel
     *
     * @param class-string<T> $model
     *
     * @return T
     */
    public function model(string $model, mixed $data): ResponseModel
    {
        return self::toModel($model, $data);
    }

    /**
     * @template T of ResponseModel
     *
     * @param class-string<T> $model
     *
     * @return list<T>
     */
    public function models(string $model, mixed $data): array
    {
        return self::toModelList($model, $data);
    }
}
