<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\SearchKit;

use Nowo\SearchKitBundle\Event\SearchItemVisibilityEvent;
use Nowo\SearchKitBundle\Event\SearchKitEvents;
use Nowo\SearchKitBundle\Model\SearchItem;
use Nowo\WikiBundle\Search\SearchKit\WikiPageSearchDefinition;
use Nowo\WikiBundle\Tests\Api\App\ApiSearchTenants;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * 1.46: `search_kit_provider.tenant: all` widens the palette to every space the viewer may read (the 1.44 / 1.45
 * behaviour), whatever the current tenant; the SearchKit tenant is then `*`.
 */
final class WikiSearchKitAllTenantsTest extends WikiSearchKitTenantTest
{
    /** @var array<string, mixed> */
    protected static array $wikiConfig = [
        'search_kit_provider'      => ['enabled' => true, 'tenant_resolver' => 'app.search_tenants', 'tenant' => 'all'],
        'team_membership_resolver' => 'app.teams',
    ];

    public function testOnlyTheCurrentTenantsSpacesAreSearched(): void
    {
        foreach (['org-1', null] as $current) {
            ApiSearchTenants::$current['u-ana'] = $current;
            self::assertSame(['Tenant page eng', 'Tenant page lab', 'Tenant page ops'], $this->titles($this->search()));
        }
    }

    public function testTheHostHooksSeeTheCurrentTenant(): void
    {
        $tenants    = [];
        $dispatcher = self::getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $listener = static function (SearchItemVisibilityEvent $event) use (&$tenants): void {
            $tenants[] = $event->tenant;
        };
        $dispatcher->addListener(SearchKitEvents::ITEM_VISIBILITY, $listener);
        $this->search();
        $dispatcher->removeListener(SearchKitEvents::ITEM_VISIBILITY, $listener);

        self::assertSame([WikiPageSearchDefinition::TENANT_ALL], $tenants);
    }

    public function testRecentsOfAnotherTenantAreDropped(): void
    {
        $this->signIn();
        ApiSearchTenants::$current['u-ana'] = 'org-1';
        self::assertInstanceOf(SearchItem::class, $this->provider()->resolve($this->reference('ops', $this->slug('Tenant page ops')), 'u-ana', null));
    }
}
