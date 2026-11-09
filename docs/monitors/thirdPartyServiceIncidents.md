# `$uptimeRobot->monitors->thirdPartyServiceIncidents()`

> UptimeRobot API v3 · `GET /monitors/{id}/third-party-service/incidents`

Get the incident history of a third party service

Returns every incident (resolved and unresolved) recorded for the provider a third party service monitor tracks, newest first, with verbatim provider updates. An empty history returns an empty array with a 200, never an error.

Only for THIRD_PARTY_SERVICE monitors: every incident of the tracked provider, resolved or not, newest first, with the updates the provider published; empty if there were none. Verified live on a DEPENDENCY monitor tracking bunny.net, as the type was called until November 2026: three resolved incidents with severity minor.

## Signature

```php
public function thirdPartyServiceIncidents(int $id): array
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `int` | yes |  |

## Returns

`list<ThirdPartyServiceIncident>`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->monitors->thirdPartyServiceIncidents(id: 123);
```
