# `$uptimeRobot->incidents->allActivity()`

> UptimeRobot API v3 · `GET /incidents/{id}/activity-log`

Iterate lazily over every item of activityLog(), across all pages.

Newest first: status updates of the checks (remoteNode is null on Up entries, responseTime only set on Slow ones), notifications and, with the plan feature incident-comments, comments (verified live). According to the specification of October 5, 2026, limit and cursor page through the notifications, and the first page also carries every comment, status update and system event, so it can hold more than limit entries; before that specification, the API ignored both and nextLink was always null (verified live then, on incidents with up to 6 entries). The pages are not verified live.

## Signature

```php
public function allActivity(string $id, ?int $limit = null): Paginator
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `string` | yes | ID of the incident |
| `$limit` | `int\|null` | no | Maximum number of notification entries per page (1-200, default 50). The first page also carries every comment, status update and system event on top of those, so it can hold more than `limit` entries. |

## Returns

`Paginator<ActivityLogEntry>`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

foreach ($uptimeRobot->incidents->allActivity(id: '123456789') as $item) {
    // ...
}
```
