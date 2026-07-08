<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Resolves team identifiers for a user (for team-scoped wiki spaces).
 */
interface WikiTeamMembershipResolverInterface
{
    /**
     * @return list<string> Team ids the user belongs to
     */
    public function getTeamIdsForUser(UserInterface $user): array;
}
