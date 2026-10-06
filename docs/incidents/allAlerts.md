# `$uptimeRobot->incidents->allAlerts()`

> UptimeRobot API v3 · `GET /incidents/{id}/alerts`

Iterate lazily over every item of alerts(), across all pages.

Newest first in pages, according to the specification of October 5, 2026; before it, the API sent every alert in one response, oldest first, and an empty list if no alert was sent (verified live then). The order and the pages are not verified live since.

## Signature

```php
public function allAlerts(string $id, ?int $limit = null): Paginator
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `string` | yes | ID of the incident |
| `$limit` | `int\|null` | no | Number of sent alerts to return (1-200, default 50) |

## Returns

`Paginator<SentAlert>`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

foreach ($uptimeRobot->incidents->allAlerts(id: '123456789') as $item) {
    // ...
}
```
