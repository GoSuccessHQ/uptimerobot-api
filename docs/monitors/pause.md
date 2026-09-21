# `$uptimeRobot->monitors->pause()`

> UptimeRobot API v3 · `POST /monitors/{id}/pause`

Pause a monitor

Pauses a single monitor by ID. The monitor will stop being checked until it is resumed. This operation is idempotent - pausing an already paused monitor will return successfully.

Returns the monitor with the status PAUSED.

## Signature

```php
public function pause(int $id): Monitor
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `int` | yes |  |

## Returns

`Monitor`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->monitors->pause(id: 123);
```
