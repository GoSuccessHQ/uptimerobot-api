# `$uptimeRobot->announcements->all()`

> UptimeRobot API v3 · `GET /psps/{pspId}/announcements`

Iterate lazily over every item of list(), across all pages.

Newest first, according to the specification. The status filter takes the upper-case values of the specification (AnnouncementStatusFilter), unlike the title-case AnnouncementStatus of the requests. Requires the plan feature psp-subscribers; without it, the API raises a ForbiddenException with the code 000-003 before it looks at the status page or the request (verified live). Not verified live beyond that: the test account lacks the feature.

## Signature

```php
public function all(int $statusPageId, ?AnnouncementStatusFilter $status = null): Paginator
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$statusPageId` | `int` | yes | ID of the Public Status Page |
| `$status` | `AnnouncementStatusFilter\|null` | no | Filter announcements by status |

## Returns

`Paginator<Announcement>`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

foreach ($uptimeRobot->announcements->all(statusPageId: 123) as $item) {
    // ...
}
```
