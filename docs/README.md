# API Reference

One page per method of the UptimeRobot API v3 client `UptimeRobot`. See the [README](../README.md) for an introduction and [examples/](../examples/) for runnable scripts.

```php
$uptimeRobot = new UptimeRobot('your-api-key');
```

## `user`

The account the API key belongs to: its plan and its alert contacts.

- [`me()`](user/me.md) — Get current user
- [`alertContacts()`](user/alertContacts.md) — Get alert contacts
- [`allAlertContacts()`](user/allAlertContacts.md) — Get all alert contacts

## `tags`

Tags of monitors. They are created by naming them on a monitor.

- [`list()`](tags/list.md) — List user tags
- [`all()`](tags/all.md) — Iterate lazily over every item of list(), across all pages
- [`delete()`](tags/delete.md) — Delete a tag

## `stormProtection`

Storm protection: the account-wide grouping of alerts when many monitors go down at once.

- [`get()`](stormProtection/get.md) — Get storm protection settings
- [`update()`](stormProtection/update.md) — Update storm protection settings
