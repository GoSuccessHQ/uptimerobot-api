<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit;

use GoSuccess\UptimeRobot\Http\Response;
use GoSuccess\UptimeRobot\Model\User;
use GoSuccess\UptimeRobot\Resource\TagResource;
use GoSuccess\UptimeRobot\Tests\Support\MockHttpClient;
use GoSuccess\UptimeRobot\UptimeRobot;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UptimeRobot::class)]
final class UptimeRobotTest extends TestCase
{
    public function testCreatesEachResourceOnceOnFirstAccess(): void
    {
        $client = new UptimeRobot('secret', httpClient: new MockHttpClient());

        self::assertInstanceOf(TagResource::class, $client->tags);
        self::assertSame($client->tags, $client->tags);
    }

    public function testSendsTheKeyAsABearerTokenToTheDefaultBaseUri(): void
    {
        $http = new MockHttpClient(new Response(200, '{"email": "ops@example.com"}'));

        $user = new UptimeRobot('secret', httpClient: $http)->user->me();

        self::assertInstanceOf(User::class, $user);
        self::assertSame('ops@example.com', $user->email);
        self::assertSame(UptimeRobot::DEFAULT_BASE_URI . '/user/me', $http->requests[0]->uri);
        self::assertSame('Bearer secret', $http->requests[0]->headers['Authorization']);
    }

    public function testUsesACustomBaseUri(): void
    {
        $http = new MockHttpClient(new Response(204));

        new UptimeRobot('secret', httpClient: $http, baseUri: 'https://proxy.example.com/uptimerobot/')->tags->delete(5);

        self::assertSame('https://proxy.example.com/uptimerobot/tags/5', $http->requests[0]->uri);
    }

    public function testReportsTheRateLimitOfTheLastResponse(): void
    {
        $http = new MockHttpClient(new Response(200, '{}', ['x-ratelimit-limit' => '20', 'x-ratelimit-remaining' => '19', 'x-ratelimit-reset' => '60']));
        $client = new UptimeRobot('secret', httpClient: $http);

        self::assertNull(new UptimeRobot('secret', httpClient: new MockHttpClient())->rateLimit);

        $client->stormProtection->get();
        $status = $client->rateLimit;

        self::assertNotNull($status);
        self::assertSame(20, $status->limit);
        self::assertSame(19, $status->remaining);
    }

    public function testHidesTheApiKeyFromDumps(): void
    {
        $client = new UptimeRobot('super-secret-key', httpClient: new MockHttpClient());

        self::assertSame(['baseUri' => UptimeRobot::DEFAULT_BASE_URI, 'apiKey' => '********', 'rateLimit' => null], $client->__debugInfo());
        self::assertStringNotContainsString('super-secret-key', print_r($client, true));
        self::assertStringNotContainsString('super-secret-key', var_export($client->__debugInfo(), true));
    }
}
