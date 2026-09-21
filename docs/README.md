# API Reference

One page per method of the UptimeRobot API v3 client `UptimeRobot`. See the [README](../README.md) for an introduction and [examples/](../examples/) for runnable scripts.

```php
$uptimeRobot = new UptimeRobot('your-api-key');
```

## `monitors`

Monitors: create, read, change, pause, start and delete them, and read their uptime and response time statistics.

- [`list()`](monitors/list.md) — List monitors
- [`all()`](monitors/all.md) — Iterate lazily over every item of list(), across all pages
- [`get()`](monitors/get.md) — Get a monitor by ID
- [`create()`](monitors/create.md) — Create a monitor
- [`update()`](monitors/update.md) — Update a monitor
- [`delete()`](monitors/delete.md) — Delete a monitor
- [`pause()`](monitors/pause.md) — Pause a monitor
- [`start()`](monitors/start.md) — Start a monitor
- [`reset()`](monitors/reset.md) — Reset stats for a monitor
- [`uptimeStats()`](monitors/uptimeStats.md) — Get the uptime statistics of all monitors
- [`uptime()`](monitors/uptime.md) — Get monitor uptime statistics
- [`responseTimeStats()`](monitors/responseTimeStats.md) — Get monitor response time statistics
- [`responseTimeStatsByRegion()`](monitors/responseTimeStatsByRegion.md) — Get monitor response time statistics by region

## `bulkMonitors`

Pause, start or change the monitors of a monitor group and/or with a tag at once.

- [`pause()`](bulkMonitors/pause.md) — Pause the monitors of a group and/or with a tag
- [`start()`](bulkMonitors/start.md) — Start the paused monitors of a group and/or with a tag
- [`update()`](bulkMonitors/update.md) — Change settings of the monitors of a group and/or with a tag

## `monitorGroups`

Monitor groups: named sets of monitors. A monitor is in one group at most; the monitors in none report the groupId 0.

- [`list()`](monitorGroups/list.md) — List monitor groups
- [`all()`](monitorGroups/all.md) — Iterate lazily over every item of list(), across all pages
- [`get()`](monitorGroups/get.md) — Get a monitor group by ID
- [`create()`](monitorGroups/create.md) — Create a monitor group
- [`update()`](monitorGroups/update.md) — Update a monitor group
- [`delete()`](monitorGroups/delete.md) — Delete a monitor group

## `maintenanceWindows`

Maintenance windows: one-time or recurring periods that suppress the alerts of the monitors assigned to them.

- [`list()`](maintenanceWindows/list.md) — List maintenance windows
- [`all()`](maintenanceWindows/all.md) — Iterate lazily over every item of list(), across all pages
- [`get()`](maintenanceWindows/get.md) — Get a maintenance window by ID
- [`create()`](maintenanceWindows/create.md) — Create a maintenance window
- [`update()`](maintenanceWindows/update.md) — Update a maintenance window
- [`delete()`](maintenanceWindows/delete.md) — Delete a maintenance window

## `incidents`

Incidents: the downtimes and slow responses of the monitors, with their root cause, activity log and the alerts sent. Incident IDs are strings of digits.

- [`list()`](incidents/list.md) — List incidents
- [`all()`](incidents/all.md) — Iterate lazily over every item of list(), across all pages
- [`get()`](incidents/get.md) — Get an incident by ID
- [`activityLog()`](incidents/activityLog.md) — Get incident activity log
- [`alerts()`](incidents/alerts.md) — Get incident sent alerts

## `incidentComments`

Comments on incidents, optionally published on the status page. Requires the plan feature incident-comments.

- [`list()`](incidentComments/list.md) — List incident comments
- [`all()`](incidentComments/all.md) — Iterate lazily over every item of list(), across all pages
- [`create()`](incidentComments/create.md) — Create incident comment
- [`update()`](incidentComments/update.md) — Update an incident comment
- [`delete()`](incidentComments/delete.md) — Delete incident comment

## `statusPages`

Public status pages: the monitors they show, their design and whether they are published.

- [`list()`](statusPages/list.md) — List PSPs
- [`all()`](statusPages/all.md) — Iterate lazily over every item of list(), across all pages
- [`get()`](statusPages/get.md) — Get a PSP by ID
- [`create()`](statusPages/create.md) — Create a status page
- [`update()`](statusPages/update.md) — Update a status page
- [`delete()`](statusPages/delete.md) — Delete a PSP

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
