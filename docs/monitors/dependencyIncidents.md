# `$uptimeRobot->monitors->dependencyIncidents()`

> UptimeRobot API v3 · `GET /monitors/{id}/dependency/incidents`

Get the incident history of a dependency monitor

Returns every incident (resolved and unresolved) recorded for the provider a type-10 dependency monitor tracks, newest first, with verbatim provider updates. An empty history returns an empty array with a 200, never an error.

Only for DEPENDENCY monitors: every incident of the tracked provider, resolved or not, newest first, with the updates the provider published; empty if there were none. Verified live on a monitor tracking bunny.net: three resolved incidents with severity minor.

## Signature

```php
public function dependencyIncidents(int $id): array
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `int` | yes |  |

## Returns

`list<DependencyIncident>`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->monitors->dependencyIncidents(id: 123);
```
