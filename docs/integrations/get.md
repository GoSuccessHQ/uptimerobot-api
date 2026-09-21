# `$uptimeRobot->integrations->get()`

> UptimeRobot API v3 · `GET /integrations/{id}`

Get an integration by ID

Get an integration details by ID

An unknown ID raises a NotFoundException with the code 000-004, the ID of a personal contact one with the code 021-005 (verified live).

## Signature

```php
public function get(int $id): Integration
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `int` | yes | ID of the integration |

## Returns

`Integration`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->integrations->get(id: 123);
```
