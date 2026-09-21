# `$uptimeRobot->monitorGroups->get()`

> UptimeRobot API v3 · `GET /monitor-groups/{id}`

Get a monitor group by ID

Get monitor group details by ID

An unknown ID raises a NotFoundException, and so does 0, the groupId of the monitors in no group (verified live).

## Signature

```php
public function get(int $id): MonitorGroup
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `int` | yes | The monitor group ID |

## Returns

`MonitorGroup`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->monitorGroups->get(id: 123);
```
