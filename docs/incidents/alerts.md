# `$uptimeRobot->incidents->alerts()`

> UptimeRobot API v3 · `GET /incidents/{id}/alerts`

Get incident sent alerts

Returns all alerts that were sent for a specific incident, including recipient information and delivery status.

Oldest first, all in one response; empty if no alert was sent (verified live).

## Signature

```php
public function alerts(string $id): array
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `string` | yes | ID of the incident |

## Returns

`list<SentAlert>`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->incidents->alerts(id: '123456789');
```
