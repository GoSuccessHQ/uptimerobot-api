# `$uptimeRobot->bulkMonitors->pause()`

> UptimeRobot API v3 · `POST /monitors/bulk/pause`

Pause the monitors of a group and/or with a tag.

Select the monitors with $groupId, $tagId or both; without either, an InvalidArgumentException is thrown before anything is sent. The result lists every selected monitor; one that could not be paused is reported there with its error, not as an exception.

## Signature

```php
public function pause(?int $groupId = null, ?int $tagId = null): BulkOperationResult
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

$result = $uptimeRobot->bulkMonitors->pause(groupId: 123);
```
