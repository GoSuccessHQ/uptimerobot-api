<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Tools;

use GoSuccess\UptimeRobot\Tools\ResponseHeaders;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResponseHeaders::class)]
final class ResponseHeadersTest extends TestCase
{
    public function testReadsTheStatusAndLastModified(): void
    {
        $response = ResponseHeaders::parse([
            'HTTP/1.1 200 OK',
            'Content-Type: application/yaml',
            'Last-Modified: Wed, 16 Sep 2026 14:06:18 GMT',
        ]);

        self::assertSame(200, $response->status);
        self::assertSame('Wed, 16 Sep 2026 14:06:18 GMT', $response->lastModified);
    }

    public function testReadsTheFinalResponseOfARedirect(): void
    {
        // As http_get_last_response_headers() reports a followed redirect.
        $response = ResponseHeaders::parse([
            'HTTP/1.1 301 Moved Permanently',
            'Location: https://cdn.uptimerobot.com/api/openapi.yaml',
            'Last-Modified: Mon, 01 Jan 2024 00:00:00 GMT',
            'HTTP/1.1 200 OK',
            'Content-Type: application/yaml',
        ]);

        self::assertSame(200, $response->status);
        self::assertNull($response->lastModified);
    }

    public function testReportsNoStatusWithoutAResponse(): void
    {
        $response = ResponseHeaders::parse([]);

        self::assertSame(0, $response->status);
        self::assertNull($response->lastModified);
    }
}
