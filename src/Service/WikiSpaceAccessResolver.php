<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Nowo\WikiBundle\Security\WikiTeamMembershipResolverInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Resolves wiki spaces visible to the current user.
 */
final readonly class WikiSpaceAccessResolver implements WikiSpaceAccessResolverInterface
{
    public function __construct(
        private WikiSpaceRepositoryInterface $spaceRepository,
        private WikiTeamMembershipResolverInterface $teamMembershipResolver,
        private string $defaultOwnerScope,
    ) {
    }

    /**
     * @return list<WikiSpace>
     */
    public function listSpacesForUser(UserInterface $user): array
    {
        $scope = WikiSpaceOwnerScope::tryFrom($this->defaultOwnerScope) ?? WikiSpaceOwnerScope::Team;

        return match ($scope) {
            WikiSpaceOwnerScope::Team => $this->spaceRepository->findAccessible(
                WikiSpaceOwnerScope::Team->value,
                $this->teamMembershipResolver->getTeamIdsForUser($user),
            ),
            WikiSpaceOwnerScope::User => $this->spaceRepository->findAccessible(
                WikiSpaceOwnerScope::User->value,
                [$this->resolveUserId($user)],
            ),
        };
    }

    public function canAccessSpace(UserInterface $user, WikiSpace $space): bool
    {
        foreach ($this->listSpacesForUser($user) as $accessible) {
            if ($accessible->getId() === $space->getId()) {
                return true;
            }
        }

        return false;
    }

    private function resolveUserId(UserInterface $user): string
    {
        if (method_exists($user, 'getId')) {
            $id = $user->getId();
            if ($id !== null && $id !== '') {
                return (string) $id;
            }
        }

        return $user->getUserIdentifier();
    }
}
