# `$uptimeRobot->thirdPartyServices->maintenances()`

> UptimeRobot API v3 · `GET /third-party-services/maintenances`

Get the upcoming maintenance windows across all third party services

Returns the maintenance windows announced by every provider the caller tracks with a third party service monitor, in one list ordered by start (earliest first): windows in progress now and windows starting within the next 30 days. Ended windows are never returned. Each row names the third party service monitor it belongs to (monitorId, friendlyName). `window` is worked out at request time from scheduledFor/scheduledUntil (announced or in_progress). If one provider cannot be read it is left out; if none can, the request fails. Nothing scheduled returns an empty array with a 200, never an error.

The maintenance windows every tracked provider announced, in one list ordered by start, limited to those in progress and those starting within the next 30 days; each row names the monitor it belongs to. A provider that cannot be read is left out, and the request only fails if none can be read. Nothing scheduled is an empty list, never an error (according to the specification; not verified live).

## Signature

```php
public function maintenances(): array
```

## Returns

`list<ThirdPartyServiceMaintenanceWithMonitor>`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->thirdPartyServices->maintenances();
```
