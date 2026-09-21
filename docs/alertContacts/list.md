# `$uptimeRobot->alertContacts->list()`

> UptimeRobot API v3 · `GET /alert-contacts`

List personal alert contacts

List the personal alert contacts (email, Pro SMS, voice, iOS/Android push) owned by the authenticated user. Integrations are managed separately through /v3/integrations.

Ascending by ID; the cursor is the ID of the last contact of the previous page, and the contacts with greater IDs follow. The page size is not documented: all 6 contacts of the test account came on one page without nextLink. The lists of contacts differ: this one left out the mobile app contacts awaiting migration (status ToMigrate), which UserResource::alertContacts() lists, while IntegrationResource::list() with includeOrgMembers listed every contact (all verified live).

## Signature

```php
public function list(?int $cursor = null): Page
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$cursor` | `int\|null` | no | The cursor of the page to return, as the previous page reported it; null for the first page. |

## Returns

`Page<AlertContact>`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->alertContacts->list();
```
