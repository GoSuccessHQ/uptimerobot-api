<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Resource;

use GoSuccess\UptimeRobot\Enum\ThirdPartyProviderComponentStatus;
use GoSuccess\UptimeRobot\Enum\ThirdPartyServiceState;
use GoSuccess\UptimeRobot\Http\Response;
use GoSuccess\UptimeRobot\Resource\ThirdPartyProviderResource;
use GoSuccess\UptimeRobot\Tests\Support\MockHttpClient;
use GoSuccess\UptimeRobot\UptimeRobot;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The catalog of the providers a third party service monitor tracks, with the
 * payloads the specifications of November 9 and 16, 2026 describe (not verified
 * live).
 */
#[CoversClass(ThirdPartyProviderResource::class)]
final class ThirdPartyProviderResourceTest extends TestCase
{
    public function testSearchesTheCatalogByName(): void
    {
        $http = new MockHttpClient(new Response(200, '{"data":[{"id":4317,"name":"GitHub","logoUrl":null,"statusPageUrl":"https://www.githubstatus.com",'
            . '"state":"minor","matchedComponent":{"id":555,"name":"Actions"},"isTracked":true},'
            . '{"id":7,"name":"GitLab","logoUrl":"https://example.com/gitlab.png","statusPageUrl":"https://status.gitlab.com","state":"operational",'
            . '"matchedComponent":null,"isTracked":false}]}'));

        $providers = self::client($http)->thirdPartyProviders->search('git hub');

        self::assertSame('https://api.uptimerobot.com/v3/third-party-providers?search=git%20hub', $http->requests[0]->uri);
        self::assertCount(2, $providers);
        self::assertSame(4317, $providers[0]->id);
        self::assertNull($providers[0]->logoUrl);
        self::assertSame(ThirdPartyServiceState::Minor, $providers[0]->state);
        self::assertSame('Actions', $providers[0]->matchedComponent?->name);
        self::assertTrue($providers[0]->isTracked);
        self::assertNull($providers[1]->matchedComponent);
        self::assertFalse($providers[1]->isTracked);
    }

    public function testReadsAProviderWithItsRegionsAndItsComponentTree(): void
    {
        $http = new MockHttpClient(new Response(200, '{"id":4317,"name":"GitHub","logoUrl":null,"statusPageUrl":"https://www.githubstatus.com","state":"operational",'
            . '"components":[{"id":555,"name":"Actions","status":"unknown","region":null,"global":true,'
            . '"children":[{"id":556,"name":"Hosted runners","status":"major","region":{"code":"eu-west-1","name":"Europe (Ireland)"},"global":false}]}],'
            . '"regions":[{"code":"eu-west-1","name":"Europe (Ireland)"},{"code":"us-east-1","name":"US East (N. Virginia)"}]}'));

        $provider = self::client($http)->thirdPartyProviders->get(4317);

        self::assertSame('https://api.uptimerobot.com/v3/third-party-providers/4317', $http->requests[0]->uri);
        self::assertSame(ThirdPartyServiceState::Operational, $provider->state);
        self::assertSame(ThirdPartyProviderComponentStatus::Unknown, $provider->components[0]->status);
        self::assertSame(556, $provider->components[0]->children[0]->id);
        self::assertSame(ThirdPartyProviderComponentStatus::Major, $provider->components[0]->children[0]->status);
        // A global component belongs to no region.
        self::assertTrue($provider->components[0]->global);
        self::assertNull($provider->components[0]->region);
        $child = $provider->components[0]->children[0];
        self::assertFalse($child->global);
        self::assertNotNull($child->region);
        self::assertSame('eu-west-1', $child->region->code);
        self::assertSame('Europe (Ireland)', $child->region->name);
        self::assertCount(2, $provider->regions);
        self::assertSame('us-east-1', $provider->regions[1]->code);
    }

    public function testSearchesTheComponentsOfAProvider(): void
    {
        $http = new MockHttpClient(
            new Response(200, '{"data":[{"id":555,"name":"Lambda","status":"operational","region":null,"global":true,'
                . '"children":[{"id":556,"name":"Lambda Invocations","status":"minor","region":{"code":"us-west-2","name":"US West (Oregon)"},"global":false}]}],'
                . '"regionLike":false}'),
            new Response(200, '{"data":[],"regionLike":true}'),
        );
        $providers = self::client($http)->thirdPartyProviders;

        $match = $providers->searchComponents(7, 'lambda oregon');

        self::assertSame('https://api.uptimerobot.com/v3/third-party-providers/7/components?search=lambda%20oregon', $http->requests[0]->uri);
        self::assertCount(1, $match->data);
        self::assertSame('Lambda', $match->data[0]->name);
        // The matching leaves come back under their parent.
        self::assertSame(556, $match->data[0]->children[0]->id);
        self::assertSame('us-west-2', $match->data[0]->children[0]->region?->code);
        self::assertFalse($match->regionLike);

        // A search term that reads like a region, and the region filter.
        $region = $providers->searchComponents(7, 'oregon', 'us-west-2');

        self::assertSame('https://api.uptimerobot.com/v3/third-party-providers/7/components?search=oregon&region=us-west-2', $http->requests[1]->uri);
        self::assertSame([], $region->data);
        self::assertTrue($region->regionLike);
    }

    private static function client(MockHttpClient $http): UptimeRobot
    {
        return new UptimeRobot('secret', httpClient: $http);
    }
}
