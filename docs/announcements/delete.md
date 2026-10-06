# `$uptimeRobot->announcements->delete()`

> UptimeRobot API v3 · `DELETE /psps/{pspId}/announcements/{id}`

Delete an announcement

Permanently delete an announcement from a Public Status Page. This cannot be undone.

Permanent; an unknown status page or announcement raises a NotFoundException, according to the specification. Requires the plan feature psp-subscribers; without it, the API raises a ForbiddenException with the code 000-003 before it looks at the status page or the request (verified live). Not verified live beyond that: the test account lacks the feature.

## Signature

```php
public function delete(int $statusPageId, int $id): void
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$statusPageId` | `int` | yes | ID of the Public Status Page |
| `$id` | `int` | yes | ID of the announcement to delete |

## Returns

`void`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$uptimeRobot->announcements->delete(statusPageId: 123, id: 123);
```
