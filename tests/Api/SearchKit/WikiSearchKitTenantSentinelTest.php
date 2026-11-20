<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\SearchKit;

use Nowo\SearchKitBundle\Event\SearchItemVisibilityEvent;
use Nowo\SearchKitBundle\Event\SearchKitEvents;
use Nowo\SearchKitBundle\Model\SearchFilter;
use Nowo\SearchKitBundle\Model\SearchItem;
use Nowo\SearchKitBundle\Model\SearchQuery;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Event\WikiSearchSpacesEvent;
use Nowo\WikiBundle\Search\SearchKit\WikiPageSearchDefinition;
use Nowo\WikiBundle\Tests\Api\App\ApiSearchTenants;

use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * 1.46 (audit VN-1 regression, the auditor's PoC), 1.47 (lists of current tenants): in `tenant: current` mode only the
 * configuration widens the search to every tenant — never a current-tenant key that equals the all-tenants marker (alone
 * or in a list), nor a key that only looks like a tenant's, nor a `space:` filter or a recent naming a space outside the
 * current tenants. A list searches the union of its tenants. Runs the tenant tests too.
 */
final class WikiSearchKitTenantSentinelTest extends WikiSearchKitTenantTest
{
    public function testTheAllTenantsMarkerAsCurrentTenantSearchesNothing(): void
    {
        ApiSearchTenants::$current['u-ana'] = WikiPageSearchDefinition::TENANT_ALL;

        self::assertSame([], $this->titles($this->search()), 'The key "*" must not widen the search to every tenant.');
    }

    public function testLookAlikeTenantKeysSearchNothing(): void
    {
        foreach (['ORG-1', 'org-1 ', "org-1\u{200B}", 'org-'] as $key) {
            ApiSearchTenants::$current['u-ana'] = $key;
            self::assertSame([], $this->titles($this->search()), json_encode($key, JSON_THROW_ON_ERROR));
        }
    }

    public function testASpaceFilterCannotEscapeTheCurrentTenant(): void
    {
        ApiSearchTenants::$current['u-ana'] = 'org-1';
        $this->signIn();
        foreach (['ops', 'lab'] as $space) {
            $query = new SearchQuery('tenant', null, 10, null, false, 'u-ana', [new SearchFilter('space', $space)], 'en');
            self::assertNull($this->provider()->search($query), $space);
        }
    }

    public function testAListSearchesTheUnionOfItsTenantsOnly(): void
    {
        ApiSearchTenants::$lists['u-ana'] = ['org-2', 'org-1'];
        $tenants                          = [];
        $keys                             = [];
        $this->listen(SearchKitEvents::ITEM_VISIBILITY, static function (SearchItemVisibilityEvent $event) use (&$tenants): void {
            $tenants[] = $event->tenant;
        });
        $this->listen(WikiEvents::SEARCH_KIT_SPACES, static function (WikiSearchSpacesEvent $event) use (&$keys): void {
            $keys[] = $event->tenants;
        });

        // The tenant-less space is in no tenant of the list: never searched.
        self::assertSame(['Tenant page eng', 'Tenant page ops'], $this->titles($this->search()));
        // The SearchKit tenant identifies the list whatever its order.
        self::assertSame([WikiPageSearchDefinition::tenantId(['org-1', 'org-2'])], $tenants);
        self::assertSame([['org-2', 'org-1']], $keys);
    }

    public function testLookAlikeKeysInAListSearchNothing(): void
    {
        ApiSearchTenants::$lists['u-ana'] = ['ORG-1', 'org-1 ', "org-2\u{200B}", 'org-', ' org-2'];

        self::assertNull($this->search());
    }

    public function testAnEmptyListSearchesNothing(): void
    {
        ApiSearchTenants::$lists['u-ana'] = [];

        self::assertNull($this->search());
    }

    public function testTheAllTenantsMarkerInsideAListIsDropped(): void
    {
        ApiSearchTenants::$lists['u-ana'] = ['*', 'org-1'];
        self::assertSame(['Tenant page eng'], $this->titles($this->search()));

        ApiSearchTenants::$lists['u-ana'] = ['*', ''];
        self::assertNull($this->search());
    }

    public function testASpaceFilterOrScopeOutsideTheListFindsNothing(): void
    {
        $this->signIn();
        $query = static fn (array $filters, ?string $scope = null): SearchQuery => new SearchQuery('tenant', null, 10, $scope, false, 'u-ana', $filters, 'en');

        ApiSearchTenants::$lists['u-ana'] = ['org-1'];
        self::assertNull($this->provider()->search($query([new SearchFilter('space', 'ops')])));
        self::assertNull($this->provider()->search($query([], 'owner-2')));
        ApiSearchTenants::$lists['u-ana'] = ['org-1', 'org-2'];
        self::assertNull($this->provider()->search($query([new SearchFilter('space', 'lab')])));
        self::assertSame(['Tenant page ops'], $this->titles($this->provider()->search($query([new SearchFilter('space', 'ops')]))));
    }

    public function testRecentsOutsideTheListAreDropped(): void
    {
        $ops = $this->reference('ops', $this->slug('Tenant page ops'));
        $lab = $this->reference('lab', $this->slug('Tenant page lab'));
        $this->signIn();

        ApiSearchTenants::$lists['u-ana'] = ['org-1'];
        self::assertNull($this->provider()->resolve($ops, 'u-ana', null));
        ApiSearchTenants::$lists['u-ana'] = ['org-1', 'org-2'];
        self::assertInstanceOf(SearchItem::class, $this->provider()->resolve($ops, 'u-ana', null));
        self::assertNull($this->provider()->resolve($lab, 'u-ana', null));
    }

    public function testATenantValueThatIsNotTheViewersCurrentOneReadsNoSpace(): void
    {
        $definition = self::getContainer()->get('test.wiki_search_definition');
        self::assertInstanceOf(WikiPageSearchDefinition::class, $definition);
        $this->signIn();
        ApiSearchTenants::$lists['u-ana'] = ['org-1'];
        $query                            = new SearchQuery('tenant', null, 10, null, false, 'u-ana', [], 'en');

        self::assertNotSame([], $definition->baseQuery('org-1', $query)->getQuery()->getResult());
        // Another tenant's value (stale or forged) is re-derived against the viewer's current keys: no space at all.
        self::assertSame([], $definition->baseQuery('org-2', $query)->getQuery()->getResult());
        self::assertSame([], $definition->baseQuery(WikiPageSearchDefinition::tenantId(['org-1', 'org-2']) ?? '', $query)->getQuery()->getResult());
    }
}
