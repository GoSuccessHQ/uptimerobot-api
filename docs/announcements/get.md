# `$uptimeRobot->announcements->get()`

> UptimeRobot API v3 · `GET /psps/{pspId}/announcements/{id}`

Get an announcement by ID

Retrieve a single announcement for a Public Status Page

Requires the plan feature psp-subscribers; without it, the API raises a ForbiddenException with the code 000-003 before it looks at the status page or the request (verified live). Not verified live beyond that: the test account lacks the feature.

## Signature

```php
public function get(int $statusPageId, int $id): Announcement
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$statusPageId` | `int` | yes | ID of the Public Status Page |
| `$id` | `int` | yes | ID of the announcement |

## Returns

`Announcement`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->announcements->get(statusPageId: 123, id: 123);
```
