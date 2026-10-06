# `$uptimeRobot->statusPages->all()`

> UptimeRobot API v3 · `GET /psps`

Iterate lazily over every item of list(), across all pages.

The cursor is the ID of the last status page of the previous page, as the official Terraform provider reads it from nextLink. Only a single page was observable: an account without status pages gets {"data": []} without nextLink (verified live).

## Signature

```php
public function all(?int $limit = null): Paginator
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$limit` | `int\|null` | no | Maximum number of status pages to return per page. Default: 50, Min: 1, Max: 200. |

## Returns

`Paginator<StatusPage>`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

foreach ($uptimeRobot->statusPages->all() as $item) {
    // ...
}
```
