# `$uptimeRobot->incidentComments->all()`

> UptimeRobot API v3 · `GET /incidents/{id}/comments`

Iterate lazily over every item of list(), across all pages.

Oldest first, according to the specification. The cursor is the ID of the last comment of the previous page. all() requests pages of 100 comments, the most the specification allows. Requires the plan feature incident-comments; without it, the API raises a ForbiddenException with the code 000-003 before it looks at the incident or the request (verified live). Not verified live beyond that: the test account lacks the feature.

## Signature

```php
public function all(string $incidentId, int $limit = 100): Paginator
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$incidentId` | `string` | yes | The incident ID |
| `$limit` | `int` | no | Comments per page, from 1 to 100; the specification gives 50 as the default. |

## Returns

`Paginator<IncidentComment>`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

foreach ($uptimeRobot->incidentComments->all(
    incidentId: '123456789',
) as $item) {
    // ...
}
```
