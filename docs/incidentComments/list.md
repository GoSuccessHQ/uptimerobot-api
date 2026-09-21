# `$uptimeRobot->incidentComments->list()`

> UptimeRobot API v3 · `GET /incidents/{id}/comments`

List incident comments

Returns paginated comments for a specific incident, ordered by creation date ascending (oldest first).

Oldest first, according to the specification. The cursor is the ID of the last comment of the previous page. all() requests pages of 100 comments, the most the specification allows. Requires the plan feature incident-comments; without it, the API raises a ForbiddenException with the code 000-003 before it looks at the incident or the request (verified live). Not verified live beyond that: the test account lacks the feature.

## Signature

```php
public function list(string $incidentId, ?int $cursor = null, ?int $limit = null): Page
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$incidentId` | `string` | yes | The incident ID |
| `$cursor` | `int\|null` | no | The cursor of the page to return, as the previous page reported it; null for the first page. |
| `$limit` | `int\|null` | no | Number of comments to return (1-100, default 50) |

## Returns

`Page<IncidentComment>`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->incidentComments->list(incidentId: '123456789');
```
