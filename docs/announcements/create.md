# `$uptimeRobot->announcements->create()`

> UptimeRobot API v3 · `POST /psps/{pspId}/announcements`

Create an announcement

Create a new announcement for a Public Status Page

The specification marks no property as required, not even title and content. Requires the plan feature psp-subscribers; without it, the API raises a ForbiddenException with the code 000-003 before it looks at the status page or the request (verified live). Not verified live beyond that: the test account lacks the feature.

## Signature

```php
public function create(int $statusPageId, AnnouncementCreate $announcement): Announcement
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$statusPageId` | `int` | yes | ID of the Public Status Page |
| `$announcement` | `AnnouncementCreate` | yes |  |

## Returns

`Announcement`

## Example

```php
use GoSuccess\UptimeRobot\Model\AnnouncementCreate;
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->announcements->create(
    statusPageId: 123,
    announcement: new AnnouncementCreate(
        title: 'Scheduled maintenance',
        content: 'The dashboard is read-only from 22:00 to 23:00 UTC.',
    ),
);
```
