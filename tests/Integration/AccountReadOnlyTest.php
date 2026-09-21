<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Integration;

use GoSuccess\UptimeRobot\Model\AlertContact;
use GoSuccess\UptimeRobot\Model\AllAlertContact;
use GoSuccess\UptimeRobot\Model\Tag;
use GoSuccess\UptimeRobot\UptimeRobot;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Reads the account, its alert contacts, tags and storm protection settings;
 * nothing is changed.
 */
#[CoversNothing]
final class AccountReadOnlyTest extends IntegrationTestCase
{
    public function testReadsTheUserAndItsPlan(): void
    {
        $user = self::client()->user->me();

        self::assertNotSame('', $user->email);
        self::assertGreaterThan(0, $user->monitorLimit);
        self::assertNotSame('', $user->activeSubscription->plan);
    }

    public function testReadsTheAlertContacts(): void
    {
        $client = self::client();

        self::assertContainsOnlyInstancesOf(AlertContact::class, $client->user->alertContacts());
        self::assertContainsOnlyInstancesOf(AllAlertContact::class, $client->user->allAlertContacts());
    }

    public function testIteratesOverTheTags(): void
    {
        $client = self::client();
        $tags = iterator_to_array($client->tags->all());

        self::assertContainsOnlyInstancesOf(Tag::class, $tags);
        // Every response reports the quota, e.g. 20 requests per minute on the Solo plan.
        self::assertGreaterThan(0, $client->rateLimit?->limit);
    }

    public function testReadsTheStormProtectionSettings(): void
    {
        $settings = self::client()->stormProtection->get();

        self::assertNotNull($settings->thresholdType);
        self::assertGreaterThan(0, $settings->windowMinutes);
    }

    private static function client(): UptimeRobot
    {
        return new UptimeRobot(self::apiKey());
    }
}
