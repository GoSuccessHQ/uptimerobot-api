# `$uptimeRobot->monitors->dependencyStatus()`

> UptimeRobot API v3 · `GET /monitors/{id}/dependency/status`

Get the current status of a dependency monitor

Returns the fine-grained 4-state (operational, minor, major, maintenance) for a type-10 dependency monitor, read from monitorConfig.dependency.currentState. Distinct from the coarse monitor status field, which cannot tell a partial degradation from a full outage.

Only for DEPENDENCY monitors: the state of the tracked provider, which tells a partial degradation (minor) from a full outage (major), unlike the status of the monitor. For a monitor of another type, the API raises a NotFoundException (000-004) (verified live).

## Signature

```php
public function dependencyStatus(int $id): DependencyStatus
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `int` | yes |  |

## Returns

`DependencyStatus`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->monitors->dependencyStatus(id: 123);
```
