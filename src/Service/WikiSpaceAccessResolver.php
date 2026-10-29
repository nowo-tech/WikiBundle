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
    /**
     * Wires the space repository, team membership resolver and default owner scope.
     *
     * @param WikiSpaceRepositoryInterface $spaceRepository space persistence
     * @param WikiTeamMembershipResolverInterface $teamMembershipResolver resolves the team ids of a user
     * @param string $defaultOwnerScope configured space scope ({@code team} or {@code user})
     */
    public function __construct(
        private WikiSpaceRepositoryInterface $spaceRepository,
        private WikiTeamMembershipResolverInterface $teamMembershipResolver,
        private string $defaultOwnerScope,
    ) {
    }

    /**
     * Lists the spaces owned by the user's teams or by the user, depending on the configured owner scope.
     *
     * @param UserInterface $user current user
     *
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

    /**
     * Tells whether the space is among the spaces listed for the user.
     *
     * @param UserInterface $user current user
     * @param WikiSpace $space space to check
     *
     * @return bool true when the space is visible to the user
     */
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
