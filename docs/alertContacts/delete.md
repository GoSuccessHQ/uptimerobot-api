# `$uptimeRobot->alertContacts->delete()`

> UptimeRobot API v3 · `DELETE /alert-contacts/{id}`

Delete a personal alert contact

An unknown ID raises a NotFoundException (verified live).

## Signature

```php
public function delete(int $id): void
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `int` | yes | ID of the personal alert contact |

## Returns

`void`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$uptimeRobot->alertContacts->delete(id: 123);
```
