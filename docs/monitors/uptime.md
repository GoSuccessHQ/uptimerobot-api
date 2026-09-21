# `$uptimeRobot->monitors->uptime()`

> UptimeRobot API v3 · `GET /monitors/{id}/stats/uptime`

Get monitor uptime statistics

Returns uptime statistics for a specific monitor within a configurable date range. Defaults to the last 24 hours. Maximum range is 90 days.

The uptime is a percentage from 0 to 100, unlike the fraction that uptimeStats() reports. to without from is rejected with "Maximum range is 90 days" (both verified live).

## Signature

```php
public function uptime(
    int $id,
    ?DateTimeInterface $from = null,
    ?DateTimeInterface $to = null,
): MonitorUptimeStats
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `int` | yes | The monitor ID |
| `$from` | `DateTimeInterface\|null` | no | Start date for statistics (ISO 8601 format). Defaults to 24 hours ago. |
| `$to` | `DateTimeInterface\|null` | no | End date for statistics (ISO 8601 format). Defaults to now. |

## Returns

`MonitorUptimeStats`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->monitors->uptime(id: 123);
```
