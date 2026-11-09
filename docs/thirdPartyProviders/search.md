# `$uptimeRobot->thirdPartyProviders->search()`

> UptimeRobot API v3 · `GET /third-party-providers`

Search third-party providers

Search the third-party provider catalog by provider or component name. Each provider is identified by its numeric id, and isTracked tells whether the account already monitors it as a third-party service. Results are cached for a minute, so isTracked can lag by up to a minute after a third-party service is created or deleted.

Matches the names of the providers and of their components; matchedComponent names the component that matched. isTracked tells whether the account monitors the provider already; the results are cached for a minute, so it can lag that long behind creating or deleting a monitor (according to the specification).

## Signature

```php
public function search(string $search): array
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$search` | `string` | yes | Free-text term matched against provider names and their components. |

## Returns

`list<ThirdPartyProviderMatch>`

## Example

```php
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->thirdPartyProviders->search(search: 'example');
```
