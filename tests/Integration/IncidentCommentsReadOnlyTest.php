<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Integration;

use GoSuccess\UptimeRobot\Exception\ForbiddenException;
use GoSuccess\UptimeRobot\Model\IncidentComment;
use GoSuccess\UptimeRobot\UptimeRobot;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Reads the comments of the incidents that have some; nothing is changed.
 * Skipped unless the plan of the account includes incident comments.
 */
#[CoversNothing]
final class IncidentCommentsReadOnlyTest extends IntegrationTestCase
{
    public function testListsTheCommentsOfAnIncidentOldestFirst(): void
    {
        $client = new UptimeRobot(self::apiKey());
        $incident = null;

        foreach ($client->incidents->all() as $candidate) {
            $incident ??= $candidate;

            if ($candidate->commentsCount > 0) {
                $incident = $candidate;

                break;
            }
        }

        if ($incident === null) {
            self::markTestSkipped('The account has no incidents.');
        }

        try {
            $comments = iterator_to_array($client->incidentComments->all($incident->id), false);
        } catch (ForbiddenException $e) {
            if ($e->errorCode === '000-003') {
                // "Feature incident-comments is not enabled in your plan." (verified live).
                self::markTestSkipped('The plan of the account does not include incident comments.');
            }

            throw $e;
        }

        self::assertCount($incident->commentsCount, $comments);

        $ids = array_map(static fn(IncidentComment $comment): int => $comment->id, $comments);
        self::assertSame($ids, array_values(array_unique($ids)));

        $previous = null;

        foreach ($comments as $comment) {
            self::assertNotNull($comment->created);
            self::assertNotSame('', $comment->user->fullName);

            if ($previous !== null) {
                self::assertGreaterThanOrEqual($previous, $comment->created);
            }

            $previous = $comment->created;
        }
    }
}
