# `$uptimeRobot->maintenanceWindows->create()`

> UptimeRobot API v3 · `POST /maintenance-windows`

Create a maintenance window

Weekly and monthly windows need days. date may be left out of recurring windows: the specification requires it for every interval, but the API's validator does not ask for it (verified live), and the official Terraform provider creates daily, weekly and monthly windows without it. There is no status here; update() pauses a window.

## Signature

```php
public function create(MaintenanceWindowCreate $window): MaintenanceWindow
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$window` | `MaintenanceWindowCreate` | yes |  |

## Returns

`MaintenanceWindow`

## Example

```php
use GoSuccess\UptimeRobot\Model\MaintenanceWindowCreate;
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->maintenanceWindows->create(window: new MaintenanceWindowCreate(/* ... */));
```
