# `$uptimeRobot->incidentComments->update()`

> UptimeRobot API v3 · `PATCH /incidents/{id}/comments/{commentId}`

Update an incident comment

Updates an existing comment on an incident

content is required, so the text is always replaced. Requires the plan feature incident-comments; without it, the API raises a ForbiddenException with the code 000-003 before it looks at the incident or the request (verified live). Not verified live beyond that: the test account lacks the feature.

## Signature

```php
public function update(string $incidentId, int $id, IncidentCommentUpdate $changes): IncidentComment
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$incidentId` | `string` | yes | The incident ID |
| `$id` | `int` | yes | The comment ID |
| `$changes` | `IncidentCommentUpdate` | yes |  |

## Returns

`IncidentComment`

## Example

```php
use GoSuccess\UptimeRobot\Model\IncidentCommentUpdate;
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->incidentComments->update(incidentId: '123456789', id: 123, changes: new IncidentCommentUpdate(/* ... */));
```
