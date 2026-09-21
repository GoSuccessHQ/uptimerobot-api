# `$uptimeRobot->maintenanceWindows->update()`

> UptimeRobot API v3 · `PATCH /maintenance-windows/{id}`

Update a maintenance window

Only the properties that are set are sent. The validator runs before the window is looked up (verified live), so an invalid change to an unknown ID raises a BadRequestException, a valid one a NotFoundException.

## Signature

```php
public function update(int $id, MaintenanceWindowUpdate $changes): MaintenanceWindow
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `int` | yes | ID of the maintenance window |
| `$changes` | `MaintenanceWindowUpdate` | yes |  |

## Returns

`MaintenanceWindow`

## Example

```php
use GoSuccess\UptimeRobot\Enum\MaintenanceWindowStatus;
use GoSuccess\UptimeRobot\Model\MaintenanceWindowUpdate;
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->maintenanceWindows->update(
    id: 123,
    changes: new MaintenanceWindowUpdate(
        status: MaintenanceWindowStatus::Paused,
    ),
);
```
