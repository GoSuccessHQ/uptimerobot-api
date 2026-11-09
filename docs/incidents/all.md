# `$uptimeRobot->incidents->all()`

> UptimeRobot API v3 · `GET /incidents`

Iterate lazily over every item of list(), across all pages.

Newest first. The cursor is opaque, taken from nextLink of the previous page; according to the specification of November 9, 2026, an incident ID still works, and an unknown or foreign cursor raises a BadRequestException. With type or cause, a page can hold fewer incidents than limit, even none, while nextLink is set; all() keeps paging. monitorName matches part of the name, ignoring case; startedAfter and startedBefore bound startedAt (verified live). all() requests pages of 200 incidents, the most the specification allows.

## Signature

```php
public function all(
    int $limit = 200,
    ?int $monitorId = null,
    ?string $monitorName = null,
    ?DateTimeInterface $startedAfter = null,
    ?DateTimeInterface $startedBefore = null,
    ?IncidentStatusFilter $status = null,
    ?IncidentTypeFilter $type = null,
    ?IncidentCauseFilter $cause = null,
): Paginator
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$limit` | `int` | no | Maximum number of items to return per page. Default: 50, Min: 1, Max: 200. |
| `$monitorId` | `int\|null` | no | Filter incidents by monitor ID |
| `$monitorName` | `string\|null` | no | Filter incidents by monitor name (partial match) |
| `$startedAfter` | `DateTimeInterface\|null` | no | Only incidents that started at or after this time, compared to the millisecond (verified live); sent as ISO 8601 in UTC. To continue after an incident, pass its startedAt plus one millisecond. |
| `$startedBefore` | `DateTimeInterface\|null` | no | Only incidents that started at or before this time, compared to the millisecond (verified live); sent as ISO 8601 in UTC. |
| `$status` | `IncidentStatusFilter\|null` | no | Filter incidents by status. "open" returns only currently ongoing incidents, regardless of started_after/started_before. |
| `$type` | `IncidentTypeFilter\|null` | no | Filter incidents by type. With a filter a page may hold fewer items than `limit`, even none, while `nextLink` is set: keep paging until it is absent. |
| `$cause` | `IncidentCauseFilter\|null` | no | Filter incidents by root cause group: Timeout, an HTTP status class (2xx–5xx), DNS, AssertionFailed, InvalidJSON or ConfigError. Combines with `type` and the other filters. With a filter a page may hold fewer items than `limit`, even none, while `nextLink` is set: keep paging until it is absent. |

## Returns

`Paginator<IncidentSummary>`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

foreach ($uptimeRobot->incidents->all() as $item) {
    // ...
}
```
