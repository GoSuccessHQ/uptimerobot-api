# `$uptimeRobot->tags->all()`

> UptimeRobot API v3 · `GET /tags`

Iterate lazily over every item of list(), across all pages.

## Signature

```php
public function all(): Paginator
```

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
