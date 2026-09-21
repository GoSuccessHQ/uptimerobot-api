# `$uptimeRobot->tags->list()`

> UptimeRobot API v3 · `GET /tags`

List user tags

Get a paginated list of tags for the authenticated user, sorted by ID in ascending order

## Signature

```php
public function list(?int $cursor = null): Page
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$cursor` | `int\|null` | no | The cursor of the page to return, as the previous page reported it; null for the first page. |

## Returns

`Page<Tag>`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->tags->list();
```
