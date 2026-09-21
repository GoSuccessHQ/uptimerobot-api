# `$uptimeRobot->alertContacts->update()`

> UptimeRobot API v3 · `PATCH /alert-contacts/{id}`

Update a personal alert contact

Only the properties that are set are sent. The validator runs before the contact is looked up, so an invalid change to an unknown ID raises a BadRequestException, a valid one a NotFoundException (verified live). Not verified live beyond that: the owner of the test account allows no contacts to be changed.

## Signature

```php
public function update(int $id, AlertContactUpdate $changes): AlertContact
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `int` | yes | ID of the personal alert contact |
| `$changes` | `AlertContactUpdate` | yes |  |

## Returns

`AlertContact`

## Example

```php
use GoSuccess\UptimeRobot\Model\AlertContactUpdate;
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->alertContacts->update(id: 123, changes: new AlertContactUpdate(/* ... */));
```
