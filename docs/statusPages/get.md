# `$uptimeRobot->statusPages->get()`

> UptimeRobot API v3 · `GET /psps/{id}`

Get a PSP by ID

Get a Public Status Page by ID

An unknown ID raises a NotFoundException (verified live).

## Signature

```php
public function get(int $id): StatusPage
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `int` | yes | The status page ID. |

## Returns

`StatusPage`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->statusPages->get(id: 123);
```
