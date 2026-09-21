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
| `$from` | `DateTimeInterface\|null` | no | The start of the period, sent as ISO 8601 in UTC. Without from and to, the last 24 hours are reported, and from alone is accepted (both verified live). |
| `$to` | `DateTimeInterface\|null` | no | The end of the period, sent as ISO 8601 in UTC. Pass it only together with from: to alone is rejected with a BadRequestException, "Maximum range is 90 days" (verified live). |

## Returns

`MonitorUptimeStats`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->monitors->uptime(id: 123);
```
