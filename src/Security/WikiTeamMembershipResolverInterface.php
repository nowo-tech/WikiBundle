<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Resolves team identifiers for a user (for team-scoped wiki spaces).
 *
 * 1.29.1 (FrankenPHP worker mode with kernel reset false): read the memberships from the database on every call (a
 * query), not from a collection of the user entity: the host's EntityManager can keep the user managed across requests,
 * and an already-loaded collection then still lists a team the user has left. Do not memoise them in a property
 * either, unless it is emptied at every request boundary.
 */
interface WikiTeamMembershipResolverInterface
{
    /**
     * Returns the identifiers of the teams the user belongs to.
     *
     * @param UserInterface $user user to resolve
     *
     * @return list<string> Team ids the user belongs to
     */
    public function getTeamIdsForUser(UserInterface $user): array;
}
