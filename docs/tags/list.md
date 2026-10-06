# `$uptimeRobot->tags->list()`

> UptimeRobot API v3 · `GET /tags`

List user tags

Get a paginated list of tags for the authenticated user, sorted by ID in ascending order

## Signature

```php
public function list(?int $cursor = null, ?int $limit = null): Page
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$cursor` | `int\|null` | no | The cursor of the page to return, as the previous page reported it; null for the first page. |
| `$limit` | `int\|null` | no | Maximum number of tags to return per page. Default: 50, Min: 1, Max: 200. |

## Returns

`Page<Tag>`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->tags->list();
```
