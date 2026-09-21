# `$uptimeRobot->alertContacts->all()`

> UptimeRobot API v3 · `GET /alert-contacts`

Iterate lazily over every item of list(), across all pages.

Ascending by ID; the cursor is the ID of the last contact of the previous page, and the contacts with greater IDs follow. The page size is not documented: all 6 contacts of the test account came on one page without nextLink. The lists of contacts differ: this one left out the mobile app contacts awaiting migration (status ToMigrate), which UserResource::alertContacts() lists, while IntegrationResource::list() with includeOrgMembers listed every contact (all verified live).

## Signature

```php
public function all(): Paginator
```

## Returns

`Paginator<AlertContact>`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

foreach ($uptimeRobot->alertContacts->all() as $item) {
    // ...
}
```
