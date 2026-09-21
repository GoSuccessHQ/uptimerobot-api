<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Http;

use GoSuccess\UptimeRobot\ClientOptions;
use GoSuccess\UptimeRobot\Exception\ApiException;
use GoSuccess\UptimeRobot\Exception\AuthenticationException;
use GoSuccess\UptimeRobot\Exception\BadRequestException;
use GoSuccess\UptimeRobot\Exception\ConflictException;
use GoSuccess\UptimeRobot\Exception\ForbiddenException;
use GoSuccess\UptimeRobot\Exception\NotFoundException;
use GoSuccess\UptimeRobot\Exception\RateLimitException;
use GoSuccess\UptimeRobot\Exception\SerializationException;
use GoSuccess\UptimeRobot\Exception\ServerException;
use GoSuccess\UptimeRobot\Exception\TransportException;
use GoSuccess\UptimeRobot\Exception\ValidationException;
use GoSuccess\UptimeRobot\Http\Connection;
use GoSuccess\UptimeRobot\Http\FileUpload;
use GoSuccess\UptimeRobot\Http\Method;
use GoSuccess\UptimeRobot\Http\Response;
use GoSuccess\UptimeRobot\RateLimit\RateLimitStatus;
use GoSuccess\UptimeRobot\Tests\Support\ExceptionTrace;
use GoSuccess\UptimeRobot\Tests\Support\FakeClock;
use GoSuccess\UptimeRobot\Tests\Support\MockHttpClient;
use GoSuccess\UptimeRobot\Tests\Support\SpyRateLimiter;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SensitiveParameterValue;

#[CoversClass(Connection::class)]
final class ConnectionTest extends TestCase
{
    private const string BASE_URI = 'https://api.uptimerobot.com/v3/';

    public function testSendsAuthenticatedJsonRequests(): void
    {
        $http = new MockHttpClient(new Response(201, '{"id":1}'));

        $result = $this->connection($http)->json(Method::Post, '/monitor-groups', ['cursor' => 5, 'name' => null], ['name' => 'Group']);

        self::assertSame(['id' => 1], $result);
        $request = $http->requests[0];
        self::assertSame(Method::Post, $request->method);
        self::assertSame('https://api.uptimerobot.com/v3/monitor-groups?cursor=5', $request->uri);
        self::assertSame('Bearer secret-key', $request->headers['Authorization']);
        self::assertSame('application/json', $request->headers['Content-Type']);
        self::assertSame('application/json', $request->headers['Accept']);
        self::assertSame(ClientOptions::DEFAULT_USER_AGENT, $request->headers['User-Agent']);
        self::assertSame('{"name":"Group"}', $request->body);
        self::assertSame(30.0, $request->timeout);
    }

    public function testEncodesAnEmptyBodyAsJsonObject(): void
    {
        // pause, start and reset only accept their empty body as JSON.
        $http = new MockHttpClient(new Response(201));

        $result = $this->connection($http)->json(Method::Post, 'monitors/1/reset', body: []);

        self::assertNull($result);
        self::assertSame('{}', $http->requests[0]->body);
        self::assertSame('application/json', $http->requests[0]->headers['Content-Type']);
    }

    public function testSendsNoBodyWithoutOne(): void
    {
        $http = new MockHttpClient(new Response(200));

        $this->connection($http)->json(Method::Post, 'psps/1/announcements/2/pin');

        self::assertNull($http->requests[0]->body);
        self::assertArrayNotHasKey('Content-Type', $http->requests[0]->headers);
    }

    public function testDecodesIntegersBeyondTheIntRangeAsStrings(): void
    {
        $http = new MockHttpClient(new Response(200, '{"id":123456789012345678901234,"small":7}'));

        self::assertSame(['id' => '123456789012345678901234', 'small' => 7], $this->connection($http)->json(Method::Get, 'incidents/1'));
    }

    public function testSendsMultipartForms(): void
    {
        $http = new MockHttpClient(new Response(201, '{"id":9}'));

        $result = $this->connection($http)->multipart(
            Method::Patch,
            'psps/9',
            ['friendlyName' => 'Status', 'monitorIds' => [1, 2]],
            ['logo' => new FileUpload('logo.png', 'PNG', 'image/png')],
        );

        self::assertSame(['id' => 9], $result);
        $request = $http->requests[0];
        self::assertSame(Method::Patch, $request->method);
        self::assertSame('Bearer secret-key', $request->headers['Authorization']);
        self::assertMatchesRegularExpression('~^multipart/form-data; boundary=\S+$~', $request->headers['Content-Type']);
        self::assertIsString($request->body);
        self::assertStringContainsString("name=\"monitorIds[]\"\r\n\r\n2\r\n", $request->body);
        self::assertStringContainsString("name=\"logo\"; filename=\"logo.png\"\r\nContent-Type: image/png\r\n\r\nPNG\r\n", $request->body);
    }

    public function testRetries429AndHonorsRetryAfter(): void
    {
        $http = new MockHttpClient(
            new Response(429, '', ['retry-after' => '5']),
            new Response(200, '[]'),
        );
        $clock = new FakeClock();
        $rateLimiter = new SpyRateLimiter();

        // A rejected request was not processed, so even a POST is repeated.
        $this->connection($http, clock: $clock, rateLimiter: $rateLimiter)->json(Method::Post, 'monitors', body: ['type' => 'HTTP']);

        self::assertSame(2, $http->callCount());
        self::assertSame(2, $rateLimiter->acquireCount);
        self::assertSame([5.0], $clock->sleeps);
    }

    public function testRetries429AfterTheAnnouncedResetInSeconds(): void
    {
        // As observed live: the value stays 60 instead of being a timestamp.
        $http = new MockHttpClient(
            new Response(429, '', ['x-ratelimit-limit' => '20', 'x-ratelimit-remaining' => '0', 'x-ratelimit-reset' => '60']),
            new Response(200, '{}', ['x-ratelimit-limit' => '20', 'x-ratelimit-remaining' => '19', 'x-ratelimit-reset' => '60']),
        );
        $clock = new FakeClock(1_800_000_000.0);

        $this->connection($http, clock: $clock)->json(Method::Get, 'monitors');

        self::assertSame([60.0], $clock->sleeps);
    }

    public function testRetries429AtTheAnnouncedResetTimestamp(): void
    {
        // As documented: the reset as a Unix timestamp.
        $http = new MockHttpClient(
            new Response(429, '', ['x-ratelimit-reset' => '1800000025']),
            new Response(200, '{}'),
        );
        $clock = new FakeClock(1_800_000_000.0);

        $this->connection($http, clock: $clock)->json(Method::Get, 'monitors');

        self::assertSame([25.0], $clock->sleeps);
    }

    public function testPrefersRetryAfterOverTheReset(): void
    {
        $http = new MockHttpClient(
            new Response(429, '', ['retry-after' => '3', 'x-ratelimit-remaining' => '0', 'x-ratelimit-limit' => '20', 'x-ratelimit-reset' => '60']),
            new Response(200, '{}'),
            new Response(200, '{}'),
        );
        $clock = new FakeClock();
        $connection = $this->connection($http, clock: $clock);

        $connection->json(Method::Get, 'monitors');
        $connection->json(Method::Get, 'monitors');

        // Neither the retry nor the next call waits for the reset on top.
        self::assertSame([3.0], $clock->sleeps);
    }

    public function testBacksOffWhenA429AnnouncesNoWait(): void
    {
        $http = new MockHttpClient(
            new Response(429),
            new Response(429, '', ['x-ratelimit-reset' => '1700000000']),
            new Response(200, '{}'),
        );
        $clock = new FakeClock(1_800_000_000.0);

        // The second reset lies in the past, e.g. because of clock skew.
        $this->connection($http, clock: $clock)->json(Method::Get, 'monitors');

        self::assertSame([1.0, 2.0], $clock->sleeps);
    }

    public function testFallsBackToTheResetWhenRetryAfterIsInvalid(): void
    {
        $http = new MockHttpClient(
            new Response(429, '', ['retry-after' => '1.5', 'x-ratelimit-reset' => '60']),
            new Response(200, '{}'),
        );
        $clock = new FakeClock(1_800_000_000.0);

        // strtotime() read "1.5" as a time of day that had passed, so the retry went out at once.
        $this->connection($http, clock: $clock)->json(Method::Get, 'monitors');

        self::assertSame([60.0], $clock->sleeps);
    }

    public function testCapsAnExcessiveRetryAfterAndReset(): void
    {
        $http = new MockHttpClient(
            new Response(429, '', ['retry-after' => '99999']),
            new Response(429, '', ['x-ratelimit-reset' => '1800009999']),
            new Response(200, '{}'),
        );
        $clock = new FakeClock(1_800_000_000.0);

        $this->connection($http, new ClientOptions(maxRetryDelay: 30.0), $clock)->json(Method::Get, 'monitors');

        self::assertSame([30.0, 30.0], $clock->sleeps);
    }

    public function testThrowsRateLimitExceptionOnceRetriesAreUsedUp(): void
    {
        $http = new MockHttpClient(
            new Response(429, '{"message":"Too Many Requests"}', ['retry-after' => '7']),
            new Response(429, '{"message":"Too Many Requests"}', ['retry-after' => '7']),
        );
        $clock = new FakeClock();

        try {
            $this->connection($http, new ClientOptions(maxRetries: 1), $clock)->json(Method::Get, 'monitors');
            self::fail('Expected a RateLimitException.');
        } catch (RateLimitException $e) {
            self::assertSame(429, $e->statusCode);
            self::assertSame(7, $e->retryAfter);
        }

        self::assertSame(2, $http->callCount());
        self::assertSame([7.0], $clock->sleeps);
    }

    public function testRetriesIdempotentRequestsOnServerErrorsWithBackoff(): void
    {
        $http = new MockHttpClient(new Response(502), new Response(503), new Response(200, '{}'));
        $clock = new FakeClock();

        $this->connection($http, clock: $clock)->json(Method::Get, 'monitors');

        self::assertSame(3, $http->callCount());
        self::assertSame([1.0, 2.0], $clock->sleeps);
    }

    #[DataProvider('nonIdempotentMethods')]
    public function testDoesNotRetryWritesOnServerErrors(Method $method): void
    {
        $http = new MockHttpClient(new Response(500, '{"message":"boom"}'));

        try {
            $this->connection($http)->json($method, 'monitors/1', body: ['friendlyName' => 'x']);
            self::fail('Expected a ServerException.');
        } catch (ServerException $e) {
            self::assertSame(500, $e->statusCode);
        }

        self::assertSame(1, $http->callCount());
    }

    /**
     * @return iterable<string, array{Method}>
     */
    public static function nonIdempotentMethods(): iterable
    {
        yield 'POST' => [Method::Post];
        yield 'PATCH' => [Method::Patch];
    }

    public function testRetriesTransportErrorsOnlyForIdempotentRequests(): void
    {
        $http = new MockHttpClient(new TransportException('reset'), new Response(200, '{}'));
        $this->connection($http, clock: new FakeClock())->json(Method::Delete, 'monitors/1');
        self::assertSame(2, $http->callCount());

        $http = new MockHttpClient(new TransportException('reset'));
        $this->expectException(TransportException::class);
        $this->connection($http)->json(Method::Post, 'monitors');
    }

    public function testGivesUpAfterMaxRetries(): void
    {
        $http = new MockHttpClient(new Response(503), new Response(503), new Response(503));

        $this->expectException(ServerException::class);

        $this->connection($http, new ClientOptions(maxRetries: 2), new FakeClock())->json(Method::Get, 'monitors');
    }

    public function testRemembersTheLastReportedRateLimit(): void
    {
        $http = new MockHttpClient(
            new Response(200, '{}', ['x-ratelimit-limit' => '20', 'x-ratelimit-remaining' => '18', 'x-ratelimit-reset' => '60']),
            new Response(200, '{}'),
        );
        $clock = new FakeClock(1_800_000_000.0);
        $connection = $this->connection($http, clock: $clock);

        self::assertNull($connection->rateLimit);

        $connection->json(Method::Get, 'monitors');
        self::assertEquals(new RateLimitStatus(20, 18, 1_800_000_060.0), $connection->rateLimit);

        // A response without the headers keeps the last known status.
        $connection->json(Method::Get, 'monitors');
        self::assertEquals(new RateLimitStatus(20, 18, 1_800_000_060.0), $connection->rateLimit);
    }

    public function testWaitsForTheResetOnceTheQuotaIsUsedUp(): void
    {
        $http = new MockHttpClient(
            new Response(200, '{}', ['x-ratelimit-limit' => '20', 'x-ratelimit-remaining' => '0', 'x-ratelimit-reset' => '60']),
            new Response(200, '{}', ['x-ratelimit-limit' => '20', 'x-ratelimit-remaining' => '19', 'x-ratelimit-reset' => '60']),
            new Response(200, '{}'),
        );
        $clock = new FakeClock(1_800_000_000.0);
        $sentAt = [];
        $http->onSend = static function () use ($clock, &$sentAt): void {
            $sentAt[] = $clock->now();
        };
        $connection = $this->connection($http, clock: $clock);

        $connection->json(Method::Get, 'monitors');
        // Time passes between the calls, which shortens the wait.
        $clock->advance(15.0);
        $connection->json(Method::Get, 'monitors');
        $connection->json(Method::Get, 'monitors');

        self::assertSame([45.0], $clock->sleeps);
        self::assertSame([1_800_000_000.0, 1_800_000_060.0, 1_800_000_060.0], $sentAt);
    }

    public function testWaitsForAnEpochResetOnceTheQuotaIsUsedUp(): void
    {
        $http = new MockHttpClient(
            new Response(200, '{}', ['x-ratelimit-limit' => '20', 'x-ratelimit-remaining' => '0', 'x-ratelimit-reset' => '1800000042']),
            new Response(200, '{}'),
        );
        $clock = new FakeClock(1_800_000_000.0);
        $connection = $this->connection($http, clock: $clock);

        $connection->json(Method::Get, 'monitors');
        $connection->json(Method::Get, 'monitors');

        self::assertSame([42.0], $clock->sleeps);
    }

    public function testCapsTheWaitForTheReset(): void
    {
        $http = new MockHttpClient(
            new Response(200, '{}', ['x-ratelimit-limit' => '20', 'x-ratelimit-remaining' => '0', 'x-ratelimit-reset' => '3600']),
            new Response(200, '{}'),
        );
        $clock = new FakeClock();
        $connection = $this->connection($http, new ClientOptions(maxRetryDelay: 10.0), $clock);

        $connection->json(Method::Get, 'monitors');
        $connection->json(Method::Get, 'monitors');

        self::assertSame([10.0], $clock->sleeps);
    }

    public function testCanSendRightAwayDespiteAUsedUpQuota(): void
    {
        $http = new MockHttpClient(
            new Response(200, '{}', ['x-ratelimit-limit' => '20', 'x-ratelimit-remaining' => '0', 'x-ratelimit-reset' => '60']),
            new Response(200, '{}'),
        );
        $clock = new FakeClock();
        $connection = $this->connection($http, new ClientOptions(awaitRateLimitReset: false), $clock);

        $connection->json(Method::Get, 'monitors');
        $connection->json(Method::Get, 'monitors');

        self::assertSame([], $clock->sleeps);
        self::assertSame(0, $connection->rateLimit?->remaining);
    }

    public function testWaitsForTheResetAfterGivingUpOnA429(): void
    {
        $http = new MockHttpClient(
            new Response(429, '', ['x-ratelimit-limit' => '20', 'x-ratelimit-remaining' => '0', 'x-ratelimit-reset' => '60']),
            new Response(200, '{}'),
        );
        $clock = new FakeClock();
        $connection = $this->connection($http, new ClientOptions(maxRetries: 0), $clock);

        try {
            $connection->json(Method::Get, 'monitors');
            self::fail('Expected a RateLimitException.');
        } catch (RateLimitException $e) {
            self::assertSame(60, $e->retryAfter);
        }

        $connection->json(Method::Get, 'monitors');

        self::assertSame([60.0], $clock->sleeps);
    }

    public function testServerErrorRetriesAlsoWaitForAUsedUpQuota(): void
    {
        $http = new MockHttpClient(
            new Response(503, '', ['x-ratelimit-limit' => '20', 'x-ratelimit-remaining' => '0', 'x-ratelimit-reset' => '60']),
            new Response(200, '{}'),
        );
        $clock = new FakeClock();

        $this->connection($http, clock: $clock)->json(Method::Get, 'monitors');

        // The backoff counts towards the wait for the reset.
        self::assertSame([1.0, 59.0], $clock->sleeps);
    }

    /**
     * @param class-string<ApiException> $expected
     */
    #[DataProvider('errorStatuses')]
    public function testMapsErrorStatusesToExceptions(int $status, string $expected): void
    {
        $http = new MockHttpClient(new Response($status, '{"message":"Monitor not found","code":"000-004"}'));

        try {
            $this->connection($http, new ClientOptions(maxRetries: 0))->json(Method::Get, 'monitors/1', ['name' => 'secret']);
            self::fail('Expected an exception.');
        } catch (ApiException $e) {
            self::assertInstanceOf($expected, $e);
            self::assertSame($status, $e->statusCode);
            self::assertSame($status, $e->getCode());
            self::assertSame('000-004', $e->errorCode);
            self::assertSame(
                "GET https://api.uptimerobot.com/v3/monitors/1 failed with HTTP {$status}: Monitor not found (000-004)",
                $e->getMessage(),
            );
        }
    }

    /**
     * @return iterable<string, array{int, class-string<ApiException>}>
     */
    public static function errorStatuses(): iterable
    {
        yield '400' => [400, BadRequestException::class];
        yield '401' => [401, AuthenticationException::class];
        yield '403' => [403, ForbiddenException::class];
        yield '404' => [404, NotFoundException::class];
        yield '409' => [409, ConflictException::class];
        yield '422' => [422, ValidationException::class];
        yield '429' => [429, RateLimitException::class];
        yield '500' => [500, ServerException::class];
        yield '418' => [418, ApiException::class];
    }

    public function testRejectsInvalidJson(): void
    {
        $this->expectException(SerializationException::class);

        $this->connection(new MockHttpClient(new Response(200, '{broken')))->json(Method::Get, 'monitors');
    }

    public function testRejectsUnencodableBodies(): void
    {
        $this->expectException(SerializationException::class);

        $this->connection(new MockHttpClient())->json(Method::Post, 'monitors', body: ['name' => "\xB1\x31"]);
    }

    public function testRejectsInvalidBaseUri(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Connection('ftp://example.com', 'key', new ClientOptions(), new MockHttpClient(), new SpyRateLimiter());
    }

    public function testRejectsEmptyApiKey(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Connection(self::BASE_URI, ' ', new ClientOptions(), new MockHttpClient(), new SpyRateLimiter());
    }

    public function testRejectsApiKeysWithLineBreaks(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Connection(self::BASE_URI, "key\n", new ClientOptions(), new MockHttpClient(), new SpyRateLimiter());
    }

    public function testHidesTheApiKeyFromDumps(): void
    {
        $http = new MockHttpClient(new Response(200, '{}'));
        $connection = $this->connection($http);
        $connection->json(Method::Get, 'monitors');

        self::assertStringNotContainsString('secret-key', print_r($connection, true));
        self::assertStringNotContainsString('secret-key', print_r($http->requests[0], true));
    }

    public function testKeepsTheApiKeyOutOfExceptionTraces(): void
    {
        $http = new MockHttpClient(new Response(404, '{"message":"Monitor not found","code":"000-004"}'));
        $connection = $this->connection($http);

        $e = ExceptionTrace::capture(static fn(): mixed => $connection->json(Method::Get, 'monitors/1'));

        self::assertInstanceOf(NotFoundException::class, $e);
        // The exception is created in ApiException::fromResponse(), whose second argument is the request.
        self::assertInstanceOf(SensitiveParameterValue::class, $e->getTrace()[0]['args'][1] ?? null);
        self::assertStringNotContainsString('secret-key', ExceptionTrace::arguments($e));
    }

    private function connection(
        MockHttpClient $http,
        ClientOptions $options = new ClientOptions(),
        FakeClock $clock = new FakeClock(),
        SpyRateLimiter $rateLimiter = new SpyRateLimiter(),
    ): Connection {
        return new Connection(self::BASE_URI, 'secret-key', $options, $http, $rateLimiter, $clock);
    }
}
