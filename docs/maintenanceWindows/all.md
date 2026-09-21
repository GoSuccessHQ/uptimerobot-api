# `$uptimeRobot->maintenanceWindows->all()`

> UptimeRobot API v3 · `GET /maintenance-windows`

Iterate lazily over every item of list(), across all pages.

The cursor is the ID of the last window of the previous page, as the official Terraform provider sends it. Only a single page was observable: an account without windows gets {"data": []} without nextLink (verified live).

## Signature

```php
public function all(): Paginator
```

## Returns

`Paginator<MaintenanceWindow>`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

foreach ($uptimeRobot->maintenanceWindows->all() as $item) {
    // ...
}
```
