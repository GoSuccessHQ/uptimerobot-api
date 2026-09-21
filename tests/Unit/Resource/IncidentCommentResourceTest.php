<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Resource;

use GoSuccess\UptimeRobot\Exception\ForbiddenException;
use GoSuccess\UptimeRobot\Http\Response;
use GoSuccess\UptimeRobot\Model\IncidentComment;
use GoSuccess\UptimeRobot\Model\IncidentCommentCreate;
use GoSuccess\UptimeRobot\Model\IncidentCommentUpdate;
use GoSuccess\UptimeRobot\Resource\IncidentCommentResource;
use GoSuccess\UptimeRobot\Tests\Support\MockHttpClient;
use GoSuccess\UptimeRobot\UptimeRobot;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The incident comments resource against a mocked transport. The account the
 * client was checked with lacks the plan feature, so the comments are shaped
 * as the specification documents them; the 403 is the one the API sent.
 */
#[CoversClass(IncidentCommentResource::class)]
final class IncidentCommentResourceTest extends TestCase
{
    private const string INCIDENT = '358532761126055015';

    private const string COMMENT = '{"id":42,"comment":"Investigating the issue...","publishOnStatusPage":true,"created":"2026-09-16T08:45:00.000Z",'
        . '"user":{"id":1234567,"fullName":"Jane Doe"}}';

    public function testReadsAPageOfComments(): void
    {
        $http = new MockHttpClient(new Response(200, '{"data":[' . self::COMMENT . '],'
            . '"nextLink":"https://api.uptimerobot.com/v3/incidents/358532761126055015/comments?limit=1&cursor=42"}'));

        $page = self::client($http)->incidentComments->list(self::INCIDENT, limit: 1);

        self::assertSame('GET', $http->requests[0]->method->value);
        self::assertSame('https://api.uptimerobot.com/v3/incidents/358532761126055015/comments?limit=1', $http->requests[0]->uri);
        // The comment ID, an int like the IDs of the comments themselves.
        self::assertSame(42, $page->next);

        $comment = $page->items[0];
        self::assertSame(42, $comment->id);
        // "comment" in responses, "content" in requests.
        self::assertSame('Investigating the issue...', $comment->comment);
        self::assertTrue($comment->publishOnStatusPage);
        self::assertSame('2026-09-16T08:45:00+00:00', $comment->created?->format(\DATE_ATOM));
        self::assertSame(1234567, $comment->user->id);
        self::assertSame('Jane Doe', $comment->user->fullName);
    }

    public function testIteratesOverAllCommentsInPagesOfOneHundred(): void
    {
        $http = new MockHttpClient(
            new Response(200, '{"data":[' . self::COMMENT . '],"nextLink":"https://api.uptimerobot.com/v3/incidents/358532761126055015/comments?limit=100&cursor=42"}'),
            new Response(200, '{"data":[{"id":43,"comment":"Fixed.","publishOnStatusPage":false,"created":"2026-09-16T09:15:00.000Z",'
                . '"user":{"id":1234567,"fullName":"Jane Doe"}}],"nextLink":null}'),
        );

        $ids = array_map(
            static fn(IncidentComment $comment): int => $comment->id,
            iterator_to_array(self::client($http)->incidentComments->all(self::INCIDENT), false),
        );

        self::assertSame([42, 43], $ids);
        self::assertSame('https://api.uptimerobot.com/v3/incidents/358532761126055015/comments?limit=100', $http->requests[0]->uri);
        self::assertSame('https://api.uptimerobot.com/v3/incidents/358532761126055015/comments?cursor=42&limit=100', $http->requests[1]->uri);
    }

    public function testCreatesACommentAndReadsTheBodyIfThereIsOne(): void
    {
        $http = new MockHttpClient(new Response(201), new Response(201, self::COMMENT));
        $comments = self::client($http)->incidentComments;

        // No body, as the specification documents it.
        self::assertNull($comments->create(self::INCIDENT, new IncidentCommentCreate('Investigating the issue...')));

        $request = $http->requests[0];
        self::assertSame('POST', $request->method->value);
        self::assertSame('https://api.uptimerobot.com/v3/incidents/358532761126055015/comments', $request->uri);
        self::assertSame('application/json', $request->headers['Content-Type'] ?? null);
        self::assertSame('{"content":"Investigating the issue..."}', $request->body);

        $comment = $comments->create(self::INCIDENT, new IncidentCommentCreate('Investigating the issue...', publishOnStatusPage: true));

        self::assertSame('{"content":"Investigating the issue...","publishOnStatusPage":true}', $http->requests[1]->body);
        self::assertSame(42, $comment?->id);
    }

    public function testUpdatesAComment(): void
    {
        $http = new MockHttpClient(new Response(200, self::COMMENT));

        $comment = self::client($http)->incidentComments->update(self::INCIDENT, 42, new IncidentCommentUpdate('Investigating the issue...', publishOnStatusPage: true));

        $request = $http->requests[0];
        self::assertSame('PATCH', $request->method->value);
        self::assertSame('https://api.uptimerobot.com/v3/incidents/358532761126055015/comments/42', $request->uri);
        self::assertSame('{"content":"Investigating the issue...","publishOnStatusPage":true}', $request->body);
        self::assertSame('Investigating the issue...', $comment->comment);
    }

    public function testDeletesAComment(): void
    {
        // 204 without a body, as documented.
        $http = new MockHttpClient(new Response(204));

        self::client($http)->incidentComments->delete(self::INCIDENT, 42);

        self::assertSame('DELETE', $http->requests[0]->method->value);
        self::assertSame('https://api.uptimerobot.com/v3/incidents/358532761126055015/comments/42', $http->requests[0]->uri);
        self::assertNull($http->requests[0]->body);
    }

    public function testReportsThatThePlanLacksTheFeature(): void
    {
        // The API sent this for every comment endpoint, before it checked the
        // incident or the body (verified live).
        $body = '{"message":"Feature incident-comments is not enabled in your plan.","code":"000-003"}';
        $http = new MockHttpClient(new Response(403, $body), new Response(403, $body));
        $comments = self::client($http)->incidentComments;

        try {
            $comments->list(self::INCIDENT);
            self::fail('Expected a ForbiddenException.');
        } catch (ForbiddenException $e) {
            self::assertSame('000-003', $e->errorCode);
            self::assertStringEndsWith('HTTP 403: Feature incident-comments is not enabled in your plan. (000-003)', $e->getMessage());
        }

        $this->expectException(ForbiddenException::class);

        $comments->create('999999999', new IncidentCommentCreate(''));
    }

    private static function client(MockHttpClient $http): UptimeRobot
    {
        return new UptimeRobot('secret', httpClient: $http);
    }
}
