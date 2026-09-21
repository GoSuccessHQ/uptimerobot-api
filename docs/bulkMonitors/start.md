# `$uptimeRobot->bulkMonitors->start()`

> UptimeRobot API v3 · `POST /monitors/bulk/start`

Start the paused monitors of a group and/or with a tag.

Select the monitors with $groupId, $tagId or both; without either, an InvalidArgumentException is thrown before anything is sent. The result lists every selected monitor; one that could not be started is reported there with its error, not as an exception.

## Signature

```php
public function start(?int $groupId = null, ?int $tagId = null): BulkOperationResult
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$groupId` | `int\|null` | no | The monitor group; 0 selects the monitors in no group. |
| `$tagId` | `int\|null` | no | The tag. |

## Returns

`BulkOperationResult`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->bulkMonitors->start();
```
