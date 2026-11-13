<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Port;

use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolverInterface;
use Nowo\WikiBundle\Service\WikiSpaceReachInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use function is_string;

/**
 * Asks a framework-free {@see WikiSpaceReachInterface} (1.33.0). The space ids it returns are loaded one by one and
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
        $spaces = [];
        foreach ($this->reach->spaceIdsFor(WikiUserRefs::fromUser($user)) as $id) {
            if (!is_string($id) || isset($spaces[$id])) {
                continue;
            }
            $space = $this->spaces->findById($id);
            if ($space instanceof WikiSpace && $space->getId() === $id) {
                $spaces[$id] = $space;
            }
        }

        return array_values($spaces);
    }

    public function canAccessSpace(UserInterface $user, WikiSpace $space): bool
    {
        return $this->reach->canAccessSpace(WikiUserRefs::fromUser($user), WikiSnapshots::space($space));
    }
}
