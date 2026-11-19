<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Search;

use Nowo\WikiBundle\LinkPicker\WikiTenantPathsLinkTenantResolver;
use Nowo\WikiBundle\Routing\WikiTenantPaths;

/**
 * Default {@see WikiSearchTenantResolverInterface} (1.46), paired with the default tenant keys of
 * {@see WikiTenantPathsLinkTenantResolver}: without `tenant_paths` every space belongs to one tenant, so the current tenant
 * is that one ({@see WikiTenantPathsLinkTenantResolver::NO_TENANT_ROW}) and the group searches every readable space, as
 * before 1.46. With `tenant_paths` the wiki cannot tell which tenant a search request belongs to (SearchKit's endpoint is
 * not a tenant route): no current tenant, so the group finds nothing until the host aliases its own resolver or sets
 * `search_kit_provider.tenant: all`. A host that aliased its own WikiLinkTenantResolverInterface must alias this port too.
 * Stateless.
 */
final readonly class WikiTenantPathsSearchTenantResolver implements WikiSearchTenantResolverInterface
{
    /**
     * @param WikiTenantPaths|null $tenantPaths the tenant paths (null: none)
     */
    public function __construct(
        private ?WikiTenantPaths $tenantPaths = null,
    ) {
    }

    public function currentTenant(string $userIdentifier): ?string
    {
        return $this->tenantPaths instanceof WikiTenantPaths && $this->tenantPaths->tenants() !== [] ? null : WikiTenantPathsLinkTenantResolver::NO_TENANT_ROW;
    }
}
