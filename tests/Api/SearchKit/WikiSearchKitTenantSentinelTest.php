<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\SearchKit;

use Nowo\SearchKitBundle\Model\SearchFilter;
use Nowo\SearchKitBundle\Model\SearchQuery;
use Nowo\WikiBundle\Search\SearchKit\WikiPageSearchDefinition;
use Nowo\WikiBundle\Tests\Api\App\ApiSearchTenants;

use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * 1.46 (audit VN-1 regression, the auditor's PoC): in `tenant: current` mode only the configuration widens the search
 * to every tenant — never a current-tenant key that equals the all-tenants marker, nor a key that only looks like a
 * tenant's, nor a `space:` filter naming another tenant's or a tenant-less space. Runs the tenant tests too.
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
}
