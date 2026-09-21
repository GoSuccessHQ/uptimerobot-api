<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Integration;

use GoSuccess\UptimeRobot\Exception\ForbiddenException;
use GoSuccess\UptimeRobot\Exception\NotFoundException;
use GoSuccess\UptimeRobot\Model\AlertContact;
use GoSuccess\UptimeRobot\UptimeRobot;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Reads the personal alert contacts of the account; nothing is changed.
 */
#[CoversNothing]
final class AlertContactsReadOnlyTest extends IntegrationTestCase
{
    /** The statuses UptimeRobot's clients name; the specification documents none. */
    private const array STATUSES = ['Active', 'Paused', 'NotActivated', 'ToMigrate'];

    /** @var list<AlertContact>|null Every contact, read once for all tests. */
    private static ?array $contacts = null;

    public function testListsTheContactsInAscendingOrder(): void
    {
        $contacts = self::contacts();
        $ids = array_map(static fn(AlertContact $contact): int => $contact->id, $contacts);
        $sorted = $ids;
        sort($sorted);

        self::assertSame($sorted, $ids);
        self::assertSame($ids, array_values(array_unique($ids)));

        foreach ($contacts as $contact) {
            self::assertGreaterThan(0, $contact->id);
            self::assertMatchesRegularExpression('/^[A-Za-z]+$/', $contact->type, "Unexpected type of contact {$contact->id}.");
            // Fails if the API reports a status the docblock does not name.
            self::assertContains($contact->status, self::STATUSES, "Unknown status of contact {$contact->id}.");
            // The names are the only values the responses use (verified live).
            self::assertNotNull($contact->enableNotificationsFor, "Unknown enableNotificationsFor of contact {$contact->id}.");
        }
    }

    public function testContinuesAfterACursor(): void
    {
        $contacts = self::contacts();
        $ids = array_map(static fn(AlertContact $contact): int => $contact->id, $contacts);

        // The contacts with greater IDs follow (verified live).
        $rest = self::client()->alertContacts->list(cursor: $ids[0])->items;

        self::assertSame(\array_slice($ids, 1, \count($rest)), array_map(static fn(AlertContact $contact): int => $contact->id, $rest));
    }

    public function testReadsAContactAsTheListDoes(): void
    {
        $listed = self::contacts()[0];

        self::assertEquals($listed, self::client()->alertContacts->get($listed->id));
    }

    public function testReportsAnUnknownContact(): void
    {
        self::contacts();

        try {
            self::client()->alertContacts->get(999999999);
            self::fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            // {"message":"Alert contact not found","code":"000-004"} (verified live).
            self::assertSame('000-004', $e->errorCode);
        }
    }

    private static function client(): UptimeRobot
    {
        return new UptimeRobot(self::apiKey());
    }

    /**
     * @return non-empty-list<AlertContact>
     */
    private static function contacts(): array
    {
        try {
            self::$contacts ??= iterator_to_array(self::client()->alertContacts->all(), false);
        } catch (ForbiddenException $e) {
            if ($e->errorCode === '000-003') {
                self::markTestSkipped('The plan of the account does not include alert contacts.');
            }

            throw $e;
        }

        if (self::$contacts === []) {
            self::markTestSkipped('The account has no personal alert contacts.');
        }

        return self::$contacts;
    }
}
