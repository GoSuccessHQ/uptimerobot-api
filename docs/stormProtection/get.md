# `$uptimeRobot->stormProtection->get()`

> UptimeRobot API v3 · `GET /monitors/storm-protection`

Get storm protection settings

Get the account-wide storm protection (alert grouping) settings. Returns defaults when not yet configured.

## Signature

```php
public function get(): StormProtectionSettings
```

## Returns

`StormProtectionSettings`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->stormProtection->get();
```
