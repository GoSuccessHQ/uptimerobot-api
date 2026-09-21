# `$uptimeRobot->tags->delete()`

> UptimeRobot API v3 · `DELETE /tags/{id}`

Delete a tag

Delete a tag and remove it from all monitors

## Signature

```php
public function delete(int $id): void
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `int` | yes | Tag ID to delete |

## Returns

`void`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$uptimeRobot->tags->delete(id: 123);
```
