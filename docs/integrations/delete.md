# `$uptimeRobot->integrations->delete()`

> UptimeRobot API v3 · `DELETE /integrations/{id}`

Delete an Integration

An unknown ID raises a NotFoundException (verified live).

## Signature

```php
public function delete(int $id): void
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `int` | yes | ID of the integration |

## Returns

`void`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$uptimeRobot->integrations->delete(id: 123);
```
