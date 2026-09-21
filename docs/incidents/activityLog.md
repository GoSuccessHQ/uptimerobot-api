# `$uptimeRobot->incidents->activityLog()`

> UptimeRobot API v3 · `GET /incidents/{id}/activity-log`

Get incident activity log

Returns the activity log for an incident, including status updates, comments, and notifications. Sorted by date descending.

Newest first: status updates of the checks (remoteNode is null on Up entries, responseTime only set on Slow ones), notifications and, with the plan feature incident-comments, comments. Only the first page is available: the API reports a nextLink, but ignores cursor and limit (verified live). No incident read had more than 6 entries, and nextLink was always null.

## Signature

```php
public function activityLog(string $id): array
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `string` | yes | ID of the incident |

## Returns

`list<ActivityLogEntry>`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->incidents->activityLog(id: '123456789');
```
