# `$uptimeRobot->monitorGroups->delete()`

> UptimeRobot API v3 · `DELETE /monitor-groups/{id}`

Delete a monitor group

Delete a monitor group. Monitors in the deleted group will be moved to the specified group or to the default group (ID: 0).

The monitors of the group move to monitorsNewGroupId, or to no group without it. An unknown ID raises a NotFoundException (verified live).

## Signature

```php
public function delete(int $id, ?int $monitorsNewGroupId = null): void
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `int` | yes | The monitor group ID to delete |
| `$monitorsNewGroupId` | `int\|null` | no | The group the monitors of the deleted group move to, at least 1: 0 is rejected with a BadRequestException (verified live). Without it, they move to no group (groupId 0), which the specification calls the default group. |

## Returns

`void`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$uptimeRobot->monitorGroups->delete(id: 123);
```
