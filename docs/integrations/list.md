# `$uptimeRobot->integrations->list()`

> UptimeRobot API v3 · `GET /integrations`

List Integrations

Ascending by ID; the cursor is the ID of the last item of the previous page. Without includeOrgMembers only integrations are listed (verified live).

## Signature

```php
public function list(?int $cursor = null, ?bool $includeOrgMembers = null): Page
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$cursor` | `int\|null` | no | The cursor of the page to return, as the previous page reported it; null for the first page. |
| `$includeOrgMembers` | `bool\|null` | no | With true, the personal alert contacts are listed along with the integrations, in the same shape. Verified live on an account in no organization, which got all its own contacts, mobile app contacts included; the specification promises the contacts of the members of an organization the caller owns. |

## Returns

`Page<Integration>`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->integrations->list();
```
