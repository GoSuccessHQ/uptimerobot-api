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
 * payloads the specification of November 9, 2026 describes (not verified live).
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

    public function testReadsAProviderWithItsComponentTree(): void
    {
        $http = new MockHttpClient(new Response(200, '{"id":4317,"name":"GitHub","logoUrl":null,"statusPageUrl":"https://www.githubstatus.com","state":"operational",'
            . '"components":[{"id":555,"name":"Actions","status":"unknown","children":[{"id":556,"name":"Hosted runners","status":"major"}]}]}'));

        $provider = self::client($http)->thirdPartyProviders->get(4317);

        self::assertSame('https://api.uptimerobot.com/v3/third-party-providers/4317', $http->requests[0]->uri);
        self::assertSame(ThirdPartyServiceState::Operational, $provider->state);
        self::assertSame(ThirdPartyProviderComponentStatus::Unknown, $provider->components[0]->status);
        self::assertSame(556, $provider->components[0]->children[0]->id);
        self::assertSame(ThirdPartyProviderComponentStatus::Major, $provider->components[0]->children[0]->status);
    }

    private static function client(MockHttpClient $http): UptimeRobot
    {
        return new UptimeRobot('secret', httpClient: $http);
    }
}
