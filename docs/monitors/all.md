# `$uptimeRobot->monitors->all()`

> UptimeRobot API v3 · `GET /monitors`

Iterate lazily over every item of list(), across all pages.

Filters combine with AND; status and tags match any of their values, while every customField entry ("key:value") must match, and the groupId 0 selects the monitors in no group (verified live). all() requests pages of 200 monitors, the most the API allows.

## Signature

```php
public function all(
    int $limit = 200,
    ?array $customField = null,
    ?int $groupId = null,
    ?array $status = null,
    ?string $name = null,
    ?string $url = null,
    ?array $tags = null,
): Paginator
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$limit` | `int` | no | Monitors per page, from 1 to 200 (verified live: "Limit must be between 1 and 200"); the specification gives 50 as the default. |
| `$customField` | `list<string>\|null` | no | Custom field filters as "key:value" strings, split at the first colon; a monitor must match all of them (verified live). Each is sent as a customField parameter of its own. |
| `$groupId` | `int\|null` | no | The monitor group; 0 selects the monitors in no group (verified live). |
| `$status` | `list<MonitorStatus>\|null` | no | The statuses to filter by; a monitor matches if it has any of them (verified live). Sent as one comma-separated value; an empty list filters nothing. |
| `$name` | `string\|null` | no | Filter monitors by name. Case-insensitive partial match on the monitor friendly name. |
| `$url` | `string\|null` | no | Filter monitors by URL. Case-insensitive partial match on the monitor URL. |
| `$tags` | `list<string>\|null` | no | The tag names to filter by, compared case-sensitively; according to the specification, a monitor matches if it has any of them. Sent as one comma-separated value; an empty list filters nothing. |

## Returns

`Paginator<Monitor>`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

foreach ($uptimeRobot->monitors->all() as $item) {
    // ...
}
```
