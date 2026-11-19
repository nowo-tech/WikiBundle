<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Search;

use Nowo\WikiBundle\LinkPicker\WikiLinkTenantResolverInterface;

/**
 * Port (1.46, `search_kit_provider.tenant_resolver`): the **current tenant** of a SearchKit search of the wiki — the
 * palette and its results page. With `search_kit_provider.tenant: current` (the default, owner decision of 2026-11-19
 * for every quick-access list) the group searches only the spaces whose tenant key
 * ({@see WikiLinkTenantResolverInterface::tenantOf()}, the key the link picker and the favourites / recents lists use) is
 * exactly the key returned here.
 *
 * Return the key of the tenant (organisation, workspace…) the person is working in for this request, or null when there
 * is none (no tenant context): the group then finds nothing. Use the same keys as your WikiLinkTenantResolverInterface.
 * Framework-free (REQ-ARCH-001 rule 3): read the current tenant from your own request-scoped services inside each call
 * and keep nothing between calls (FrankenPHP worker mode without kernel reset).
 */
interface WikiSearchTenantResolverInterface
{
    /**
     * @param string $userIdentifier the viewer (SearchKit's user identifier)
     *
     * @return string|null the current tenant key, or null for none
     */
    public function currentTenant(string $userIdentifier): ?string;
}
