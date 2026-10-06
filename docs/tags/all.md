# `$uptimeRobot->tags->all()`

> UptimeRobot API v3 · `GET /tags`

Iterate lazily over every item of list(), across all pages.

## Signature

```php
public function all(?int $limit = null): Paginator
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$limit` | `int\|null` | no | Maximum number of tags to return per page. Default: 50, Min: 1, Max: 200. |

## Returns

`Paginator<Tag>`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

foreach ($uptimeRobot->tags->all() as $item) {
    // ...
}
```
