# `$uptimeRobot->monitors->responseTimeStats()`

> UptimeRobot API v3 · `GET /monitors/{id}/stats/response-time`

Get monitor response time statistics

Returns response time statistics for a specific monitor within a configurable date range. Defaults to the last 24 hours. Maximum range is 90 days. Optionally includes time series data. Optionally filter by region.

Times are in milliseconds. Pass from and to together or neither: from alone is rejected with "to must be a Date instance", to alone with "Maximum range is 90 days". timeSeries is empty unless includeTimeSeries is true; its points summarize longer intervals for longer ranges (all verified live).

## Signature

```php
public function responseTimeStats(
    int $id,
    ?DateTimeInterface $from = null,
    ?DateTimeInterface $to = null,
    ?bool $includeTimeSeries = null,
    ?ResponseTimeRegion $region = null,
): ResponseTimeStats
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `int` | yes | The monitor ID |
| `$from` | `DateTimeInterface\|null` | no | Start date for statistics (ISO 8601 format). Defaults to 24 hours ago. |
| `$to` | `DateTimeInterface\|null` | no | End date for statistics (ISO 8601 format). Defaults to now. |
| `$includeTimeSeries` | `bool\|null` | no | Whether to include time series data points in the response. Defaults to false. |
| `$region` | `ResponseTimeRegion\|null` | no | Filter by region code (na, eu, as, oc). When provided, only returns data for the specified region. |

## Returns

`ResponseTimeStats`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->monitors->responseTimeStats(id: 123);
```
