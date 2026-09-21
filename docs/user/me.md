# `$uptimeRobot->user->me()`

> UptimeRobot API v3 · `GET /user/me`

Get current user

## Signature

```php
public function me(): User
```

## Returns

`User`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->user->me();
```
