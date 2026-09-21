# `$uptimeRobot->announcements->list()`

> UptimeRobot API v3 · `GET /psps/{pspId}/announcements`

List announcements

List all announcements for a Public Status Page. Results are sorted by creation date (newest first) and can be filtered by status.

Newest first, according to the specification. The status filter takes the upper-case values of the specification (AnnouncementStatusFilter), unlike the title-case AnnouncementStatus of the requests. Requires the plan feature psp-subscribers; without it, the API raises a ForbiddenException with the code 000-003 before it looks at the status page or the request (verified live). Not verified live beyond that: the test account lacks the feature.

## Signature

```php
public function list(
    int $statusPageId,
    ?int $cursor = null,
    ?AnnouncementStatusFilter $status = null,
): Page
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$statusPageId` | `int` | yes | ID of the Public Status Page |
| `$cursor` | `int\|null` | no | The cursor of the page to return, as the previous page reported it; null for the first page. |
| `$status` | `AnnouncementStatusFilter\|null` | no | Filter announcements by status |

## Returns

`Page<Announcement>`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->announcements->list(statusPageId: 123);
```
