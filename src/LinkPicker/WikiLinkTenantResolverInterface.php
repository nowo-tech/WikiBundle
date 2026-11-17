<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\LinkPicker;

use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;

/**
 * 1.40 (owner decision O4): which tenant a wiki belongs to, so that the link picker offers pages of the **same tenant
 * only**. Two spaces belong to the same tenant when this returns the same non-null key for both; null means the space
 * takes part in no cross-wiki suggestion (only its own pages are offered while editing it).
 *
 * The default, {@see WikiTenantPathsLinkTenantResolver}, uses the `tenant_paths` rows: that is coarse (every space
 * matching one row is one tenant, e.g. every project wiki of every organisation). A host with real tenants aliases this
 * interface to its own service (an organisation id, for instance):
 *
 *     services:
 *         Nowo\WikiBundle\LinkPicker\WikiLinkTenantResolverInterface: '@App\Wiki\OrganisationLinkTenants'
 *
 * Framework-free (REQ-ARCH-001 rule 3). When the answer depends on the current person (a space shared into another
 * organisation, for instance), inject Symfony's `Security` (or the token storage) into your service and read the user
 * inside each call. Keep no state between calls and cache nothing across requests (FrankenPHP worker mode without
 * kernel reset: the same service instance answers every user and tenant). The picker asks once for the current wiki and
 * once per readable space of the person.
 */
interface WikiLinkTenantResolverInterface
{
    /**
     * @param WikiSpaceSnapshot $space the space
     *
     * @return string|null the tenant key, or null for none
     */
    public function tenantOf(WikiSpaceSnapshot $space): ?string;
}
