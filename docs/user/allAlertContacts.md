# `$uptimeRobot->user->allAlertContacts()`

> UptimeRobot API v3 · `GET /user/all-alert-contacts`

Get all alert contacts

Get all alert contacts including personal, notify-only, and organization members alert contacts

## Signature

```php
public function allAlertContacts(): array
```

## Returns

`list<AllAlertContact>`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->user->allAlertContacts();
```
