# `$uptimeRobot->monitorGroups->delete()`

> UptimeRobot API v3 · `DELETE /monitor-groups/{id}`

Delete a monitor group

Delete a monitor group. Monitors in the deleted group will be moved to the specified group or to the default group (ID: 0).

The monitors of the group are moved to monitorsNewGroupId, or to no group (groupId 0) without it; monitorsNewGroupId 0 is rejected with a BadRequestException. An unknown ID raises a NotFoundException (both verified live).

## Signature

```php
public function delete(int $id, ?int $monitorsNewGroupId = null): void
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `int` | yes | The monitor group ID to delete |
| `$monitorsNewGroupId` | `int\|null` | no | Optional group ID to move monitors to. If not provided, monitors will be moved to default group (ID: 0). |

## Returns

`void`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$uptimeRobot->monitorGroups->delete(id: 123);
```
