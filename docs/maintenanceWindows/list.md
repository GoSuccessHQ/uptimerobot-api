# `$uptimeRobot->maintenanceWindows->list()`

> UptimeRobot API v3 · `GET /maintenance-windows`

List maintenance windows

The cursor is the ID of the last window of the previous page, as the official Terraform provider sends it. Only a single page was observable: an account without windows gets {"data": []} without nextLink (verified live).

## Signature

```php
public function list(?int $cursor = null): Page
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$cursor` | `int\|null` | no | The cursor of the page to return, as the previous page reported it; null for the first page. |

## Returns

`Page<MaintenanceWindow>`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->maintenanceWindows->list();
```
