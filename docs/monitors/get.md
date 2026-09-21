# `$uptimeRobot->monitors->get()`

> UptimeRobot API v3 · `GET /monitors/{id}`

Get a monitor by ID

Get a monitor details by ID

## Signature

```php
public function get(int $id): Monitor
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `int` | yes | The monitor ID. |

## Returns

`Monitor`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->monitors->get(id: 123);
```
