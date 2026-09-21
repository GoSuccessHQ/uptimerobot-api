# `$uptimeRobot->statusPages->all()`

> UptimeRobot API v3 · `GET /psps`

Iterate lazily over every item of list(), across all pages.

The cursor is the ID of the last status page of the previous page, as the official Terraform provider reads it from nextLink. Only a single page was observable: an account without status pages gets {"data": []} without nextLink (verified live).

## Signature

```php
public function all(): Paginator
```

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
