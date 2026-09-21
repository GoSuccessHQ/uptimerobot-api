# `$uptimeRobot->monitors->start()`

> UptimeRobot API v3 · `POST /monitors/{id}/start`

Start a monitor

Starts a paused monitor by ID. The monitor will resume being checked. This operation is idempotent - starting an already active monitor will return successfully.

Returns the monitor, with the status STARTED until its next check (verified live).

## Signature

```php
public function start(int $id): Monitor
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `int` | yes | The monitor ID. |

## Returns

`Monitor`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->monitors->start(id: 123);
```
