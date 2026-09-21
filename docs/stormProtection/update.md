# `$uptimeRobot->stormProtection->update()`

> UptimeRobot API v3 · `PATCH /monitors/storm-protection`

Update storm protection settings

Update the account-wide storm protection (alert grouping) settings. Partial update — only provided fields are changed.

## Signature

```php
public function update(StormProtectionUpdate $changes): StormProtectionSettings
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$changes` | `StormProtectionUpdate` | yes |  |

## Returns

`StormProtectionSettings`

## Example

```php
use GoSuccess\UptimeRobot\Model\StormProtectionUpdate;
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->stormProtection->update(changes: new StormProtectionUpdate(/* ... */));
```
