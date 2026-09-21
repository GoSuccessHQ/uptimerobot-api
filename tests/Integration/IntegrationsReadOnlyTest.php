<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Integration;

use GoSuccess\UptimeRobot\Exception\NotFoundException;
use GoSuccess\UptimeRobot\Model\AlertContact;
use GoSuccess\UptimeRobot\Model\Integration;
use GoSuccess\UptimeRobot\UptimeRobot;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Reads the integrations of the account, and its personal contacts in the
 * shape of integrations; nothing is changed.
 */
#[CoversNothing]
final class IntegrationsReadOnlyTest extends IntegrationTestCase
{
    /** The statuses UptimeRobot's clients name; the specification documents none. */
    private const array STATUSES = ['Active', 'Paused', 'NotActivated', 'ToMigrate'];

    public function testListsTheIntegrations(): void
    {
        $integrations = iterator_to_array(self::client()->integrations->all(), false);

        self::assertIntegrations($integrations);

        if ($integrations === []) {
            // {"data": []} (verified live).
            self::markTestSkipped('The account has no integrations.');
        }

        self::assertEquals($integrations[0], self::client()->integrations->get($integrations[0]->id));
    }

    public function testListsThePersonalContactsWithTheOrganizationMembers(): void
    {
        $listed = iterator_to_array(self::client()->integrations->all(includeOrgMembers: true), false);
        $contacts = iterator_to_array(self::client()->alertContacts->all(), false);

        if ($contacts === []) {
            self::markTestSkipped('The account has no personal alert contacts.');
        }

        self::assertIntegrations($listed);

        // Every contact of /alert-contacts is among them (verified live: all
        // of them and those awaiting migration).
        $ids = array_map(static fn(Integration $integration): int => $integration->id, $listed);
        $missing = array_diff(array_map(static fn(AlertContact $contact): int => $contact->id, $contacts), $ids);
        self::assertSame([], array_values($missing));

        // The items with greater IDs follow the cursor (verified live).
        $rest = self::client()->integrations->list(cursor: $ids[0], includeOrgMembers: true)->items;
        self::assertSame(\array_slice($ids, 1, \count($rest)), array_map(static fn(Integration $integration): int => $integration->id, $rest));
    }

    public function testReportsUnknownIntegrationsWithTwoCodes(): void
    {
        $client = self::client();

        try {
            $client->integrations->get(999999999);
            self::fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            // {"message":"Alert contact not found","code":"000-004"} (verified live).
            self::assertSame('000-004', $e->errorCode);
        }

        $contact = $client->alertContacts->list()->items[0] ?? null;

        if ($contact === null) {
            self::markTestSkipped('The account has no personal alert contacts.');
        }

        try {
            $client->integrations->get($contact->id);
            self::fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            // {"message":"No integration found.","code":"021-005"} (verified live).
            self::assertSame('021-005', $e->errorCode);
        }
    }

    /**
     * @param list<Integration> $integrations
     */
    private static function assertIntegrations(array $integrations): void
    {
        $ids = array_map(static fn(Integration $integration): int => $integration->id, $integrations);
        $sorted = $ids;
        sort($sorted);

        // Ascending by ID, without repetitions (verified live).
        self::assertSame($sorted, $ids);
        self::assertSame($ids, array_values(array_unique($ids)));

        foreach ($integrations as $integration) {
            self::assertGreaterThan(0, $integration->id);
            self::assertMatchesRegularExpression('/^[A-Za-z]+$/', $integration->type, "Unexpected type of integration {$integration->id}.");
            // Fails if the API reports a status the docblock does not name.
            self::assertContains($integration->status, self::STATUSES, "Unknown status of integration {$integration->id}.");
            self::assertNotNull($integration->enableNotificationsFor, "Unknown enableNotificationsFor of integration {$integration->id}.");
        }
    }

    private static function client(): UptimeRobot
    {
        return new UptimeRobot(self::apiKey());
    }
}
