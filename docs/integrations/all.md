# `$uptimeRobot->integrations->all()`

> UptimeRobot API v3 · `GET /integrations`

Iterate lazily over every item of list(), across all pages.

Ascending by ID; the cursor is the ID of the last item of the previous page. Without includeOrgMembers only integrations are listed. With includeOrgMembers true the personal alert contacts are listed as well, in the same shape: the test account, which has no integrations, got all its contacts, although the specification promises the contacts of the members of an organization (both verified live).

## Signature

```php
public function all(?bool $includeOrgMembers = null): Paginator
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$includeOrgMembers` | `bool\|null` | no | When true and the caller owns an organization, include each active member's personal alert contacts (EmailToSms / Email / ProSms / Voice) in the response. Used by the v2 getAlertContacts proxy to restore the legacy org-roster scope. |

## Returns

`Paginator<Integration>`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

foreach ($uptimeRobot->integrations->all() as $item) {
    // ...
}
```
