# `$uptimeRobot->user->alertContacts()`

> UptimeRobot API v3 · `GET /user/alert-contacts`

Get alert contacts

## Signature

```php
public function alertContacts(): array
```

## Returns

`list<AlertContact>`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->user->alertContacts();
```
