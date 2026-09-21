<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Resource;

use DateTimeImmutable;
use GoSuccess\UptimeRobot\ClientOptions;
use GoSuccess\UptimeRobot\Exception\SerializationException;
use GoSuccess\UptimeRobot\Http\Connection;
use GoSuccess\UptimeRobot\Http\Method;
use GoSuccess\UptimeRobot\Resource\AbstractResource;
use GoSuccess\UptimeRobot\Tests\Support\ExampleModel;
use GoSuccess\UptimeRobot\Tests\Support\ExampleResource;
use GoSuccess\UptimeRobot\Tests\Support\MockHttpClient;
use GoSuccess\UptimeRobot\Tests\Support\SpyRateLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractResource::class)]
final class AbstractResourceTest extends TestCase
{
    public function testEncodesPathSegments(): void
    {
        $resource = $this->resource();

        self::assertSame('352577094135060139', $resource->segmentOf('352577094135060139'));
        self::assertSame('a%2Fb%3Fc', $resource->segmentOf('a/b?c'));
        self::assertSame('42', $resource->segmentOf(42));
        self::assertSame('GET', $resource->segmentOf(Method::Get));
        self::assertSame('2026-09-18T12%3A30%3A00Z', $resource->segmentOf(new DateTimeImmutable('2026-09-18T14:30:00+02:00')));
    }

    public function testMapsResponsesOntoModels(): void
    {
        self::assertSame(['data' => []], $this->resource()->object(['data' => []]));
        self::assertSame('a', $this->resource()->model(ExampleModel::class, ['name' => 'a'])->name);
        self::assertSame(
            ['a', 'b'],
            array_map(static fn(ExampleModel $model): ?string => $model->name, $this->resource()->models(ExampleModel::class, [['name' => 'a'], ['name' => 'b']])),
        );
    }

    public function testRejectsAnythingButAnObject(): void
    {
        $this->expectException(SerializationException::class);
        $this->expectExceptionMessage('Expected a JSON object, got string.');

        $this->resource()->object('oops');
    }

    public function testRejectsAModelThatIsNoObject(): void
    {
        $this->expectException(SerializationException::class);

        $this->resource()->model(ExampleModel::class, null);
    }

    public function testRejectsAListThatIsAnObject(): void
    {
        $this->expectException(SerializationException::class);

        $this->resource()->models(ExampleModel::class, ['data' => []]);
    }

    public function testRejectsAListWithScalars(): void
    {
        $this->expectException(SerializationException::class);

        $this->resource()->models(ExampleModel::class, [['name' => 'a'], 5]);
    }

    private function resource(): ExampleResource
    {
        return new ExampleResource(new Connection('https://api.uptimerobot.com/v3', 'key', new ClientOptions(), new MockHttpClient(), new SpyRateLimiter()));
    }
}
