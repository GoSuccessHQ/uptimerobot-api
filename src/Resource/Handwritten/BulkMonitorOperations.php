<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Resource\Handwritten;

use GoSuccess\UptimeRobot\Http\Method;
use GoSuccess\UptimeRobot\Model\BulkMonitorUpdate;
use GoSuccess\UptimeRobot\Model\BulkOperationResult;
use GoSuccess\UptimeRobot\Resource\BulkMonitorResource;
use InvalidArgumentException;

/**
 * Hand-written methods of {@see BulkMonitorResource}.
 *
 * Each operation selects the monitors by monitor group, by tag or by both
 * (then only the monitors in the group with the tag). The API requires at
 * least one of the two, which its specification states in prose only, so a
 * call without either is rejected here before anything is sent.
 */
trait BulkMonitorOperations
{
    /**
     * Pause the monitors of a group and/or with a tag.
     *
     * Select the monitors with $groupId, $tagId or both; without either, an
     * InvalidArgumentException is thrown before anything is sent. The result
     * lists every selected monitor; one that could not be paused is reported
     * there with its error, not as an exception.
     *
     * `POST /monitors/bulk/pause`
     *
     * @param int|null $groupId The monitor group; 0 selects the monitors in no group.
     * @param int|null $tagId   The tag.
     *
     * @throws InvalidArgumentException If neither $groupId nor $tagId is given.
     */
    public function pause(?int $groupId = null, ?int $tagId = null): BulkOperationResult
    {
        $data = $this->connection->json(Method::Post, 'monitors/bulk/pause', body: self::selection($groupId, $tagId));

        return self::toModel(BulkOperationResult::class, $data);
    }

    /**
     * Start the paused monitors of a group and/or with a tag.
     *
     * Select the monitors with $groupId, $tagId or both; without either, an
     * InvalidArgumentException is thrown before anything is sent. The result
     * lists every selected monitor; one that could not be started is reported
     * there with its error, not as an exception.
     *
     * `POST /monitors/bulk/start`
     *
     * @param int|null $groupId The monitor group; 0 selects the monitors in no group.
     * @param int|null $tagId   The tag.
     *
     * @throws InvalidArgumentException If neither $groupId nor $tagId is given.
     */
    public function start(?int $groupId = null, ?int $tagId = null): BulkOperationResult
    {
        $data = $this->connection->json(Method::Post, 'monitors/bulk/start', body: self::selection($groupId, $tagId));

        return self::toModel(BulkOperationResult::class, $data);
    }

    /**
     * Change settings of the monitors of a group and/or with a tag.
     *
     * Select the monitors with $groupId, $tagId or both; without either, or
     * without a setting to change, an InvalidArgumentException is thrown
     * before anything is sent. Only the settings set in $changes are sent. The
     * result lists every selected monitor; one that could not be changed is
     * reported there with its error, not as an exception.
     *
     * `POST /monitors/bulk/update`
     *
     * @param BulkMonitorUpdate $changes The settings to change; at least one.
     * @param int|null          $groupId The monitor group; 0 selects the monitors in no group.
     * @param int|null          $tagId   The tag.
     *
     * @throws InvalidArgumentException If neither $groupId nor $tagId is given, or
     *                                  $changes changes nothing.
     */
    public function update(BulkMonitorUpdate $changes, ?int $groupId = null, ?int $tagId = null): BulkOperationResult
    {
        $settings = $changes->toArray();

        if ($settings === []) {
            throw new InvalidArgumentException('$changes changes nothing; set at least one setting.');
        }

        $data = $this->connection->json(Method::Post, 'monitors/bulk/update', body: [...self::selection($groupId, $tagId), ...$settings]);

        return self::toModel(BulkOperationResult::class, $data);
    }

    /**
     * The body that selects the monitors.
     *
     * @return array<string, int>
     *
     * @throws InvalidArgumentException If neither $groupId nor $tagId is given.
     */
    private static function selection(?int $groupId, ?int $tagId): array
    {
        if ($groupId === null && $tagId === null) {
            throw new InvalidArgumentException('Select the monitors with $groupId, $tagId or both; the API requires at least one.');
        }

        return array_filter(['groupId' => $groupId, 'tagId' => $tagId], static fn(?int $id): bool => $id !== null);
    }
}
