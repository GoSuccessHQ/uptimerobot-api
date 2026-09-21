# `$uptimeRobot->statusPages->update()`

> UptimeRobot API v3 · `PATCH /psps/{id}`

Update a status page.

Only the properties that are set are sent, as JSON, or as multipart/form-data when a logo or an icon is uploaded; to replace only the logo, pass an empty StatusPageUpdate. In the form, the design is sent as nested fields such as customSettings[page][theme], which the API's validator reads like JSON (verified live). A form cannot express null or an empty object, so such a value together with a file raises an InvalidArgumentException before anything is sent: make that change and the upload in two calls. The validator runs before the page is looked up, so an invalid change to an unknown ID raises a BadRequestException, a valid one a NotFoundException (both verified live). Not verified live beyond that: the test account has no status pages.

## Signature

```php
public function update(
    int $id,
    StatusPageUpdate $changes,
    ?FileUpload $logo = null,
    ?FileUpload $icon = null,
): StatusPage
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `int` | yes | The status page. |
| `$changes` | `StatusPageUpdate` | yes | The properties to change. |
| `$logo` | `FileUpload\|null` | no | A new logo: JPG or PNG, at most 150 KB, 20-400 px wide and 10-200 px high (per the specification; the API checks it). |
| `$icon` | `FileUpload\|null` | no | A new icon, with the same limits. |

## Returns

`StatusPage`

## Example

```php
use GoSuccess\UptimeRobot\Model\StatusPageUpdate;
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->statusPages->update(
    id: 123,
    changes: new StatusPageUpdate(friendlyName: 'Status'),
);
```
