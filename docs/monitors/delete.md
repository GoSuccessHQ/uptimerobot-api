# `$uptimeRobot->monitors->delete()`

> UptimeRobot API v3 · `DELETE /monitors/{id}`

Delete a monitor

The monitor is gone at once; later calls for it fail with a NotFoundException (verified live).

## Signature

```php
public function delete(int $id): void
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `int` | yes |  |

## Returns

`void`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$uptimeRobot->monitors->delete(id: 123);
```
