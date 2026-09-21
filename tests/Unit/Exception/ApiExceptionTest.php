<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Exception;

use GoSuccess\UptimeRobot\Exception\ApiException;
use GoSuccess\UptimeRobot\Exception\AuthenticationException;
use GoSuccess\UptimeRobot\Exception\ErrorDetails;
use GoSuccess\UptimeRobot\Exception\NotFoundException;
use GoSuccess\UptimeRobot\Exception\RateLimitException;
use GoSuccess\UptimeRobot\Exception\UptimeRobotException;
use GoSuccess\UptimeRobot\Http\Method;
use GoSuccess\UptimeRobot\Http\Request;
use GoSuccess\UptimeRobot\Http\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiException::class)]
#[CoversClass(ErrorDetails::class)]
#[CoversClass(RateLimitException::class)]
final class ApiExceptionTest extends TestCase
{
    #[DataProvider('errorBodies')]
    public function testNormalizesTheErrorBodies(string $body, string $message, ?string $errorCode): void
    {
        $exception = ApiException::fromResponse(new Response(400, $body), $this->request());

        self::assertSame($message, $exception->getMessage());
        self::assertSame($errorCode, $exception->errorCode);
        self::assertSame($body, $exception->responseBody);
    }

    /**
     * @return iterable<string, array{string, string, string|null}>
     */
    public static function errorBodies(): iterable
    {
        $prefix = 'GET https://api.uptimerobot.com/v3/monitors/1 failed with HTTP 400';

        // Observed live.
        yield 'api error' => ['{"message":"Monitor not found","code":"000-004"}', "{$prefix}: Monitor not found (000-004)", '000-004'];
        yield 'invalid token' => ['{"message":"Invalid token.","code":"003-005"}', "{$prefix}: Invalid token. (003-005)", '003-005'];
        yield 'unknown route' => ['{"message":"Cannot GET /v3/x","error":"Not Found","statusCode":404}', "{$prefix}: Cannot GET /v3/x", null];
        yield 'validation' => [
            '{"message":["name must be shorter than or equal to 255 characters","name must be a string"],"error":"Bad Request","statusCode":400}',
            "{$prefix}: name must be shorter than or equal to 255 characters; name must be a string",
            null,
        ];

        // Defensive fallbacks.
        yield 'error only' => ['{"error":"Bad Request","statusCode":400}', "{$prefix}: Bad Request", null];
        yield 'numeric code' => ['{"message":"Nope","code":42}', "{$prefix}: Nope (42)", '42'];
        yield 'blank message' => ['{"message":" ","error":"Bad Request"}', "{$prefix}: Bad Request", null];
        yield 'list without strings' => ['{"message":[1,{"a":2}],"error":"Bad Request"}', "{$prefix}: Bad Request", null];
        yield 'plain text' => ['Bad Gateway', "{$prefix}: Bad Gateway", null];
        yield 'json list' => ['["x"]', "{$prefix}", null];
        yield 'empty' => ['', $prefix, null];
    }

    public function testIncludesTheReasonPhraseButNotTheQuery(): void
    {
        $exception = ApiException::fromResponse(
            new Response(404, '{"message":"Monitor not found","code":"000-004"}', [], 'Not Found'),
            new Request(Method::Delete, 'https://api.uptimerobot.com/v3/monitors/1?name=secret'),
        );

        self::assertInstanceOf(NotFoundException::class, $exception);
        self::assertInstanceOf(UptimeRobotException::class, $exception);
        self::assertSame('DELETE https://api.uptimerobot.com/v3/monitors/1 failed with HTTP 404 Not Found: Monitor not found (000-004)', $exception->getMessage());
    }

    public function testMapsAnInvalidKeyToAuthenticationException(): void
    {
        $exception = ApiException::fromResponse(new Response(401, '{"message":"Invalid token.","code":"003-005"}'), $this->request());

        self::assertInstanceOf(AuthenticationException::class, $exception);
        self::assertSame(401, $exception->statusCode);
    }

    public function testShortensHugeBodiesWithoutBreakingUtf8(): void
    {
        $body = str_repeat('ä', 400);
        $exception = ApiException::fromResponse(new Response(502, $body), $this->request());

        self::assertTrue(mb_check_encoding($exception->getMessage(), 'UTF-8'));
        self::assertStringEndsWith('…', $exception->getMessage());
        self::assertLessThan(600, \strlen($exception->getMessage()));
        self::assertSame($body, $exception->responseBody);
    }

    public function testDecodesTheBody(): void
    {
        $exception = ApiException::fromResponse(new Response(400, '{"message":"x"}'), $this->request());

        self::assertSame(['message' => 'x'], $exception->decodedBody());
        self::assertNull(ApiException::fromResponse(new Response(502), $this->request())->decodedBody());
    }

    public function testParsesRetryAfter(): void
    {
        $now = 1_800_000_000.0;

        self::assertSame(30, RateLimitException::parseRetryAfter('30', $now));
        self::assertNull(RateLimitException::parseRetryAfter('', $now));
        self::assertNull(RateLimitException::parseRetryAfter('soon', $now));
        self::assertSame(0, RateLimitException::parseRetryAfter('Wed, 21 Oct 2015 07:28:00 GMT', $now));
        self::assertSame(120, RateLimitException::parseRetryAfter(gmdate('D, d M Y H:i:s', 1_800_000_120) . ' GMT', $now));

        $future = gmdate('D, d M Y H:i:s', time() + 120) . ' GMT';
        self::assertGreaterThanOrEqual(118, RateLimitException::parseRetryAfter($future));
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('rateLimitHeaders')]
    public function testRateLimitExceptionCarriesTheAnnouncedWait(array $headers, ?int $retryAfter): void
    {
        $exception = ApiException::fromResponse(new Response(429, '', $headers), $this->request(), 1_800_000_000.0);

        self::assertInstanceOf(RateLimitException::class, $exception);
        self::assertSame($retryAfter, $exception->retryAfter);
    }

    /**
     * @return iterable<string, array{array<string, string>, int|null}>
     */
    public static function rateLimitHeaders(): iterable
    {
        yield 'retry-after' => [['retry-after' => '7', 'x-ratelimit-reset' => '60'], 7];
        yield 'reset in seconds' => [['x-ratelimit-reset' => '60'], 60];
        yield 'reset as timestamp' => [['x-ratelimit-reset' => '1800000012.2'], 13];
        yield 'reset in the past' => [['x-ratelimit-reset' => '1700000000'], null];
        yield 'nothing' => [[], null];
    }

    private function request(): Request
    {
        return new Request(Method::Get, 'https://api.uptimerobot.com/v3/monitors/1');
    }
}
