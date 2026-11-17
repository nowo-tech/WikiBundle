<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\LinkPicker;

use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;

/**
 * 1.40 (owner decision O4): which tenant a wiki belongs to, so that the link picker offers pages of the **same tenant
 * only**. Two spaces belong to the same tenant when this returns the same non-null key for both; null means the space
 * takes part in no cross-wiki suggestion (only its own pages are offered while editing it).
 *
 * The default, {@see WikiTenantPathsLinkTenantResolver}, uses the `tenant_paths` rows. Alias this interface to your own
 * service for a finer tenant (an organisation id, for instance). Framework-free (REQ-ARCH-001 rule 3); keep no state
 * between calls (worker mode).
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
