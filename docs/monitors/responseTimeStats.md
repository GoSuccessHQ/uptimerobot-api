# `$uptimeRobot->monitors->responseTimeStats()`

> UptimeRobot API v3 · `GET /monitors/{id}/stats/response-time`

Get monitor response time statistics

Returns response time statistics for a specific monitor within a configurable date range. Defaults to the last 24 hours. Maximum range is 90 days. Optionally includes time series data. Optionally filter by region.

Times are in milliseconds. Pass from and to together or neither: from alone is rejected with "to must be a Date instance", to alone with "Maximum range is 90 days". timeSeries is empty unless includeTimeSeries is true; the spacing of its points is not documented and varied: 1, 5 and 30 minutes were seen (all verified live).

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
| `$from` | `DateTimeInterface\|null` | no | The start of the period, sent as ISO 8601 in UTC. Pass from and to together, or neither for the last 24 hours: from alone is rejected with a BadRequestException, "to must be a Date instance" (verified live). |
| `$to` | `DateTimeInterface\|null` | no | The end of the period, sent as ISO 8601 in UTC. Pass from and to together, or neither for the last 24 hours: to alone is rejected with a BadRequestException, "Maximum range is 90 days" (verified live). |
| `$includeTimeSeries` | `bool\|null` | no | Whether to include time series data points in the response. Defaults to false. |
| `$region` | `ResponseTimeRegion\|null` | no | Only the data of this region, or of all regions with All (verified live: the API takes na, eu, as, oc and all). |

## Returns

`ResponseTimeStats`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->monitors->responseTimeStats(id: 123);
```
