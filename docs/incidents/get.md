# `$uptimeRobot->incidents->get()`

> UptimeRobot API v3 · `GET /incidents/{id}`

Get an incident by ID

Get incident details including root cause information

Unlike the items of list(), an incident has neither type nor monitor; cause 0 marks a slow response. rootCause, null according to the specification, was present on every incident read, with an empty url and null headers for a slow response; assertionDiagnostics is only expected for API monitors and was always null. An unknown ID raises a NotFoundException (all verified live).

## Signature

```php
public function get(string $id): Incident
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `string` | yes | The incident ID |

## Returns

`Incident`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->incidents->get(id: '123456789');
```
