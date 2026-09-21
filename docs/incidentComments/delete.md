# `$uptimeRobot->incidentComments->delete()`

> UptimeRobot API v3 · `DELETE /incidents/{id}/comments/{commentId}`

Delete incident comment

Delete a comment from an incident

Requires the plan feature incident-comments; without it, the API raises a ForbiddenException with the code 000-003 before it looks at the incident or the request (verified live). Not verified live beyond that: the test account lacks the feature.

## Signature

```php
public function delete(string $incidentId, int $id): void
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$incidentId` | `string` | yes | ID of the incident |
| `$id` | `int` | yes | ID of the comment |

## Returns

`void`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$uptimeRobot->incidentComments->delete(incidentId: '123456789', id: 123);
```
