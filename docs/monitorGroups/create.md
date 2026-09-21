# `$uptimeRobot->monitorGroups->create()`

> UptimeRobot API v3 · `POST /monitor-groups`

Create a monitor group

Create a new monitor group with optional initial monitors. Monitors can be assigned by providing their IDs or by specifying existing group IDs whose monitors should be moved.

The monitors of monitorIds and of the groups in groupIds are moved into the new group.

## Signature

```php
public function create(MonitorGroupCreate $group): MonitorGroup
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$group` | `MonitorGroupCreate` | yes |  |

## Returns

`MonitorGroup`

## Example

```php
use GoSuccess\UptimeRobot\Model\MonitorGroupCreate;
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->monitorGroups->create(group: new MonitorGroupCreate(/* ... */));
```
