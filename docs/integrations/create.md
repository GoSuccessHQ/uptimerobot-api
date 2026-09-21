# `$uptimeRobot->integrations->create()`

> UptimeRobot API v3 · `POST /integrations`

Create an Integration

Pass the model of the integration type, e.g. SlackIntegrationCreate, which sends its type. The API matches the type ignoring case; a type the plan lacks raises a ForbiddenException with the code 021-003 (verified live for update() with PagerDuty on the Solo plan). The specification gives Telegram no chat setting. Not verified live beyond the type: the owner of the test account allows no integrations to be created.

## Signature

```php
public function create(IntegrationCreate $integration): Integration
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$integration` | `IntegrationCreate` | yes |  |

## Returns

`Integration`

## Example

```php
use GoSuccess\UptimeRobot\Model\SlackIntegrationCreate;
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->integrations->create(integration: new SlackIntegrationCreate(/* ... */));
```
