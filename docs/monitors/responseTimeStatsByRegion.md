# `$uptimeRobot->monitors->responseTimeStatsByRegion()`

> UptimeRobot API v3 · `GET /monitors/{id}/stats/response-time/all`

Get monitor response time statistics by region

Returns response time statistics for a specific monitor grouped by region within a configurable date range. Defaults to the last 24 hours. Maximum range is 90 days. Optionally includes time series data.

Times are in milliseconds. Pass from and to together or neither, as for responseTimeStats(). The regions the monitor is not checked from are null (verified live).

## Signature

```php
public function responseTimeStatsByRegion(
    int $id,
    ?DateTimeInterface $from = null,
    ?DateTimeInterface $to = null,
    ?bool $includeTimeSeries = null,
): RegionalResponseTimeStats
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `int` | yes | The monitor ID |
| `$from` | `DateTimeInterface\|null` | no | The start of the period, sent as ISO 8601 in UTC. Pass from and to together, or neither for the last 24 hours: from alone is rejected with a BadRequestException, "to must be a Date instance" (verified live). |
| `$to` | `DateTimeInterface\|null` | no | The end of the period, sent as ISO 8601 in UTC. Pass from and to together, or neither for the last 24 hours: to alone is rejected with a BadRequestException, "Maximum range is 90 days" (verified live). |
| `$includeTimeSeries` | `bool\|null` | no | Whether to include time series data points in the response. Defaults to false. |

## Returns

`RegionalResponseTimeStats`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->monitors->responseTimeStatsByRegion(id: 123);
```
