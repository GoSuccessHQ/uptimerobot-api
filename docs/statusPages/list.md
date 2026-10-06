# `$uptimeRobot->statusPages->list()`

> UptimeRobot API v3 · `GET /psps`

List PSPs

List Public Status Pages

The cursor is the ID of the last status page of the previous page, as the official Terraform provider reads it from nextLink. Only a single page was observable: an account without status pages gets {"data": []} without nextLink (verified live).

## Signature

```php
public function list(?int $cursor = null, ?int $limit = null): Page
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$cursor` | `int\|null` | no | The cursor of the page to return, as the previous page reported it; null for the first page. |
| `$limit` | `int\|null` | no | Maximum number of status pages to return per page. Default: 50, Min: 1, Max: 200. |

## Returns

`Page<StatusPage>`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->statusPages->list();
```
