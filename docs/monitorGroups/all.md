# `$uptimeRobot->monitorGroups->all()`

> UptimeRobot API v3 · `GET /monitor-groups`

Iterate lazily over every item of list(), across all pages.

The groups carry neither their monitors nor their number; MonitorResource::list() with groupId lists the monitors of a group (verified live).

## Signature

```php
public function all(): Paginator
```

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
