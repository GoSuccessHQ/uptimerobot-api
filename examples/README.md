# Examples

Runnable scripts that show the client in use. **They only read data**, so they
are safe to run against a production account; write operations are shown in
the [README](../README.md) and the [API reference](../docs/README.md).

```bash
composer install
UPTIMEROBOT_API_KEY=your-api-key php examples/monitors.php
```

They only send GET requests, so the read-only API key from **Integrations &
API** in the UptimeRobot dashboard should be enough; UptimeRobot documents it
for reading, but the scripts were run with the main API key.
`error-handling.php` sends requests the API rejects, including one with an
invalid key.

| Script | Shows |
| --- | --- |
| [monitors.php](monitors.php) | Monitors page by page and all at once, with their regions, alert contacts, group and last incident, and a status filter |
| [monitor-stats.php](monitor-stats.php) | The uptime of the account and of one monitor, and its response times, as a time series and by region |
| [incidents.php](incidents.php) | The incidents of the last 30 days, the root cause and the activity log of the newest one |
| [monitor-groups.php](monitor-groups.php) | The monitor groups with the monitors in each |
| [maintenance-windows.php](maintenance-windows.php) | The maintenance windows with their schedules |
| [status-pages.php](status-pages.php) | The status pages with their monitors, design and announcements |
| [alert-contacts.php](alert-contacts.php) | The personal alert contacts with the monitors that alert each of them |
| [integrations.php](integrations.php) | The integrations, with `--contacts` also the personal alert contacts |
| [account.php](account.php) | The plan, the alert contacts, tags and storm protection of the account, and the remaining request quota |
| [error-handling.php](error-handling.php) | Typed exceptions with the error codes and messages of the API, and arguments rejected before sending |

Each script makes a handful of requests. UptimeRobot allows only a few per
minute (20 on the Solo plan), counted for the whole account; when they are
used up, the client waits for the next window instead of failing, so a script
may pause for up to a minute.
