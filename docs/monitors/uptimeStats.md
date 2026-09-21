# `$uptimeRobot->monitors->uptimeStats()`

> UptimeRobot API v3 · `GET /monitors/uptime-stats`

Get the uptime statistics of all monitors.

Aggregated over every monitor of the account, with the downtimes of the time frame, newest first. The overall uptime is a fraction from 0 to 1, unlike the percentage of uptime(). For a time frame of your own, pass UptimeTimeFrame::Custom with $start and $end, which are sent as Unix seconds; the API ignores them for the other time frames (verified live), so passing them there is rejected.

## Signature

```php
public function uptimeStats(
    UptimeTimeFrame $timeFrame,
    ?DateTimeInterface $start = null,
    ?DateTimeInterface $end = null,
    ?int $logLimit = null,
): UptimeStats
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$timeFrame` | `UptimeTimeFrame` | yes | The period; the API matches it case-sensitively. |
| `$start` | `DateTimeInterface\|null` | no | Start of a Custom time frame. |
| `$end` | `DateTimeInterface\|null` | no | End of a Custom time frame, after $start (the API rejects an equal one, verified live). |
| `$logLimit` | `int\|null` | no | The maximum number of downtimes in logs (1-500; the API's default is 50). |

## Returns

`UptimeStats`

## Example

```php
use GoSuccess\UptimeRobot\Enum\UptimeTimeFrame;
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->monitors->uptimeStats(timeFrame: UptimeTimeFrame::Day);
```
