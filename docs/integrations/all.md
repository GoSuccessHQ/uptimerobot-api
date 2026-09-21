# `$uptimeRobot->integrations->all()`

> UptimeRobot API v3 · `GET /integrations`

Iterate lazily over every item of list(), across all pages.

Ascending by ID; the cursor is the ID of the last item of the previous page. Without includeOrgMembers only integrations are listed (verified live).

## Signature

```php
public function all(?bool $includeOrgMembers = null): Paginator
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$includeOrgMembers` | `bool\|null` | no | With true, the personal alert contacts are listed along with the integrations, in the same shape. Verified live on an account in no organization, which got all its own contacts, mobile app contacts included; the specification promises the contacts of the members of an organization the caller owns. |

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
