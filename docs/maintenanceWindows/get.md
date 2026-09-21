# `$uptimeRobot->maintenanceWindows->get()`

> UptimeRobot API v3 · `GET /maintenance-windows/{id}`

Get a maintenance window by ID

An unknown ID raises a NotFoundException, the ID of another account's window a ForbiddenException with the code 000-006 (both verified live).

## Signature

```php
public function get(int $id): MaintenanceWindow
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `int` | yes | ID of the maintenance window |

## Returns

`MaintenanceWindow`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->maintenanceWindows->get(id: 123);
```
