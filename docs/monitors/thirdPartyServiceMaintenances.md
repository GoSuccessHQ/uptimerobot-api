# `$uptimeRobot->monitors->thirdPartyServiceMaintenances()`

> UptimeRobot API v3 · `GET /monitors/{id}/third-party-service/maintenances`

Get the upcoming maintenance windows of a third party service

Returns the maintenance windows the provider a third party service monitor tracks has announced, earliest start first: windows in progress now and windows starting within the next 30 days. Responses are cached for 60 seconds, so a window that ended within the last minute can still appear; `window` is worked out from scheduledFor/scheduledUntil when the response is built (announced or in_progress). Nothing scheduled returns an empty array with a 200, never an error.

Only for THIRD_PARTY_SERVICE monitors: the maintenance windows the tracked provider announced, earliest start first, limited to those in progress and those starting within the next 30 days; empty if there are none, never an error. The answers are cached for a minute, so a window that just ended can still appear, and window says where a window stands when the API answers (according to the specification; not verified live).

## Signature

```php
public function thirdPartyServiceMaintenances(int $id): array
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `int` | yes |  |

## Returns

`list<ThirdPartyServiceMaintenance>`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->monitors->thirdPartyServiceMaintenances(id: 123);
```
