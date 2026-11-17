<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\LinkPicker;

use Nowo\WikiBundle\Routing\WikiTenantPaths;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;

use function preg_match;

/**
 * 1.40: the default tenant of a wiki for the link picker (owner decision O4, contract §5): the `tenant_paths` row whose
 * `slug_requirement` matches the space slug first (the same row that owns the space's URLs). Without `tenant_paths` every
 * space is one tenant. A space that matches no row is in the tenant-less group, unless `tenant_only` is set: then it has
 * no tenant and is never offered to, nor offered from, another wiki. Stateless.
 */
final readonly class WikiTenantPathsLinkTenantResolver implements WikiLinkTenantResolverInterface
{
    /** Key of the tenant-less group (and of every space without tenant_paths). */
    public const NO_TENANT_ROW = 'default';

    /**
     * @param WikiTenantPaths|null $tenantPaths the tenant paths (null: none)
     */
    public function __construct(
        private ?WikiTenantPaths $tenantPaths = null,
    ) {
    }

    public function tenantOf(WikiSpaceSnapshot $space): ?string
    {
        foreach ($this->tenantPaths?->tenants() ?? [] as $index => $tenant) {
            if (preg_match('#^(?:' . $tenant['slug_requirement'] . ')$#D', $space->slug) === 1) {
                return 'tenant_path:' . $index;
            }
        }

        return $this->tenantPaths?->isTenantOnly() === true ? null : self::NO_TENANT_ROW;
    }
}
