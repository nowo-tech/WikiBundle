<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Port;

use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Port\Internal\WikiSpaceAccessResolverInterface;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Nowo\WikiBundle\Service\WikiSpaceReachInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use function array_values;
use function is_string;

/**
 * Asks a framework-free {@see WikiSpaceReachInterface} (1.36.0). The space ids it returns are loaded one by one and
 * kept only when a stored space has exactly that id (REQ-SEC-006: no look-alike id reaches a space); duplicates are
 * dropped.
 *
 * @internal
 */
final readonly class WikiSpaceReachGate implements WikiSpaceAccessResolverInterface
{
    public function __construct(
        private WikiSpaceReachInterface $reach,
        private WikiSpaceRepositoryInterface $spaces,
    ) {
    }

    public function listSpacesForUser(UserInterface $user): array
    {
        $ids = [];
        foreach ($this->reach->spaceIdsFor(WikiUserRefs::fromUser($user)) as $id) {
            if (is_string($id)) {
                $ids[$id] = $id;
            }
        }
        // 1.55.2: one query for every id (it was 2 queries per space); 2.0: every space repository has findByIds(). The
        // exact id is checked here again, whatever repository answered (REQ-SEC-006).
        $found  = $ids === [] ? [] : $this->spaces->findByIds(array_values($ids));
        $spaces = [];
        foreach ($ids as $id) {
            $space = $found[$id] ?? null;
            if ($space instanceof WikiSpace && $space->getId() === $id) {
                $spaces[] = $space;
            }
        }

        return $spaces;
    }

    public function canAccessSpace(UserInterface $user, WikiSpace $space): bool
    {
        return $this->reach->canAccessSpace(WikiUserRefs::fromUser($user), WikiSnapshots::space($space));
    }
}
