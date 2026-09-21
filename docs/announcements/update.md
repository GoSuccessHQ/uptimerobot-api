# `$uptimeRobot->announcements->update()`

> UptimeRobot API v3 · `PATCH /psps/{pspId}/announcements/{id}`

Update an announcement

Update an existing announcement for a Public Status Page

Only the properties that are set are sent. Requires the plan feature psp-subscribers; without it, the API raises a ForbiddenException with the code 000-003 before it looks at the status page or the request (verified live). Not verified live beyond that: the test account lacks the feature.

## Signature

```php
public function update(int $statusPageId, int $id, AnnouncementUpdate $changes): Announcement
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$statusPageId` | `int` | yes | ID of the Public Status Page |
| `$id` | `int` | yes | ID of the announcement to update |
| `$changes` | `AnnouncementUpdate` | yes |  |

## Returns

`Announcement`

## Example

```php
use GoSuccess\UptimeRobot\Model\AnnouncementUpdate;
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->announcements->update(
    statusPageId: 123,
    id: 123,
    changes: new AnnouncementUpdate(title: 'Maintenance completed'),
);
```
