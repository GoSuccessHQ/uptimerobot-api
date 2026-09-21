# UptimeRobot API – PHP Client

A modern, strongly-typed, **dependency-free** PHP client for the
[UptimeRobot API v3](https://uptimerobot.com/api/v3/), built for **PHP 8.4+**.

## Features

- **Fully typed**: `final readonly` models, native enums, a request model for
  each monitor type and each integration type, and generics-annotated
  pagination for every endpoint, checked with PHPStan at the highest level.
- **Complete**: all 61 operations of the specification, from monitors and their
  statistics to incidents, status pages, alert contacts and integrations.
- **Zero Composer dependencies**: a self-contained cURL transport (only
  `ext-curl` and `ext-json` are required), pluggable through a small interface.
- **Partial updates done right**: a field you leave out is not sent, a field you
  set to `null` is cleared.
- **Lazy pagination** across all pages with `all()`, or page by page with
  `list()`.
- **Rate-limit aware**: reads UptimeRobot's rate-limit headers, waits for the
  next window instead of running into a `429`, and retries a `429` after the
  announced time.
- **Safe retries**: server and network errors are only retried for idempotent
  requests, so a monitor is never created twice.
- **Typed exceptions** that normalize UptimeRobot's error formats, including
  the message lists of its validator and error codes such as `000-004`.
- **Generated from UptimeRobot's OpenAPI specification**, with the deviations
  between specification and reality corrected by hand, each documented with
  its evidence: a live check, a message of the API's validator or UptimeRobot's
  Terraform provider.
- **The API key stays out of** `var_dump()`, `print_r()` and stack traces.

## Supported resources

| Resource | Access | Checked against the live API |
| --- | --- | --- |
| [Monitors](#monitors) and their statistics | `$uptimeRobot->monitors` | Reading, filters and statistics; creating, changing, pausing, starting, resetting and deleting one monitor |
| [Bulk operations](#bulk-operations) | `$uptimeRobot->bulkMonitors` | No, tested with a mocked transport only |
| [Monitor groups](#monitor-groups) | `$uptimeRobot->monitorGroups` | Reading; writes only probed with unknown IDs and invalid bodies |
| [Maintenance windows](#maintenance-windows) | `$uptimeRobot->maintenanceWindows` | Validation and errors only; the account had none |
| [Incidents](#incidents) | `$uptimeRobot->incidents` | Reading |
| [Incident comments](#incident-comments) | `$uptimeRobot->incidentComments` | The plan check only; the plan lacked the feature |
| [Status pages](#status-pages) | `$uptimeRobot->statusPages` | Validation and errors only; the account had none |
| [Announcements](#announcements) | `$uptimeRobot->announcements` | The plan check only; the plan lacked the feature |
| [Alert contacts](#alert-contacts) | `$uptimeRobot->alertContacts` | Reading; writes only probed with unknown IDs and invalid bodies |
| [Integrations](#integrations) | `$uptimeRobot->integrations` | Listing and errors; the account had none |
| [Tags](#tags) | `$uptimeRobot->tags` | Listing; the account had none |
| [Account](#account) | `$uptimeRobot->user` | Reading |
| [Storm protection](#storm-protection) | `$uptimeRobot->stormProtection` | Reading |

The checks ran against a production account on the Solo plan; the only
writes went to a test monitor created and deleted for the purpose. Everything
else follows the specification, read together with UptimeRobot's official
[Terraform provider](https://github.com/uptimerobot/terraform-provider-uptimerobot)
where the specification is vague. The notes of the methods in the
[API reference](docs/README.md) say what was verified live.

## Requirements

- PHP **8.4** or higher
- `ext-curl` and `ext-json`

## Installation

```bash
composer require gosuccess/uptimerobot-api
```

## Quick start

```php
use GoSuccess\UptimeRobot\Enum\MonitorStatus;
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

// Iterate over all monitors; pages are fetched lazily.
foreach ($uptimeRobot->monitors->all() as $monitor) {
    echo $monitor->friendlyName, ': ', $monitor->status->value ?? 'unknown', PHP_EOL;
}

// Only the monitors that are down.
foreach ($uptimeRobot->monitors->all(status: [MonitorStatus::Down, MonitorStatus::LooksDown]) as $monitor) {
    echo $monitor->friendlyName, ' is down since ', $monitor->currentStateDuration, ' s', PHP_EOL;
}

// Pause a monitor during a deployment, then start it again.
$uptimeRobot->monitors->pause(123456789);
$uptimeRobot->monitors->start(123456789);
```

`UptimeRobot` creates each resource on first access. They share one
connection, so the rate-limit handling covers all of them.

The API keys are in the [UptimeRobot dashboard](https://dashboard.uptimerobot.com/)
under **Integrations & API**. The **Main API Key** can do everything. According
to UptimeRobot, the **Read-only API Key** only reads and cannot create, update
or pause monitors; this client was checked with a main key.

## Monitors

A monitor checks a URL, host or port at an interval from one or more regions,
or waits for heartbeats, and alerts the contacts assigned to it. See the
[API reference](docs/README.md#monitors) for every method.

### Creating monitors

Each monitor type has its own request model, which sends the type for you and
requires what the type requires:

| Type | Model | Required besides `friendlyName` and `interval` |
| --- | --- | --- |
| HTTP(S) | `HttpMonitorCreate` | `url`, `timeout` |
| Keyword | `KeywordMonitorCreate` | `url`, `timeout`, `keywordType`, `keywordCaseType`, `keywordValue` |
| Ping | `PingMonitorCreate` | `url` (host name or IP address), `timeout` |
| Port | `PortMonitorCreate` | `url`, `port`, `timeout` |
| Heartbeat | `HeartbeatMonitorCreate` | – |
| DNS | `DnsMonitorCreate` | `url`, `config` with the expected records |
| API | `ApiMonitorCreate` | `url`, `timeout`, `config` with the assertions |
| UDP | `UdpMonitorCreate` | `url`, `port`, `timeout`, `config` with the packet loss threshold |
| Visual comparison | `VisualComparisonMonitorCreate` | `url`, `config` with sensitivity and viewport |

```php
use GoSuccess\UptimeRobot\Enum\HttpMethod;
use GoSuccess\UptimeRobot\Enum\Region;
use GoSuccess\UptimeRobot\Model\AssignedAlertContact;
use GoSuccess\UptimeRobot\Model\HttpMonitorConfig;
use GoSuccess\UptimeRobot\Model\HttpMonitorCreate;
use GoSuccess\UptimeRobot\Model\RegionalData;

$monitor = $uptimeRobot->monitors->create(new HttpMonitorCreate(
    friendlyName: 'Website',
    interval: 300,  // seconds between two checks
    url: 'https://example.com',
    timeout: 30,    // seconds
    assignedAlertContacts: [
        // Alert at once, without repeating the alert (delays in minutes).
        new AssignedAlertContact(alertContactId: 1234567, threshold: 0, recurrence: 0),
    ],
    tagNames: ['production'],
    regionData: new RegionalData(region: [Region::Europe, Region::NorthAmerica]),
    httpMethodType: HttpMethod::Get,
    config: new HttpMonitorConfig(sslExpirationPeriodDays: [7, 14]),
));

// STARTED until the first check.
echo $monitor->id, ': ', $monitor->status->value ?? '?', PHP_EOL;
```

The other types work the same way:

```php
use GoSuccess\UptimeRobot\Enum\AssertionComparison;
use GoSuccess\UptimeRobot\Enum\AssertionLogic;
use GoSuccess\UptimeRobot\Enum\KeywordCaseType;
use GoSuccess\UptimeRobot\Enum\KeywordType;
use GoSuccess\UptimeRobot\Enum\VisualComparisonViewport;
use GoSuccess\UptimeRobot\Model\ApiAssertionCheck;
use GoSuccess\UptimeRobot\Model\ApiAssertions;
use GoSuccess\UptimeRobot\Model\ApiMonitorConfig;
use GoSuccess\UptimeRobot\Model\ApiMonitorCreate;
use GoSuccess\UptimeRobot\Model\DnsMonitorConfig;
use GoSuccess\UptimeRobot\Model\DnsMonitorCreate;
use GoSuccess\UptimeRobot\Model\DnsRecords;
use GoSuccess\UptimeRobot\Model\HeartbeatMonitorCreate;
use GoSuccess\UptimeRobot\Model\KeywordMonitorCreate;
use GoSuccess\UptimeRobot\Model\PingMonitorCreate;
use GoSuccess\UptimeRobot\Model\PortMonitorCreate;
use GoSuccess\UptimeRobot\Model\UdpMonitorConfig;
use GoSuccess\UptimeRobot\Model\UdpMonitorCreate;
use GoSuccess\UptimeRobot\Model\UdpSettings;
use GoSuccess\UptimeRobot\Model\VisualComparisonMonitorConfig;
use GoSuccess\UptimeRobot\Model\VisualComparisonMonitorCreate;
use GoSuccess\UptimeRobot\Model\VisualComparisonSettings;

// Alert when the page no longer contains "Welcome".
$uptimeRobot->monitors->create(new KeywordMonitorCreate(
    friendlyName: 'Shop',
    interval: 300,
    url: 'https://shop.example.com',
    timeout: 30,
    keywordType: KeywordType::AlertNotExists,
    keywordCaseType: KeywordCaseType::CaseInsensitive,
    keywordValue: 'Welcome',
));

$uptimeRobot->monitors->create(new PingMonitorCreate(friendlyName: 'Router', interval: 300, url: '203.0.113.1', timeout: 30));
$uptimeRobot->monitors->create(new PortMonitorCreate(friendlyName: 'Mail', interval: 300, url: 'mail.example.com', port: 25, timeout: 30));

// Expects a heartbeat at least once a day, with an hour of grace.
$heartbeat = $uptimeRobot->monitors->create(new HeartbeatMonitorCreate(friendlyName: 'Nightly backup', interval: 86400, gracePeriod: 3600));

$uptimeRobot->monitors->create(new DnsMonitorCreate(
    friendlyName: 'DNS',
    interval: 300,
    url: 'example.com',
    config: new DnsMonitorConfig(dnsRecords: new DnsRecords(a: ['203.0.113.10'], ns: ['ns1.example.com.'])),
));

// Between one and five assertions on the response.
$uptimeRobot->monitors->create(new ApiMonitorCreate(
    friendlyName: 'Health endpoint',
    interval: 60,
    url: 'https://api.example.com/health',
    timeout: 30,
    config: new ApiMonitorConfig(apiAssertions: new ApiAssertions(
        logic: AssertionLogic::And,
        checks: [
            new ApiAssertionCheck(property: 'status_code', comparison: AssertionComparison::Equals, target: 200),
            new ApiAssertionCheck(property: '$.status', comparison: AssertionComparison::Equals, target: 'ok'),
        ],
    )),
));

$uptimeRobot->monitors->create(new UdpMonitorCreate(
    friendlyName: 'Game server',
    interval: 300,
    url: 'game.example.com',
    port: 27015,
    timeout: 30,
    config: new UdpMonitorConfig(udp: new UdpSettings(payload: 'ping', packetLossThreshold: 50.0)),  // percent
));

$uptimeRobot->monitors->create(new VisualComparisonMonitorCreate(
    friendlyName: 'Landing page',
    interval: 3600,
    url: 'https://example.com',
    config: new VisualComparisonMonitorConfig(visualComparison: new VisualComparisonSettings(
        sensitivityThreshold: 10,  // 0 to 100
        viewport: VisualComparisonViewport::Desktop,
    )),
));
```

A new monitor reports the status `STARTED` until its first check and, unless
they were set, the `authType` `HTTP_BASIC` with empty credentials and the
`httpMethodType` `null`, whatever its type. A heartbeat monitor reports the
token of its heartbeat URL as `url`. Monitors of the types HTTP, keyword, ping,
port, heartbeat, DNS and API were created on the live API during development,
the keyword monitor through this client and the others with requests of the
same shape; UDP and visual comparison monitors follow the specification.

### Reading and filtering

```php
use GoSuccess\UptimeRobot\Enum\MonitorStatus;

$monitor = $uptimeRobot->monitors->get(123456789);

// Filters combine with AND; a list of statuses matches any of them.
foreach ($uptimeRobot->monitors->all(status: [MonitorStatus::Up, MonitorStatus::Paused], groupId: 29454) as $monitor) {
    echo $monitor->friendlyName, PHP_EOL;
}

// "key:value" custom fields, all of which must match, and part of the name or URL, ignoring case.
$shop = $uptimeRobot->monitors->all(customField: ['env:production'], name: 'shop', url: 'example.com');
```

`groupId: 0` selects the monitors in no group. The filters were verified live;
the tag filter only with tags that match nothing, because the account had no
tags.

### Updating monitors

```php
use GoSuccess\UptimeRobot\Enum\IpVersion;
use GoSuccess\UptimeRobot\Model\MonitorConfigUpdate;
use GoSuccess\UptimeRobot\Model\MonitorUpdate;

// Only what you pass is sent; everything else keeps its value.
$uptimeRobot->monitors->update($monitor->id, new MonitorUpdate(
    interval: 60,
    customHttpHeaders: ['X-Checked-By' => 'UptimeRobot'],        // replaces all headers
    config: new MonitorConfigUpdate(ipVersion: IpVersion::Ipv4Only),  // merged into the current settings
));

// Remove one setting of the config, or the whole config.
$uptimeRobot->monitors->update($monitor->id, new MonitorUpdate(config: new MonitorConfigUpdate(ipVersion: null)));
$uptimeRobot->monitors->update($monitor->id, new MonitorUpdate(config: null));
```

What an update does with the current values depends on the field (verified
live):

- `config` is merged key by key: a key you leave out is kept, a key set to
  `null` is removed, and `config: null` clears them all. `apiAssertions`
  inside it is replaced as a whole.
- `customHttpHeaders` and `customFields` replace the whole map; `[]` removes
  every entry.
- `successHttpResponseCodes` replaces the list; `[]` resets it to
  `["2xx", "3xx"]`.
- `responseTimeThreshold` goes into the thresholds of the regions that have
  one (`$monitor->regionalData->threshold`); the property itself always reads
  `0`.

Whether `assignedAlertContacts`, `tagNames` and `maintenanceWindowsIds` replace
or extend the current ones was not verified.

### Pausing, starting, resetting and deleting

```php
$paused = $uptimeRobot->monitors->pause($monitor->id);    // status PAUSED
$started = $uptimeRobot->monitors->start($monitor->id);   // status STARTED until the next check
$uptimeRobot->monitors->reset($monitor->id);              // resets the statistics, incl. those of incidents and alerts
$uptimeRobot->monitors->delete($monitor->id);
```

All four were verified live.

### Statistics

```php
use GoSuccess\UptimeRobot\Enum\ResponseTimeRegion;
use GoSuccess\UptimeRobot\Enum\UptimeTimeFrame;

// All monitors of the account; overallUptime is a fraction from 0 to 1.
$account = $uptimeRobot->monitors->uptimeStats(UptimeTimeFrame::Days30, logLimit: 10);
printf("%.3f %% uptime, %d incidents\n", $account->overallUptime * 100, $account->totalIncidents);

foreach ($account->logs as $downtime) {
    echo $downtime->datetime?->format('Y-m-d H:i'), ' down for ', $downtime->duration ?? 0, ' s', PHP_EOL;
}

// A period of your own takes Custom and both ends.
$lastWeek = $uptimeRobot->monitors->uptimeStats(
    UptimeTimeFrame::Custom,
    start: new DateTimeImmutable('-7 days'),
    end: new DateTimeImmutable(),
);

// One monitor; uptime is a percentage from 0 to 100.
$uptime = $uptimeRobot->monitors->uptime($monitor->id, from: new DateTimeImmutable('-30 days'), to: new DateTimeImmutable());
printf("%.3f %% uptime, %d s down, %d incidents\n", $uptime->uptime, $uptime->totalDowntimeSeconds, $uptime->incidentCount);

// Response times in milliseconds, of the last 24 hours without from and to.
$responseTimes = $uptimeRobot->monitors->responseTimeStats($monitor->id, includeTimeSeries: true, region: ResponseTimeRegion::Europe);
echo $responseTimes->summary->avg ?? '-', ' ms on average', PHP_EOL;

foreach ($responseTimes->timeSeries as $point) {
    echo $point->timestamp?->format('H:i'), ' ', $point->value, ' ms', PHP_EOL;
}

// Every region at once; the regions the monitor is not checked from are null.
$byRegion = $uptimeRobot->monitors->responseTimeStatsByRegion($monitor->id);
echo $byRegion->eu->summary->avg ?? 'not checked from Europe', PHP_EOL;
```

The units, verified live:

- `uptimeStats()->overallUptime` is a **fraction** from 0 to 1, while
  `uptime()->uptime` is a **percentage** from 0 to 100. The specification
  documents neither.
- Durations (`duration`, `totalDowntimeSeconds`, `totalTimeWithoutIncidents`,
  `mtbf`) are in seconds, response times in milliseconds. `mtbf` is `null`
  without incidents.
- `uptimeStats()` sends `start` and `end` as Unix seconds and needs both with
  `UptimeTimeFrame::Custom`, `start` before `end`. The API ignores them for the
  other time frames, so the client rejects them there with an
  `InvalidArgumentException`.
- `uptime()` and the response time methods cover the last 24 hours by default
  and at most 90 days. `uptime()` accepts `from` alone but not `to` alone; the
  response time methods need both or neither.
- The time series is only included with `includeTimeSeries: true`. The spacing
  of its points is not documented and varied: 1, 5 and 30 minutes were seen.

## Bulk operations

Pause, start or change every monitor of a group, with a tag, or both (then the
monitors of the group with the tag):

```php
use GoSuccess\UptimeRobot\Model\BulkMonitorUpdate;

$result = $uptimeRobot->bulkMonitors->pause(groupId: 29454);
$uptimeRobot->bulkMonitors->start(groupId: 29454);

$result = $uptimeRobot->bulkMonitors->update(new BulkMonitorUpdate(interval: 300, sslExpirationReminder: true), tagId: 42);

echo $result->totalSuccess, ' changed, ', $result->totalError, ' failed', PHP_EOL;

foreach ($result->results as $item) {
    if ($item->error !== null) {
        echo $item->monitorName, ': ', $item->error, PHP_EOL;
    }
}
```

`groupId: 0` selects the monitors in no group. Without a group or a tag, or
with an update that changes nothing, an `InvalidArgumentException` is thrown
before anything is sent. A monitor that fails is reported in the result, not as
an exception. The bulk operations were **not** run against the live API, as
they change many monitors at once; they follow the specification.

## Monitor groups

```php
use GoSuccess\UptimeRobot\Model\MonitorGroupCreate;
use GoSuccess\UptimeRobot\Model\MonitorGroupUpdate;

$group = $uptimeRobot->monitorGroups->create(new MonitorGroupCreate(name: 'Shop', monitorIds: [123456789, 987654321]));
$uptimeRobot->monitorGroups->update($group->id, new MonitorGroupUpdate(name: 'Shop (production)'));

// A group lists neither its monitors nor their number; every monitor names its group.
foreach ($uptimeRobot->monitors->all(groupId: $group->id) as $monitor) {
    echo $monitor->friendlyName, PHP_EOL;
}

// The monitors of a deleted group move to another group, or to none without it.
$uptimeRobot->monitorGroups->delete($group->id, monitorsNewGroupId: 29454);
```

A monitor is in one group at most, so adding it to a group takes it out of its
previous one; `$monitor->groupId` is `0` for none. Reading was verified live;
creating, changing and deleting groups follow the specification.

## Maintenance windows

Maintenance windows suppress the alerts of their monitors, once or on a
schedule:

```php
use GoSuccess\UptimeRobot\Enum\MaintenanceWindowInterval;
use GoSuccess\UptimeRobot\Enum\MaintenanceWindowStatus;
use GoSuccess\UptimeRobot\Model\MaintenanceWindowCreate;
use GoSuccess\UptimeRobot\Model\MaintenanceWindowUpdate;

// Every Sunday at 02:00 for 90 minutes.
$window = $uptimeRobot->maintenanceWindows->create(new MaintenanceWindowCreate(
    name: 'Weekly updates',
    interval: MaintenanceWindowInterval::Weekly,
    time: '02:00:00',
    duration: 90,  // minutes
    days: [7],     // 1 = Monday to 7 = Sunday
    monitorIds: [123456789],
));

// Once, on a given day, for every monitor.
$uptimeRobot->maintenanceWindows->create(new MaintenanceWindowCreate(
    name: 'Database migration',
    interval: MaintenanceWindowInterval::Once,
    time: '22:00:00',
    duration: 120,
    date: '2026-10-03',
    autoAddMonitors: true,
));

// A paused window no longer suppresses alerts.
$uptimeRobot->maintenanceWindows->update($window->id, new MaintenanceWindowUpdate(status: MaintenanceWindowStatus::Paused));
```

The account had no maintenance windows and none could be created, so only the
API's validation and error responses were checked live, for example that the
validator accepts a window without a `date`. The rest follows the specification
and the Terraform provider: the weekday numbering (the specification's example
would also fit 0 = Sunday), monthly `days` from 1 to 31 or -1 for the last day,
and, presumably, that a one-time window needs a `date`. Neither documents the
time zone of `date` and `time`.

## Incidents

```php
$incidents = $uptimeRobot->incidents->all(monitorId: 123456789, startedAfter: new DateTimeImmutable('-30 days'));

foreach ($incidents as $incident) {
    printf(
        "%s %s: %s, %s\n",
        $incident->startedAt?->format('Y-m-d H:i') ?? '?',
        $incident->type,
        $incident->reason,
        $incident->duration === null ? 'ongoing' : "{$incident->duration} s",
    );
}

// Incident IDs are strings of digits.
$incident = $uptimeRobot->incidents->get('358532761126055015');
echo $incident->rootCause?->url, ' answered ', $incident->rootCause->httpResponseCode ?? 'nothing', PHP_EOL;
```

The list is sorted newest first; `monitorName` matches part of the name,
ignoring case. The items of the list carry the `type` (e.g. `Downtime`,
`SlowResponse`) and the monitor, `get()` carries neither but adds the root
cause. `cause` is the HTTP status of an HTTP error, `333333` for a connection
timeout, `444444` for no response and `0` for a slow response (verified
live); the specification documents none of them.

### Activity log and alerts

```php
use GoSuccess\UptimeRobot\Model\NotificationActivity;
use GoSuccess\UptimeRobot\Model\StatusUpdateActivity;

foreach ($uptimeRobot->incidents->activityLog($incident->id) as $entry) {
    // Every entry has a date and a region; the rest depends on its type.
    $what = match (true) {
        $entry instanceof StatusUpdateActivity => "{$entry->alertLogType}: {$entry->reason}",
        $entry instanceof NotificationActivity => "{$entry->notificationType} alert to {$entry->sentToFullName}",
        default => $entry::class,
    };

    echo $entry->date?->format('H:i:s'), ' ', $entry->region->value ?? '-', ' ', $what, PHP_EOL;
}

foreach ($uptimeRobot->incidents->alerts($incident->id) as $alert) {
    echo $alert->channelType, ' to ', $alert->recipientName, ': ', $alert->status->value ?? '?', PHP_EOL;
}
```

The activity log comes newest first, the alerts oldest first. Entries are
`StatusUpdateActivity`, `NotificationActivity` or `CommentActivity`; an entry
of a type the client does not know becomes an `UnknownActivityLogEntry` with
the raw data, so a new type never breaks the log. Only the first page of the
log is available: the API ignores its cursor (verified live; no log had more
than 6 entries).

All of this was verified live with the 14 resolved incidents of the account.
Unresolved incidents, comment entries and the assertion diagnostics of API
monitors were not observable.

### Incident comments

```php
use GoSuccess\UptimeRobot\Model\IncidentCommentCreate;

$created = $uptimeRobot->incidentComments->create($incident->id, new IncidentCommentCreate(
    content: 'The database failed over; we are watching it.',
    publishOnStatusPage: true,
));

foreach ($uptimeRobot->incidentComments->all($incident->id) as $comment) {
    echo $comment->user->fullName, ': ', $comment->comment, PHP_EOL;
}
```

Comments need the plan feature `incident-comments`. Without it every call
raises a `ForbiddenException` with the code `000-003`, which is all that could
be verified live. `create()` returns the comment if the API sends it back and
`null` otherwise, since the specification documents no response.

## Status pages

```php
use GoSuccess\UptimeRobot\Enum\StatusPageStatus;
use GoSuccess\UptimeRobot\Enum\StatusPageTheme;
use GoSuccess\UptimeRobot\Http\FileUpload;
use GoSuccess\UptimeRobot\Model\StatusPageCreate;
use GoSuccess\UptimeRobot\Model\StatusPageCustomSettingsInput;
use GoSuccess\UptimeRobot\Model\StatusPageCustomSettingsPageInput;
use GoSuccess\UptimeRobot\Model\StatusPageUpdate;

$page = $uptimeRobot->statusPages->create(
    new StatusPageCreate(
        friendlyName: 'Example status',
        monitorIds: [0],  // [0] alone: every monitor of the account
        status: StatusPageStatus::Enabled,
        customSettings: new StatusPageCustomSettingsInput(page: new StatusPageCustomSettingsPageInput(theme: StatusPageTheme::Dark)),
    ),
    logo: FileUpload::fromPath(__DIR__ . '/logo.png'),
);

// Replace only the icon.
$uptimeRobot->statusPages->update($page->id, new StatusPageUpdate(), icon: FileUpload::fromPath(__DIR__ . '/icon.png'));

echo $page->urlKey, $page->isPasswordSet ? ' (password protected)' : '', PHP_EOL;
```

Without a file, a status page is sent as JSON. With a logo or an icon, the
request is `multipart/form-data`, the design goes along as nested form fields
such as `customSettings[page][theme]`, and a value that a form cannot express
(`null` or an empty object) is rejected with an `InvalidArgumentException`
before anything is sent. According to the specification, logo and icon must be
JPG or PNG files of at most 150 KB, 20 to 400 pixels wide and 10 to 200 pixels
high. `FileUpload::fromPath()` guesses the content type from the extension;
`new FileUpload('logo.png', $bytes, 'image/png')` takes the bytes directly.

The account had no status pages and none could be created, so only the API's
validator was checked live, with JSON and with form data. It rejects the
design values of the specification (`Light`, `LogoOnLeft`, `Compact`) and
takes lower-case ones (`light`, `logo_on_left`, `compact`), which the enums
use. The response follows the specification and the Terraform provider.

## Announcements

Announcements are notices on a status page, which are also delivered to the
subscribers of the page:

```php
use GoSuccess\UptimeRobot\Enum\AnnouncementStatus;
use GoSuccess\UptimeRobot\Enum\AnnouncementStatusFilter;
use GoSuccess\UptimeRobot\Enum\AnnouncementType;
use GoSuccess\UptimeRobot\Model\AnnouncementCreate;

$announcement = $uptimeRobot->announcements->create($page->id, new AnnouncementCreate(
    title: 'Scheduled maintenance',
    content: 'The shop is offline on Saturday from 22:00 to 23:00 UTC.',
    status: AnnouncementStatus::Published,
    type: AnnouncementType::Maintenance,
    startDate: new DateTimeImmutable('2026-10-03 22:00 UTC'),
    endDate: new DateTimeImmutable('2026-10-03 23:00 UTC'),
));

$uptimeRobot->announcements->pin($page->id, $announcement->id);

foreach ($uptimeRobot->announcements->all($page->id, status: AnnouncementStatusFilter::Published) as $announcement) {
    echo $announcement->title, ' (', $announcement->type, ')', PHP_EOL;
}
```

The list filter takes upper-case values (`AnnouncementStatusFilter`), the
requests title-case ones (`AnnouncementStatus`), as the specification says.
Announcements need the plan feature `psp-subscribers`. Without it every call
raises a `ForbiddenException` with the code `000-003`, which is all that could
be verified live.

## Alert contacts

Personal alert contacts are the e-mail addresses, phone numbers and mobile app
devices of the account; team channels such as Slack are
[integrations](#integrations).

```php
use GoSuccess\UptimeRobot\Enum\AlertContactType;
use GoSuccess\UptimeRobot\Enum\NotificationEvent;
use GoSuccess\UptimeRobot\Model\AlertContactCreate;
use GoSuccess\UptimeRobot\Model\AlertContactUpdate;

$contact = $uptimeRobot->alertContacts->create(new AlertContactCreate(
    type: AlertContactType::Email,
    friendlyName: 'On-call',
    value: 'oncall@example.com',
    enableNotificationsFor: NotificationEvent::Down,
));

// Pause the contact, so that it receives no alerts.
$uptimeRobot->alertContacts->update($contact->id, new AlertContactUpdate(isActive: false));

foreach ($uptimeRobot->alertContacts->all() as $contact) {
    echo $contact->type, ' ', $contact->friendlyName ?? $contact->value, ' (', $contact->status, ')', PHP_EOL;
}
```

E-mail and mobile app contacts can be created here; text message and voice
contacts need a phone number verified in the dashboard. Monitors assign
contacts by ID with an `AssignedAlertContact`. The validator of `create()` did
not object to an invalid `enableNotificationsFor`, and the Terraform provider
sets it again with `update()` right after creating a contact; whether
`create()` applies it was not verified.

The contacts appear in four lists, and on the test account each listed
different ones (verified live): `alertContacts->all()` left out the mobile app
contacts awaiting migration (status `ToMigrate`), `user->alertContacts()`
included them but left out two older phone contacts, `user->allAlertContacts()`
listed four of the eight, grouped by user (according to the specification it
also covers notify-only contacts and organization members), and
`integrations->all(includeOrgMembers: true)` listed all eight. Reading was
verified live; creating, changing and deleting contacts only against the
validator, with bodies it rejects and unknown IDs.

## Integrations

Integrations alert team channels: Slack, Telegram, Microsoft Teams, webhooks,
Zapier, PagerDuty, Google Chat, Discord, Splunk, Pushbullet, Pushover and
Mattermost. Each has a create and an update model, which send the type for you:

```php
use GoSuccess\UptimeRobot\Enum\NotificationEvent;
use GoSuccess\UptimeRobot\Model\SlackIntegrationCreate;
use GoSuccess\UptimeRobot\Model\SlackIntegrationUpdate;
use GoSuccess\UptimeRobot\Model\WebhookIntegrationCreate;

$slack = $uptimeRobot->integrations->create(new SlackIntegrationCreate(
    webhookUrl: 'https://hooks.slack.com/services/T000/B000/XXXX',
    customValue: '#alerts',  // the channel; '' for none
    friendlyName: 'Ops channel',
    enableNotificationsFor: NotificationEvent::UpAndDown,
));

$uptimeRobot->integrations->create(new WebhookIntegrationCreate(
    urlToNotify: 'https://example.com/uptimerobot-webhook',
    postValue: '{"message": "Alert: $monitorURL is $alertType"}',
    sendAsJson: true,
));

$uptimeRobot->integrations->update($slack->id, new SlackIntegrationUpdate(customValue: '#ops'));
```

Monitors assign integrations by ID, like personal contacts. An update must
name the type, although the specification makes it optional (verified live);
the update models always send it. A type the plan lacks raises a
`ForbiddenException` with the code `021-003` (verified live for PagerDuty on
the Solo plan). The account had no integrations, so their settings and
responses follow the specification and the Terraform provider.

## Tags

```php
foreach ($uptimeRobot->tags->all() as $tag) {
    echo $tag->id, ' ', $tag->name, PHP_EOL;
}

// Also removes the tag from every monitor.
$uptimeRobot->tags->delete(42);
```

Tags are created by naming them on a monitor (`tagNames`). The account had no
tags, so only the empty list was verified live.

## Account

```php
$user = $uptimeRobot->user->me();

printf(
    "%s, %s plan: %d of %d monitors\n",
    $user->email,
    $user->activeSubscription->plan,
    $user->monitorsCount,
    $user->monitorLimit,
);

$contacts = $uptimeRobot->user->alertContacts();
$byUser = $uptimeRobot->user->allAlertContacts();  // grouped by user
```

Verified live. See [Alert contacts](#alert-contacts) for how the lists of
contacts differ.

## Storm protection

Storm protection groups the alerts when many monitors go down at once. The
setting applies to the whole account:

```php
use GoSuccess\UptimeRobot\Enum\StormProtectionThresholdType;
use GoSuccess\UptimeRobot\Model\StormProtectionUpdate;

$settings = $uptimeRobot->stormProtection->get();

// Group the alerts at a threshold of 10 percent within a rolling 5-minute window.
$uptimeRobot->stormProtection->update(new StormProtectionUpdate(
    isEnabled: true,
    thresholdType: StormProtectionThresholdType::Percentage,
    thresholdValue: 10,
    windowMinutes: 5,
));
```

Reading was verified live; `update()` was not called, as it would change the
account.

## Partial updates and clearing fields

Request models serialize **only what you pass**, so an update touches nothing
else. A field you leave out and a field you set to `null` therefore mean two
different things:

```php
use GoSuccess\UptimeRobot\Model\MonitorUpdate;

// Only changes the name; everything else keeps its value.
$uptimeRobot->monitors->update($monitor->id, new MonitorUpdate(friendlyName: 'Shop'));

// Sends an explicit null, which clears the HTTP method.
$uptimeRobot->monitors->update($monitor->id, new MonitorUpdate(httpMethodType: null));
```

A field only takes `null` where the API accepts it, such as the monitor's
`config`, the keys of `MonitorConfigUpdate` and `httpMethodType`. An empty list
or map is sent as it is; what it means depends on the field (see
[Updating monitors](#updating-monitors)).

To make a field conditional, pass the `Undefined` sentinel, which is the default
of every optional field:

```php
use GoSuccess\UptimeRobot\Model\MonitorUpdate;
use GoSuccess\UptimeRobot\Model\Undefined;

$newUrl = getenv('SHOP_URL') ?: null;

// The URL is only sent if there is a new one.
$uptimeRobot->monitors->update($monitor->id, new MonitorUpdate(
    friendlyName: 'Shop',
    url: $newUrl ?? Undefined::Value,
));
```

## Pagination

List endpoints come in pairs: `list()` returns one `Page`, `all()` returns a lazy
`Paginator` over every item of every page.

```php
$page = $uptimeRobot->monitors->list(limit: 50);

foreach ($page->items as $monitor) {
    // ...
}

if ($page->hasMore) {
    // The cursor of the next page is the ID of the last item on this one.
    $page = $uptimeRobot->monitors->list(cursor: (int) $page->next, limit: 50);
}

// Every monitor, fetching pages only while the loop runs:
foreach ($uptimeRobot->monitors->all(name: 'shop') as $monitor) {
    // ...
}
```

The cursor is an `int`, and a string of digits for incidents. `all()` asks for
200 monitors per page, the most the API allows, and 100 comments, the most the
specification allows; the other lists have no page size. Breaking out of the
loop early saves the remaining requests. The monitor list reports a next page
after every full page, even when nothing follows, so the last request of
`all()` may return an empty page (verified live). The activity log, the alerts
of an incident and the contacts of `user` are plain lists.

## Error handling

Every exception of a failed request implements
`GoSuccess\UptimeRobot\Exception\UptimeRobotException`. Error responses become
typed exceptions:

| Status | Exception |
| --- | --- |
| 400 | `BadRequestException` |
| 401 | `AuthenticationException` |
| 403 | `ForbiddenException` |
| 404 | `NotFoundException` |
| 409 | `ConflictException` |
| 422 | `ValidationException` |
| 429 | `RateLimitException` (with `$retryAfter`) |
| 5xx | `ServerException` |
| other | `ApiException` |

All of them extend `ApiException`, which carries `$statusCode`, `$errorCode`,
the raw `$responseBody` and `decodedBody()`. The message names the request
without its query string and the API's message, e.g.
`GET https://api.uptimerobot.com/v3/monitors/999999999 failed with HTTP 404: Monitor not found (000-004)`.
Network failures throw a `TransportException`, unexpected response bodies a
`SerializationException`. Arguments the API would reject or silently ignore
raise PHP's own `InvalidArgumentException` before anything is sent, and
`FileUpload::fromPath()` a `RuntimeException` for a file it cannot read.

```php
use GoSuccess\UptimeRobot\Exception\ForbiddenException;
use GoSuccess\UptimeRobot\Exception\NotFoundException;

try {
    $comments = $uptimeRobot->incidentComments->list('358532761126055015');
} catch (ForbiddenException $e) {
    if ($e->errorCode === '000-003') {
        echo 'The plan lacks incident comments.', PHP_EOL;
    }
} catch (NotFoundException $e) {
    echo $e->getMessage(), PHP_EOL;
}
```

UptimeRobot does not document its error codes. These were seen live:

| Code | Status | Meaning |
| --- | --- | --- |
| `000-003` | 403 | The plan lacks the feature, e.g. incident comments or announcements |
| `000-004` | 404 | Not found |
| `000-006` | 403 | The resource belongs to another account |
| `003-005` | 401 | The API key is invalid or missing |
| `021-003` | 403 | The plan lacks the integration type |
| `021-005` | 404 | No integration with this ID, e.g. the ID of a personal alert contact |

A `400` from the validator carries no code but a list of messages, which the
exception message joins with `; ` and `decodedBody()['message']` returns as
they came. The body of a `429` is plain text:
`ThrottlerException: Too Many Requests`.

## Rate limits

UptimeRobot limits the requests per minute by plan: its documentation names 10
for the free plan and twice the monitor limit, at most 5000, for paid plans.
The Solo plan with 10 monitors allowed 20. Observed live:

- The limit applies to a **fixed 60-second window** that starts with the first
  request counted in it, and to **all clients of the account together**:
  requests of another client reduced the remaining quota.
- `x-ratelimit-reset` counts the **seconds until the window ends**, not a Unix
  time as the documentation says. `Retry-After` on a `429` gave the full 60
  seconds, although only 58 of the window were left.
- Requests the API rejects before its throttler, such as those with an invalid
  key, a malformed body or no content type, get no rate-limit headers. A
  malformed body and a missing content type did not count against the quota.

The client handles this for you. Once a response reports that no requests
remain, it holds the next request back until the window ends instead of running
into a `429`, and it retries a `429` after the announced time. Both waits are
capped by `ClientOptions::$maxRetryDelay`, and `awaitRateLimitReset: false`
turns off the first. The status of the last response is at hand:

```php
$uptimeRobot->user->me();

$quota = $uptimeRobot->rateLimit;  // null before the first response with the headers

if ($quota !== null) {
    printf("%d of %d requests left for %.0f s\n", $quota->remaining, $quota->limit, $quota->secondsUntilReset(microtime(true)));
}
```

To spread the requests of one process evenly, or to leave room for other
clients of the account, pass a rate limiter, e.g. the built-in sliding window,
or your own implementation of `RateLimiter` (for example backed by Redis to
share it across processes):

```php
use GoSuccess\UptimeRobot\RateLimit\RateLimiter;
use GoSuccess\UptimeRobot\RateLimit\SlidingWindowRateLimiter;
use GoSuccess\UptimeRobot\UptimeRobot;

// At most 15 requests in any 60 seconds.
$uptimeRobot = new UptimeRobot('your-api-key', rateLimiter: new SlidingWindowRateLimiter(maxRequests: 15));

final class SharedRateLimiter implements RateLimiter
{
    public function acquire(): void
    {
        // Block until the shared counter has room for one more request.
    }
}

$uptimeRobot = new UptimeRobot('your-api-key', rateLimiter: new SharedRateLimiter());
```

## Configuration

```php
use GoSuccess\UptimeRobot\ClientOptions;
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key', new ClientOptions(
    timeout: 30.0,              // seconds per request
    connectTimeout: 10.0,
    maxRetries: 3,
    retryBaseDelay: 1.0,        // exponential backoff: 1 s, 2 s, 4 s, ...
    maxRetryDelay: 60.0,        // also caps Retry-After and the wait for the next window
    awaitRateLimitReset: true,  // wait for the next window once no requests remain
    userAgent: 'my-app/1.0',
));
```

A `429` is always retried, up to `maxRetries` times. A server error or a lost
connection is only retried for `GET`, `PUT` and `DELETE`: UptimeRobot creates resources and triggers
actions with `POST` and changes them with `PATCH`, and repeating such a request
could apply it twice. The first attempt of a `DELETE` may still have deleted
the resource, and the API answers a repeated `DELETE` with a `404` (verified
live). So a `DELETE` that is retried after a server error or a lost connection
and then answers `404` counts as successful: the resource is gone either way.

### Custom transport

Implement `GoSuccess\UptimeRobot\Http\HttpClient` to route requests through
your own HTTP stack, e.g. Guzzle, without the library depending on it, or to
wrap the built-in transport:

```php
use GoSuccess\UptimeRobot\Http\CurlHttpClient;
use GoSuccess\UptimeRobot\Http\HttpClient;
use GoSuccess\UptimeRobot\Http\Request;
use GoSuccess\UptimeRobot\Http\Response;
use GoSuccess\UptimeRobot\UptimeRobot;

final class LoggingTransport implements HttpClient
{
    public function __construct(private readonly HttpClient $inner = new CurlHttpClient()) {}

    public function send(#[SensitiveParameter] Request $request): Response
    {
        $response = $this->inner->send($request);
        error_log("{$request->method->value} {$request->uri}: {$response->statusCode}");

        return $response;
    }
}

$uptimeRobot = new UptimeRobot('your-api-key', httpClient: new LoggingTransport());
```

A transport returns error statuses as responses, throws a `TransportException`
only for network failures, returns lower-case header names and must not follow
redirects, so that the API key never reaches another host. The request carries
the key, so mark the parameter `#[SensitiveParameter]`.

The built-in cURL transport keeps connections alive and, on PHP 8.5, shares
DNS, connection and TLS session caches across the requests of a PHP worker.

## API quirks

Where UptimeRobot's API departs from its specification, the client follows the
API. The deviations you are most likely to meet, verified live unless noted:

- **Two uptime scales**: the account's `overallUptime` is a fraction from 0 to
  1, a monitor's `uptime` a percentage from 0 to 100.
- **Passwords in plain text**: `Monitor::$httpPassword` holds the password of
  the check's HTTP authentication. Treat monitor data as secret.
- **Misleading defaults**: new monitors of every type report the `authType`
  `HTTP_BASIC` (with empty credentials) and the `httpMethodType` `null`, and
  the status `STARTED` until their first check.
- **Merge or replace**: an update merges `config` key by key, but replaces
  `customHttpHeaders`, `customFields` and `successHttpResponseCodes`, where
  `[]` resets to `["2xx", "3xx"]`. `responseTimeThreshold` always reads `0`.
- **Incident IDs are strings** of 18 digits, beyond the integers a JSON number
  holds exactly; so is `Monitor::$lastIncidentId`.
- **Plan-gated features** such as incident comments and announcements answer
  `403` with the code `000-003` before the API looks at IDs or bodies.
- **Mobile app contacts**: the API accepts the types `MobileAppOld` (iOS) and
  `MobileApp` (Android) through October 10, 2026, according to the
  specification; the enum marks them deprecated in favor of `MobileAppIos`
  and `MobileAppAndroid`. The app contacts of the test account read as
  `MobileApp`; expect `MobileAppIOS` and `MobileAppAndroid` in
  `AlertContact::$type` as well.
- **Four contact lists** that list different contacts (see
  [Alert contacts](#alert-contacts)).
- **The activity log has one page**: the API reports a `nextLink` but ignores
  its cursor.
- **Status page design values** are lower-case (`light`, `logo_on_left`),
  unlike the specification's `Light` and `LogoOnLeft`; checked with the
  validator.
- **Integration updates need the type**, which the specification makes
  optional.
- **Time ranges**: `uptimeStats()` ignores `start` and `end` except for
  `Custom`; the response time statistics need `from` and `to` together.
- **Rate-limit headers** count seconds, not a Unix time (see
  [Rate limits](#rate-limits)).
- **Maintenance windows** (not verified live): the weekday numbering follows
  the Terraform provider, and no time zone is documented.

The generator configuration lists every correction with its evidence (see
[Development](#development)).

## Documentation and examples

- **[docs/](docs/README.md)**: a reference page for every method, with the
  endpoint, signature, parameters and an example.
- **[examples/](examples/README.md)**: runnable scripts for every area. They
  only read data, so they are safe to run against a production account.

## Development

Most enums, models and resources under `src/`, and the `UptimeRobot` class, are
generated from the committed snapshot of UptimeRobot's OpenAPI specification in
[resources/specs/](resources/specs/). They start with the comment
`// This file is generated by tools/generate.php from resources/specs/uptimerobot.json. Do not edit.`
Everything else is written by hand: the HTTP layer, pagination, rate limiting,
exceptions, the model runtime such as `Undefined`, and the `Handwritten/`
traits of the resources, which hold the account's uptime statistics, the bulk
operations and the status page uploads.
Naming decisions and every correction of the specification live in
[tools/config/uptimerobot.php](tools/config/uptimerobot.php), each correction
with the evidence it is based on: a live check, a message of the API's
validator or UptimeRobot's Terraform provider.

```bash
composer generate   # regenerate code and docs from the snapshot
composer check      # php-cs-fixer, PHPStan (level max) and the unit tests
composer specs      # refresh the snapshot from UptimeRobot, then run "composer generate"
```

CI runs the checks on PHP 8.4 and 8.5, regenerates everything and fails if the
committed files are out of date. UptimeRobot changes its published
specification without raising its version, so a weekly workflow fetches it and
fails when it no longer matches the snapshot.

The integration tests check the client against a real account. By default they
only read, and they skip what the account has no data for or the plan lacks:

```bash
UPTIMEROBOT_API_KEY=your-api-key composer test:integration
```

With `UPTIMEROBOT_ALLOW_WRITES=1` as well, `MonitorLifecycleTest` also creates
**one** keyword monitor of `https://example.com`, named
"GoSuccess API client test (safe to delete)", which alerts nobody. It reads,
changes, pauses, starts and resets it, reads its statistics and deletes it
again, even when a check fails; a monitor of that name that an aborted run left
behind is deleted first. It needs a free monitor slot of the plan.

```bash
UPTIMEROBOT_API_KEY=your-api-key UPTIMEROBOT_ALLOW_WRITES=1 composer test:integration
```

## License

[MIT](LICENSE)
