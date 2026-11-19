<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Search;

use Nowo\WikiBundle\LinkPicker\WikiTenantPathsLinkTenantResolver;
use Nowo\WikiBundle\Routing\WikiTenantPaths;
use Nowo\WikiBundle\Search\WikiTenantPathsSearchTenantResolver;
use PHPUnit\Framework\TestCase;

/**
 * 1.46: the default current tenant: the one tenant without `tenant_paths`, none with them (no tenant context known).
 */
final class WikiTenantPathsSearchTenantResolverTest extends TestCase
{
    public function testOneTenantWithoutTenantPathsNoneWithThem(): void
    {
        self::assertSame(WikiTenantPathsLinkTenantResolver::NO_TENANT_ROW, (new WikiTenantPathsSearchTenantResolver())->currentTenant('u-1'));
        self::assertSame(WikiTenantPathsLinkTenantResolver::NO_TENANT_ROW, (new WikiTenantPathsSearchTenantResolver(new WikiTenantPaths([], [])))->currentTenant('u-1'));
        $paths = new WikiTenantPaths([['path' => '/p/{spaceSlug}/wiki', 'name_prefix' => 'p_', 'slug_requirement' => 'p-[a-z0-9]+']], []);
        self::assertNull((new WikiTenantPathsSearchTenantResolver($paths))->currentTenant('u-1'));
    }
}
