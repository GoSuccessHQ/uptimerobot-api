# `$uptimeRobot->statusPages->delete()`

> UptimeRobot API v3 · `DELETE /psps/{id}`

Delete a PSP

Delete a Public Status Page

An unknown ID raises a NotFoundException (verified live).

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

$uptimeRobot->statusPages->delete(id: 123);
```
