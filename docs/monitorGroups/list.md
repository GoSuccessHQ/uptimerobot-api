# `$uptimeRobot->monitorGroups->list()`

> UptimeRobot API v3 · `GET /monitor-groups`

List monitor groups

List all monitor groups in a user's account. Values can be paginated with the cursor parameter.

The groups carry neither their monitors nor their number; MonitorResource::list() with groupId lists the monitors of a group (verified live).

## Signature

```php
public function list(?int $cursor = null): Page
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$cursor` | `int\|null` | no | The cursor of the page to return, as the previous page reported it; null for the first page. |

## Returns

`Page<MonitorGroup>`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->monitorGroups->list();
```
