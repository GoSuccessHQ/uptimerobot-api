# `$uptimeRobot->monitorGroups->update()`

> UptimeRobot API v3 · `PATCH /monitor-groups/{id}`

Update a monitor group

Update (rename) an existing monitor group

Only the name can be changed; MonitorResource::update() moves a monitor to another group with groupId.

## Signature

```php
public function update(int $id, MonitorGroupUpdate $changes): MonitorGroup
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `int` | yes | The monitor group ID |
| `$changes` | `MonitorGroupUpdate` | yes |  |

## Returns

`MonitorGroup`

## Example

```php
use GoSuccess\UptimeRobot\Model\MonitorGroupUpdate;
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->monitorGroups->update(
    id: 123,
    changes: new MonitorGroupUpdate(name: 'Production'),
);
```
