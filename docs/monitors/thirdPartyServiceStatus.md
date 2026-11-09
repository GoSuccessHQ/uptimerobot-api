# `$uptimeRobot->monitors->thirdPartyServiceStatus()`

> UptimeRobot API v3 · `GET /monitors/{id}/third-party-service/status`

Get the current status of a third party service

Returns the fine-grained 4-state (operational, minor, major, maintenance) for a third party service monitor. Distinct from the coarse monitor status field, which cannot tell a partial degradation from a full outage.

Only for THIRD_PARTY_SERVICE monitors: the state of the tracked provider, which tells a partial degradation (minor) from a full outage (major), unlike the status of the monitor. For a monitor of another type, the API raised a NotFoundException (000-004) when the type was still called DEPENDENCY (verified live then).

## Signature

```php
public function thirdPartyServiceStatus(int $id): ThirdPartyServiceStatus
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `int` | yes |  |

## Returns

`ThirdPartyServiceStatus`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->monitors->thirdPartyServiceStatus(id: 123);
```
