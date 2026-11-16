# `$uptimeRobot->thirdPartyProviders->searchComponents()`

> UptimeRobot API v3 · `GET /third-party-providers/{id}/components`

Search the components of a third-party provider

Returns the component tree of one third-party provider reduced to the components that match search and region, each leaf under its parent. regionLike tells whether the search term looks like a region rather than a component name. Results are cached for a minute.

The components of one provider that match, each leaf under its parent, for picking trackedComponentIds of a large provider. Every word of search must match a component name, the name of a parent component, a region name or a region code; region keeps one exact region code and never global components. regionLike tells whether search reads like a region rather than a component name. Without search and region, the whole tree comes back. The answers are cached for a minute (according to the specification; not verified live).

## Signature

```php
public function searchComponents(int $id, ?string $search = null, ?string $region = null): ThirdPartyProviderComponentSearch
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$id` | `int` | yes | The numeric id of the third-party provider. |
| `$search` | `string\|null` | no | Words matched against component names, parent component names, region names and region codes. Every word must match. |
| `$region` | `string\|null` | no | Keep only the components of this exact region code. Global components are never returned for a region. |

## Returns

`ThirdPartyProviderComponentSearch`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->thirdPartyProviders->searchComponents(
    id: 123,
    search: 'lambda oregon',
);
```
