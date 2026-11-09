# `$uptimeRobot->monitors->thirdPartyServiceIncident()`

> UptimeRobot API v3 · `GET /monitors/{id}/third-party-service/incidents/{incidentId}`

Get one incident of a third party service

Returns one incident from the incident history of a third party service monitor, resolved or not, with its verbatim provider updates. affectedComponents lists the affected components the monitor tracks, or every affected component when the monitor tracks the whole provider.

One incident of thirdPartyServiceIncidents(), with the affected components the monitor tracks, or every affected component if it tracks the whole provider (not verified live).

## Signature

```php
public function thirdPartyServiceIncident(int $id, string $incidentId): ThirdPartyServiceIncidentDetail
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `int` | yes | The monitor ID |
| `$incidentId` | `string` | yes | The incident ID from the incident history of the third party service. A string, since it can exceed the safe integer range. |

## Returns

`ThirdPartyServiceIncidentDetail`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->monitors->thirdPartyServiceIncident(
    id: 123,
    incidentId: '123456789',
);
```
