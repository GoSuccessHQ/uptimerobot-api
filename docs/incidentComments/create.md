# `$uptimeRobot->incidentComments->create()`

> UptimeRobot API v3 · `POST /incidents/{id}/comments`

Create incident comment

Create a new comment on an incident

The specification documents no response body. This returns the comment if the API sends one, as UptimeRobot's incident-response guide for its MCP server takes the commentId "from the create response", and null for an empty response. Requires the plan feature incident-comments; without it, the API raises a ForbiddenException with the code 000-003 before it looks at the incident or the request (verified live). Not verified live beyond that: the test account lacks the feature.

## Signature

```php
public function create(string $incidentId, IncidentCommentCreate $comment): ?IncidentComment
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$incidentId` | `string` | yes | ID of the incident |
| `$comment` | `IncidentCommentCreate` | yes |  |

## Returns

`?IncidentComment`

## Example

```php
use GoSuccess\UptimeRobot\Model\IncidentCommentCreate;
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->incidentComments->create(
    incidentId: '123456789',
    comment: new IncidentCommentCreate(content: 'We are looking into it.'),
);
```
