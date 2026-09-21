# `$uptimeRobot->integrations->update()`

> UptimeRobot API v3 · `PATCH /integrations/{id}`

Update an Integration

Pass the model of the integration's type, which sends it: the API requires the type, although the specification makes it optional (verified live). Only the settings that are set are sent; whether the API keeps the others is not documented, and the official Terraform provider always sends all of them. The API checks the type first, then whether the plan includes it (ForbiddenException with the code 021-003), then looks up the integration; invalid settings still reached the lookup (all verified live). Not verified live beyond that: the test account has no integrations.

## Signature

```php
public function update(int $id, IntegrationUpdate $changes): Integration
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `int` | yes | ID of the integration |
| `$changes` | `IntegrationUpdate` | yes |  |

## Returns

`Integration`

## Example

```php
use GoSuccess\UptimeRobot\Model\SlackIntegrationUpdate;
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->integrations->update(
    id: 123,
    changes: new SlackIntegrationUpdate(customValue: '#ops'),
);
```
