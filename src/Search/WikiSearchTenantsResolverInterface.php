<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Search;

use Nowo\WikiBundle\LinkPicker\WikiLinkTenantResolverInterface;
use Nowo\WikiBundle\Search\SearchKit\WikiPageSearchDefinition;

/**
 * Port (1.47, `search_kit_provider.tenant_resolver`): the **current tenants** of a SearchKit search of the wiki, for a
 * host whose people work in several wiki tenants at once (an organisation's wiki, one per project, personal wikis, a
 * general wiki…). With `search_kit_provider.tenant: current` the group searches the union of the spaces whose tenant key
 * ({@see WikiLinkTenantResolverInterface::tenantOf()}) is exactly (`===`) one of the keys returned here.
 *
 * Return every tenant key the person may search from this request, or an empty list (no tenant context): nothing is
 * found. `*` and empty strings are never keys (dropped); duplicates are ignored; more than
 * {@see WikiPageSearchDefinition::MAX_TENANTS} keys find nothing. Neither the SearchKit
 * scope, the `space:` filters nor the recents can reach a space outside the list.
 *
 * Implement either this port or the single-key {@see WikiSearchTenantResolverInterface} (1.46, still supported).
 * Framework-free (REQ-ARCH-001 rule 3): read the person's tenants from your own request-scoped services inside each call
 * and keep nothing between calls (FrankenPHP worker mode without kernel reset).
 */
interface WikiSearchTenantsResolverInterface
{
    /**
     * @param string $userIdentifier the viewer (SearchKit's user identifier)
     *
     * @return list<string> the current tenant keys (empty: none)
     */
    public function currentTenants(string $userIdentifier): array;
}
