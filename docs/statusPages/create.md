# `$uptimeRobot->statusPages->create()`

> UptimeRobot API v3 · `POST /psps`

Create a status page.

Sent as JSON, or as multipart/form-data when a logo or an icon is uploaded; the specification mentions only the logo for this request, although its model has the icon as well. In the form, the design is sent as nested fields such as customSettings[page][theme], which the API's validator reads like JSON (verified live). A form cannot express null or an empty object, so such a value together with a file raises an InvalidArgumentException before anything is sent: create the page without the file, then upload it with update(). Not verified live beyond the validator: no status page could be created on the test account.

## Signature

```php
public function create(
    StatusPageCreate $page,
    ?FileUpload $logo = null,
    ?FileUpload $icon = null,
): StatusPage
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$page` | `StatusPageCreate` | yes | The page; friendlyName is required. |
| `$logo` | `FileUpload\|null` | no | The logo: JPG or PNG, at most 150 KB, 20-400 px wide and 10-200 px high (per the specification; the API checks it). |
| `$icon` | `FileUpload\|null` | no | The icon, with the same limits. |

## Returns

`StatusPage`

## Example

```php
use GoSuccess\UptimeRobot\Model\StatusPageCreate;
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->statusPages->create(page: new StatusPageCreate(/* ... */));
```
