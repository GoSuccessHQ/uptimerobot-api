# `$uptimeRobot->monitors->create()`

> UptimeRobot API v3 · `POST /monitors`

Create a monitor

New monitors of any type can be created using this endpoint. The request body is documented as one schema per monitor type — pick the one matching the `type` you are creating.

Pass the model of the monitor type, e.g. HttpMonitorCreate, which sends its type. A new monitor is STARTED until its first check and, unless they are set, reports authType HTTP_BASIC and httpMethodType null (verified live). The deprecated region string regionalData is left out; set the regions with regionData.

## Signature

```php
public function create(MonitorCreate $monitor): Monitor
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$monitor` | `MonitorCreate` | yes |  |

## Returns

`Monitor`

## Example

```php
use GoSuccess\UptimeRobot\Model\HttpMonitorCreate;
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->monitors->create(monitor: new HttpMonitorCreate(/* ... */));
```
