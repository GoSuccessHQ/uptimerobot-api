# `$uptimeRobot->alertContacts->get()`

> UptimeRobot API v3 · `GET /alert-contacts/{id}`

Get a personal alert contact by ID

Get a personal alert contact details by ID

An unknown ID raises a NotFoundException (verified live).

## Signature

```php
public function get(int $id): AlertContact
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `int` | yes | ID of the personal alert contact |

## Returns

`AlertContact`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->alertContacts->get(id: 123);
```
