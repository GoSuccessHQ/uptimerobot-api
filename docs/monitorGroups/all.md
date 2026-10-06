# `$uptimeRobot->monitorGroups->all()`

> UptimeRobot API v3 · `GET /monitor-groups`

Iterate lazily over every item of list(), across all pages.

The groups carry neither their monitors nor their number; MonitorResource::list() with groupId lists the monitors of a group (verified live).

## Signature

```php
public function all(?int $limit = null): Paginator
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$limit` | `int\|null` | no | Maximum number of monitor groups to return per page. Default: 50, Min: 1, Max: 200. |

## Returns

`Paginator<MonitorGroup>`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

foreach ($uptimeRobot->monitorGroups->all() as $item) {
    // ...
}
```
