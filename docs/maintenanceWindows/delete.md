# `$uptimeRobot->maintenanceWindows->delete()`

> UptimeRobot API v3 · `DELETE /maintenance-windows/{id}`

Delete a maintenance window

An unknown ID raises a NotFoundException (verified live).

## Signature

```php
public function delete(int $id): void
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `int` | yes |  |

## Returns

`void`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$uptimeRobot->maintenanceWindows->delete(id: 123);
```
