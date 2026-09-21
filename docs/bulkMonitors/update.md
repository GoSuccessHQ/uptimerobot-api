# `$uptimeRobot->bulkMonitors->update()`

> UptimeRobot API v3 · `POST /monitors/bulk/update`

Change settings of the monitors of a group and/or with a tag.

Select the monitors with $groupId, $tagId or both; without either, or without a setting to change, an InvalidArgumentException is thrown before anything is sent. Only the settings set in $changes are sent. The result lists every selected monitor; one that could not be changed is reported there with its error, not as an exception.

## Signature

```php
public function update(BulkMonitorUpdate $changes, ?int $groupId = null, ?int $tagId = null): BulkOperationResult
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$changes` | `BulkMonitorUpdate` | yes | The settings to change; at least one. |
| `$groupId` | `int\|null` | no | The monitor group; 0 selects the monitors in no group. |
| `$tagId` | `int\|null` | no | The tag. |

## Returns

`BulkOperationResult`

## Example

```php
use GoSuccess\UptimeRobot\Model\BulkMonitorUpdate;
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->bulkMonitors->update(changes: new BulkMonitorUpdate(/* ... */));
```
