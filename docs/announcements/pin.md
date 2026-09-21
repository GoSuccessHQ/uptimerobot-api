# `$uptimeRobot->announcements->pin()`

> UptimeRobot API v3 · `POST /psps/{pspId}/announcements/{id}/pin`

Pin an announcement

Pin an announcement to the Public Status Page. This operation is idempotent.

Sends an empty JSON object, which the API insists on although the specification declares no body (verified live). Requires the plan feature psp-subscribers; without it, the API raises a ForbiddenException with the code 000-003 before it looks at the status page or the request (verified live). Not verified live beyond that: the test account lacks the feature.

## Signature

```php
public function pin(int $statusPageId, int $id): void
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$statusPageId` | `int` | yes | ID of the Public Status Page |
| `$id` | `int` | yes | ID of the announcement to pin |

## Returns

`void`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$uptimeRobot->announcements->pin(statusPageId: 123, id: 123);
```
