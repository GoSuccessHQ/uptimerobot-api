<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Tools\Generator;

use GoSuccess\UptimeRobot\Tests\Support\Generator\GeneratorFixture;
use GoSuccess\UptimeRobot\Tools\Generator\Analysis;
use GoSuccess\UptimeRobot\Tools\Generator\Registry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * A `oneOf` becomes a union only when every variant has a single-value
 * discriminator and the configuration names exactly those values.
 */
#[CoversClass(Registry::class)]
final class UnionChecksTest extends TestCase
{
    public function testGeneratesAnInterfaceAndOneModelPerVariant(): void
    {
        $analysis = $this->request(self::variants(), ['HTTP' => 'HttpThingCreate', 'PING' => 'PingThingCreate']);
        $union = array_values($analysis->registry->unions)[0];

        self::assertSame('GoSuccess\\UptimeRobot\\Tests\\Fixture\\Unused\\Model\\ThingCreate', $union->interface);
        self::assertTrue($union->request);
        self::assertFalse($union->response);
        self::assertSame(['HTTP', 'PING'], array_keys($union->variants));

        $variant = $analysis->registry->models[$union->variants['HTTP']];
        self::assertSame('HTTP', $variant->discriminatorValue);
        self::assertSame(['url'], array_map(static fn($property): string => $property->jsonName, $variant->properties));
        self::assertSame('thingCreate', $analysis->resources[0]->methods[0]->parameters[0]->phpName);
    }

    public function testChecksTheDiscriminatorMappingOfTheSpecification(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unions.ThingsController_create.body: the discriminator mapping assigns PING to #/components/schemas/HttpThingDto, but the variant with that value is PingThingDto.');

        $this->request(self::variants(['HTTP' => '#/components/schemas/HttpThingDto', 'PING' => '#/components/schemas/HttpThingDto']), ['HTTP' => 'HttpThingCreate', 'PING' => 'PingThingCreate']);
    }

    public function testChecksTheDiscriminatorPropertyOfTheSpecification(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unions.ThingsController_create.body: the specification discriminates by kind, not by type.');

        $this->request(self::variants([], 'kind'), ['HTTP' => 'HttpThingCreate', 'PING' => 'PingThingCreate']);
    }

    public function testRequiresAClassForEveryVariant(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unions.ThingsController_create.body: "variants" must name exactly the discriminator values HTTP, PING.');

        $this->request(self::variants(), ['HTTP' => 'HttpThingCreate', 'DNS' => 'DnsThingCreate']);
    }

    public function testRequiresASingleValueDiscriminatorInEveryVariant(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unions.ThingsController_create.body: variant PingThingDto has no type with a single value.');

        $this->request(self::variants(pingValues: ['PING', 'ICMP']), ['HTTP' => 'HttpThingCreate', 'PING' => 'PingThingCreate']);
    }

    public function testRequiresAFallbackForUnionsReadFromResponses(): void
    {
        $spec = [
            'paths' => ['/things' => ['get' => [
                'operationId' => 'ThingsController_get',
                'responses' => ['200' => ['description' => '', 'content' => ['application/json' => ['schema' => ['oneOf' => [
                    ['type' => 'object', 'properties' => ['type' => ['type' => 'string', 'enum' => ['A']]]],
                    ['type' => 'object', 'properties' => ['type' => ['type' => 'string', 'enum' => ['B']]]],
                ]]]]]],
            ]]],
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unions.ThingsController_get.response: the union is read from responses and needs a "fallback" model for unknown discriminator values.');

        GeneratorFixture::analyze($spec, [
            'unions' => ['ThingsController_get.response' => ['interface' => 'Thing', 'discriminator' => 'type', 'variants' => ['A' => 'AThing', 'B' => 'BThing']]],
            'resources' => ['things' => ['class' => 'ThingResource', 'description' => 'Things.', 'methods' => ['get' => ['operation' => 'ThingsController_get']]]],
        ]);
    }

    public function testRejectsAFallbackThatWouldNeverBeUsed(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unions.ThingsController_create.body: the union is only sent, so its "fallback" would never be used.');

        $this->request(self::variants(), ['HTTP' => 'HttpThingCreate', 'PING' => 'PingThingCreate'], ['fallback' => 'UnknownThingCreate']);
    }

    public function testRejectsEnvelopesWithFurtherProperties(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unions.ThingsController_create.body: HttpThingDto has properties besides type and data (url), so it cannot be flattened.');

        $this->request(self::variants(withData: true), ['HTTP' => 'HttpThingCreate', 'PING' => 'PingThingCreate'], ['envelope' => 'data']);
    }

    /**
     * @param array<string, mixed>  $spec
     * @param array<string, string> $variants
     * @param array<string, mixed>  $union
     */
    private function request(array $spec, array $variants, array $union = []): Analysis
    {
        return GeneratorFixture::analyze($spec, [
            'naming' => ['stripSuffixes' => ['Dto']],
            'unions' => ['ThingsController_create.body' => ['interface' => 'ThingCreate', 'discriminator' => 'type', 'variants' => $variants, ...$union]],
            'resources' => ['things' => ['class' => 'ThingResource', 'description' => 'Things.', 'methods' => ['create' => ['operation' => 'ThingsController_create']]]],
        ]);
    }

    /**
     * POST /things with a oneOf of HttpThingDto and PingThingDto.
     *
     * @param array<string, string> $mapping    The discriminator mapping, if any.
     * @param list<string>          $pingValues The values of PingThingDto's type.
     * @param bool                  $withData   Whether both variants also have a "data" object.
     *
     * @return array<string, mixed>
     */
    private static function variants(array $mapping = [], ?string $discriminator = null, array $pingValues = ['PING'], bool $withData = false): array
    {
        $data = $withData ? ['data' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]]] : [];

        $body = ['oneOf' => [['$ref' => '#/components/schemas/HttpThingDto'], ['$ref' => '#/components/schemas/PingThingDto']]];

        if ($mapping !== [] || $discriminator !== null) {
            $body['discriminator'] = ['propertyName' => $discriminator ?? 'type', 'mapping' => $mapping];
        }

        return [
            'paths' => ['/things' => ['post' => [
                'operationId' => 'ThingsController_create',
                'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => $body]]],
                'responses' => ['201' => ['description' => '']],
            ]]],
            'components' => ['schemas' => [
                'HttpThingDto' => ['type' => 'object', 'properties' => ['type' => ['type' => 'string', 'enum' => ['HTTP']], 'url' => ['type' => 'string'], ...$data], 'required' => ['type', 'url']],
                'PingThingDto' => ['type' => 'object', 'properties' => ['type' => ['type' => 'string', 'enum' => $pingValues], ...$data], 'required' => ['type']],
            ]],
        ];
    }
}
