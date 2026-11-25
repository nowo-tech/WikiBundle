<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\QuickAccess;

use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\LinkPicker\WikiLinkTenantResolverInterface;
use Nowo\WikiBundle\Port\Internal\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Port\Internal\WikiSpaceAccessCheckerInterface;
use Nowo\WikiBundle\Port\Internal\WikiSpaceAccessResolverInterface;
use Nowo\WikiBundle\Port\WikiSnapshots;
use Symfony\Component\Security\Core\User\UserInterface;

use function array_values;

/**
 * 1.45: the {@see WikiViewerScope} of one view, for the per-viewer lists (favourites, recents, backlinks), in the order
 * of the page view's own rules: the role gate ({@see WikiAccessCheckerInterface::canList()}), the viewer's spaces from
 * the tenant / team resolver ({@see WikiSpaceAccessResolverInterface}), {@see WikiAction::View} on each space and —
 * owner decision A (2026-11-19) — only the spaces of the **current tenant**: the same non-null key of
 * {@see WikiLinkTenantResolverInterface} as the space being viewed (the link picker's tenant: `tenant_paths` by default,
 * the host's organisation resolver when it aliases one). A space without a tenant key keeps the lists to itself. Only
 * these spaces reach the SQL of the lists, so another tenant's or space's rows are never read. Computed on every call
 * (worker mode: nothing is kept between requests).
 */
final readonly class WikiReadableSpaces
{
    public function __construct(
        private WikiAccessCheckerInterface $roles,
        private WikiSpaceAccessResolverInterface $spaces,
        private WikiSpaceAccessCheckerInterface $checker,
        private WikiLinkTenantResolverInterface $tenants,
    ) {
    }

    /**
     * The scope of one view of `$current` (the space being viewed) by `$viewer`.
     */
    public function forView(UserInterface $viewer, WikiSpace $current): WikiViewerScope
    {
        if (!$this->roles->canList($viewer)) {
            return new WikiViewerScope($viewer, [], $this->checker);
        }

        $tenant   = $this->tenants->tenantOf(WikiSnapshots::space($current));
        $readable = [];
        foreach ($this->spaces->listSpacesForUser($viewer) as $space) {
            $same = $space->getId() === $current->getId()
                || ($tenant !== null && $this->tenants->tenantOf(WikiSnapshots::space($space)) === $tenant);
            if ($same && $this->checker->isGranted($viewer, WikiAction::View, $space)) {
                $readable[$space->getId()] = $space;
            }
        }

        return new WikiViewerScope($viewer, array_values($readable), $this->checker);
    }
}
