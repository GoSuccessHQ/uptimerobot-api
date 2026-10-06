# `$uptimeRobot->maintenanceWindows->all()`

> UptimeRobot API v3 · `GET /maintenance-windows`

Iterate lazily over every item of list(), across all pages.

The cursor is the ID of the last window of the previous page, as the official Terraform provider sends it. Only a single page was observable: an account without windows gets {"data": []} without nextLink (verified live).

## Signature

```php
public function all(?int $limit = null): Paginator
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$limit` | `int\|null` | no | Maximum number of maintenance windows to return per page. Default: 50, Min: 1, Max: 200. |

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
