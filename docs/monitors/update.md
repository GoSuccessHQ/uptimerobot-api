# `$uptimeRobot->monitors->update()`

> UptimeRobot API v3 · `PATCH /monitors/{id}`

Update a monitor

Only the properties that are set are sent. config is merged into the current settings key by key (see MonitorConfigUpdate; null clears them), while customHttpHeaders, customFields and successHttpResponseCodes replace the current values (verified live).

## Signature

```php
public function update(int $id, MonitorUpdate $changes): Monitor
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `int` | yes |  |
| `$changes` | `MonitorUpdate` | yes |  |

## Returns

`Monitor`

## Example

```php
use GoSuccess\UptimeRobot\Model\MonitorUpdate;
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->monitors->update(id: 123, changes: new MonitorUpdate(/* ... */));
```
