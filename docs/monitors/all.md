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
| `$limit` | `int` | no | Maximum number of monitors to return per page. Default: 50, Min: 1, Max: 200. |
| `$customField` | `list<string>\|null` | no | Filter monitors by custom field key:value pairs. Format: customField=key:value. Multiple filters use AND logic. Split on first colon only. |
| `$groupId` | `int\|null` | no | Filter monitors by monitor group ID. |
| `$status` | `list<MonitorStatus>\|null` | no | Comma-separated list of status values to filter monitors. Uses OR logic (matches any specified status). Case-insensitive. Allowed values: PAUSED, STARTED, UP, LOOKS_DOWN, DOWN. |
| `$name` | `string\|null` | no | Filter monitors by name. Case-insensitive partial match on the monitor friendly name. |
| `$url` | `string\|null` | no | Filter monitors by URL. Case-insensitive partial match on the monitor URL. |
| `$tags` | `list<string>\|null` | no | Comma-separated list of tag names to filter monitors. Uses OR logic (matches any specified tag). Case-sensitive. |

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
