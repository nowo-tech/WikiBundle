<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\QuickAccess;

use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolverInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use function array_values;

/**
 * 1.45: the spaces a viewer may read, for the per-viewer lists (favourites, recents, backlinks), in the order of the
 * page view's own rules: the role gate ({@see WikiAccessCheckerInterface::canList()}), the viewer's spaces from the
 * tenant / team resolver ({@see WikiSpaceAccessResolverInterface}) and {@see WikiAction::View} on each space. Only these
 * spaces reach the SQL of the lists, so another tenant's or space's rows are never read. Computed on every call (worker
 * mode: nothing is kept between requests).
 */
final readonly class WikiReadableSpaces
{
    public function __construct(
        private WikiAccessCheckerInterface $roles,
        private WikiSpaceAccessResolverInterface $spaces,
        private WikiSpaceAccessCheckerInterface $checker,
    ) {
    }

    /**
     * @return list<WikiSpace>
     */
    public function of(UserInterface $viewer): array
    {
        if (!$this->roles->canList($viewer)) {
            return [];
        }

        $readable = [];
        foreach ($this->spaces->listSpacesForUser($viewer) as $space) {
            if ($this->checker->isGranted($viewer, WikiAction::View, $space)) {
                $readable[$space->getId()] = $space;
            }
        }

        return array_values($readable);
    }

    /** The page-aware checker of the same rules (the per-page pass of the lists). */
    public function checker(): WikiSpaceAccessCheckerInterface
    {
        return $this->checker;
    }
}
