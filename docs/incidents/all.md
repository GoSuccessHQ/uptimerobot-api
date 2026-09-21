# `$uptimeRobot->incidents->all()`

> UptimeRobot API v3 · `GET /incidents`

Iterate lazily over every item of list(), across all pages.

Newest first. The cursor is the ID of the last incident of the previous page; the incidents that started before it follow. monitorName matches part of the name, ignoring case; startedAfter and startedBefore bound startedAt. The page size is not documented: all 14 incidents of the test account came on one page (all verified live).

## Signature

```php
public function all(
    ?int $monitorId = null,
    ?string $monitorName = null,
    ?DateTimeInterface $startedAfter = null,
    ?DateTimeInterface $startedBefore = null,
): Paginator
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$monitorId` | `int\|null` | no | Filter incidents by monitor ID |
| `$monitorName` | `string\|null` | no | Filter incidents by monitor name (partial match) |
| `$startedAfter` | `DateTimeInterface\|null` | no | Only incidents that started after this time, sent as ISO 8601 in UTC (verified live). |
| `$startedBefore` | `DateTimeInterface\|null` | no | Only incidents that started before this time, sent as ISO 8601 in UTC (verified live). |

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
