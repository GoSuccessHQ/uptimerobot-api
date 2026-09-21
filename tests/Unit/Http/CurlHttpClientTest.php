<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Http;

use GoSuccess\UptimeRobot\ClientOptions;
use GoSuccess\UptimeRobot\Exception\NotFoundException;
use GoSuccess\UptimeRobot\Exception\TransportException;
use GoSuccess\UptimeRobot\Http\Connection;
use GoSuccess\UptimeRobot\Http\CurlHttpClient;
use GoSuccess\UptimeRobot\Http\FileUpload;
use GoSuccess\UptimeRobot\Http\Method;
use GoSuccess\UptimeRobot\Http\Multipart;
use GoSuccess\UptimeRobot\Http\Request;
use GoSuccess\UptimeRobot\Http\Response;
use GoSuccess\UptimeRobot\RateLimit\NullRateLimiter;
use GoSuccess\UptimeRobot\Tests\Support\FakeClock;
use GoSuccess\UptimeRobot\Tests\Support\LocalServer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the real cURL transport against a local PHP web server.
 */
#[CoversClass(CurlHttpClient::class)]
final class CurlHttpClientTest extends TestCase
{
    private static ?LocalServer $server = null;

    public static function setUpBeforeClass(): void
    {
        self::$server = new LocalServer();
    }

    public static function tearDownAfterClass(): void
    {
        self::$server?->stop();
        self::$server = null;
    }

    public function testGetReturnsStatusBodyAndLowerCasedHeaders(): void
    {
        $response = $this->send(new Request(Method::Get, $this->uri('/echo?a=1'), ['X-Test' => 'yes']));

        self::assertSame(200, $response->statusCode);
        self::assertTrue($response->isSuccessful);
        self::assertSame('one, two', $response->header('X-CUSTOM'));
        self::assertSame('19', $response->header('x-ratelimit-remaining'));
        self::assertSame('OK', $response->reasonPhrase);

        $echo = $this->decode($response);
        self::assertSame('GET', $echo['method']);
        self::assertSame('a=1', $echo['query']);
        self::assertIsArray($echo['headers']);
        self::assertSame('yes', $echo['headers']['x-test']);
    }

    public function testBodylessPostSendsZeroContentLengthWithoutFormContentType(): void
    {
        $echo = $this->decode($this->send(new Request(Method::Post, $this->uri('/echo'))));

        self::assertIsArray($echo['headers']);
        self::assertSame('0', $echo['headers']['content-length']);
        self::assertArrayNotHasKey('content-type', $echo['headers']);
    }

    public function testStringBodyIsSentWithTheGivenContentType(): void
    {
        $echo = $this->decode($this->send(new Request(
            Method::Patch,
            $this->uri('/echo'),
            ['Content-Type' => 'application/json'],
            '{"friendlyName":"Site"}',
        )));

        self::assertSame('PATCH', $echo['method']);
        self::assertSame('{"friendlyName":"Site"}', $echo['body']);
        self::assertIsArray($echo['headers']);
        self::assertSame('application/json', $echo['headers']['content-type']);
    }

    #[DataProvider('formMethods')]
    public function testMultipartFormsAreReadByAFormParser(Method $method): void
    {
        $logo = random_bytes(5_000);
        $form = Multipart::encode(
            ['friendlyName' => 'Status "page"', 'monitorIds' => [1, 2], 'tags' => [], 'hideUrlLinks' => true],
            ['logo' => new FileUpload('logo.png', $logo, 'image/png')],
        );

        $echo = $this->decode($this->send(new Request($method, $this->uri('/form'), ['Content-Type' => $form->contentType], $form->body)));

        self::assertSame($method->value, $echo['method']);
        self::assertSame(
            ['friendlyName' => 'Status "page"', 'monitorIds' => ['1', '2'], 'tags' => [''], 'hideUrlLinks' => 'true'],
            $echo['fields'],
        );
        self::assertSame(
            ['logo' => ['name' => 'logo.png', 'type' => 'image/png', 'size' => 5_000, 'sha256' => hash('sha256', $logo)]],
            $echo['files'],
        );
    }

    /**
     * @return iterable<string, array{Method}>
     */
    public static function formMethods(): iterable
    {
        yield 'POST' => [Method::Post];
        yield 'PATCH' => [Method::Patch];
    }

    public function testErrorResponsesAreReturned(): void
    {
        $response = $this->send(new Request(Method::Get, $this->uri('/status/404')));

        self::assertSame(404, $response->statusCode);
        self::assertFalse($response->isSuccessful);
        self::assertSame('{"message":"Status 404","code":"000-004"}', $response->body);
    }

    public function testReusesTheHandleAcrossRequests(): void
    {
        $client = new CurlHttpClient();

        $first = $client->send(new Request(Method::Post, $this->uri('/echo'), ['Content-Type' => 'text/plain'], 'first'));
        $second = $client->send(new Request(Method::Get, $this->uri('/echo')));

        self::assertSame('first', $this->decode($first)['body']);
        // No option of the first request (method, body, headers) leaks into the second.
        $echo = $this->decode($second);
        self::assertSame('GET', $echo['method']);
        self::assertSame('', $echo['body']);
        self::assertIsArray($echo['headers']);
        self::assertArrayNotHasKey('content-type', $echo['headers']);
    }

    public function testWorksBehindAConnection(): void
    {
        self::assertNotNull(self::$server);
        $connection = new Connection(
            self::$server->baseUri,
            'secret-key',
            new ClientOptions(),
            new CurlHttpClient(),
            new NullRateLimiter(),
            new FakeClock(1_800_000_000.0),
        );

        $echo = $connection->json(Method::Get, 'echo', ['cursor' => 42]);

        self::assertIsArray($echo);
        self::assertSame('cursor=42', $echo['query']);
        self::assertIsArray($echo['headers']);
        self::assertSame('Bearer secret-key', $echo['headers']['authorization']);
        $status = $connection->rateLimit;
        self::assertNotNull($status);
        self::assertSame(19, $status->remaining);
        self::assertSame(1_800_000_060.0, $status->resetAt);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('failed with HTTP 404 Not Found: Status 404 (000-004)');

        $connection->json(Method::Get, 'status/404');
    }

    public function testConnectionFailureThrowsTransportException(): void
    {
        $port = LocalServer::closedPort();

        $this->expectException(TransportException::class);

        new CurlHttpClient(connectTimeout: 1.0)->send(new Request(Method::Get, "http://127.0.0.1:{$port}/"));
    }

    public function testRequestTimeoutOverridesTheDefault(): void
    {
        $this->expectException(TransportException::class);

        $this->send(new Request(Method::Get, $this->uri('/sleep/2000'), timeout: 0.3));
    }

    public function testTransportErrorMessageOmitsTheQueryString(): void
    {
        $port = LocalServer::closedPort();

        try {
            new CurlHttpClient(connectTimeout: 1.0)->send(new Request(Method::Get, "http://127.0.0.1:{$port}/monitors?name=secret"));
            self::fail('Expected a TransportException.');
        } catch (TransportException $e) {
            self::assertStringNotContainsString('secret', $e->getMessage());
        }
    }

    /**
     * @return non-empty-string
     */
    private function uri(string $path): string
    {
        self::assertNotNull(self::$server);

        return self::$server->baseUri . $path;
    }

    private function send(Request $request): Response
    {
        return new CurlHttpClient()->send($request);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decode(Response $response): array
    {
        $decoded = json_decode($response->body, true);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
