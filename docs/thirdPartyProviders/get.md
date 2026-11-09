# `$uptimeRobot->thirdPartyProviders->get()`

> UptimeRobot API v3 · `GET /third-party-providers/{id}`

Get a third-party provider

Returns one third-party provider by its numeric id, with its current state and its component tree. Components carry their own id, name and status, and list their children.

The provider with its state and its tree of components; their IDs scope a monitor through trackedComponentIds.

## Signature

```php
public function get(int $id): ThirdPartyProvider
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `int` | yes | The numeric id of the third-party provider. |

## Returns

`ThirdPartyProvider`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->thirdPartyProviders->get(id: 123);
```
