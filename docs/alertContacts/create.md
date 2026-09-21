# `$uptimeRobot->alertContacts->create()`

> UptimeRobot API v3 · `POST /alert-contacts`

Create a personal alert contact

Create a personal alert contact. Email and push (iOS/Android) are supported; Pro SMS and voice require phone verification and are not creatable here.

Email contacts need value. Mobile app contacts need platform, oneSignalSubscriptionId, oneSignalUserId and deviceFingerprint, which the official Terraform provider checks before it sends them along with deviceName and pushToken. sslExpirationReminder and isActive can only be set with update(). Not verified live beyond the validator: the owner of the test account allows no contacts to be created.

## Signature

```php
public function create(AlertContactCreate $contact): AlertContact
```

## Parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `$contact` | `AlertContactCreate` | yes |  |

## Returns

`AlertContact`

## Example

```php
use GoSuccess\UptimeRobot\Enum\AlertContactType;
use GoSuccess\UptimeRobot\Model\AlertContactCreate;
use GoSuccess\UptimeRobot\UptimeRobot;

$uptimeRobot = new UptimeRobot('your-api-key');

$result = $uptimeRobot->alertContacts->create(
    contact: new AlertContactCreate(
        type: AlertContactType::Email,
        friendlyName: 'Ops',
        value: 'ops@example.com',
    ),
);
```
