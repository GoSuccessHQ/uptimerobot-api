# `$uptimeRobot->monitors->reset()`

> UptimeRobot API v3 · `POST /monitors/{id}/reset`

Reset stats for a monitor

Resets stats for a monitor. This includes the stats for incidents and alerts.

## Signature

```php
public function reset(int $id): void
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `int` | yes |  |

## Returns

`void`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$uptimeRobot->monitors->reset(id: 123);
```
