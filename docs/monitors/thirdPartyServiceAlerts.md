# `$uptimeRobot->monitors->thirdPartyServiceAlerts()`

> UptimeRobot API v3 · `GET /monitors/{id}/third-party-service/incidents/{incidentId}/activity-log`

Get the activity log of a third party service incident

Returns the notifications this account sent about one incident of a third party service, with the channel, the alert contact and whether delivery succeeded. channelIdentifier is shown only for email, SMS and voice channels; for every other channel, such as webhooks, Slack or mobile push, whose value can carry a credential, it is the literal string "[REDACTED]". An incident with no notifications returns an empty array with a 200.

The alerts the account sent about one incident of the tracked provider. channelIdentifier is only shown for email, SMS and voice; for every other channel it is "[REDACTED]", according to the specification (not verified live).

## Signature

```php
public function thirdPartyServiceAlerts(int $id, string $incidentId): array
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `int` | yes | The monitor ID |
| `$incidentId` | `string` | yes | The incident ID from the incident history of the third party service. A string, since it can exceed the safe integer range. |

## Returns

`list<ThirdPartyServiceAlert>`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->monitors->thirdPartyServiceAlerts(
    id: 123,
    incidentId: '123456789',
);
```
