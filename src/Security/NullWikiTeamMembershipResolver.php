<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Returns no team memberships when the application does not configure a resolver.
 */
final class NullWikiTeamMembershipResolver implements WikiTeamMembershipResolverInterface
{
    /**
     * Returns the team identifiers the user belongs to; always empty.
     *
     * @param UserInterface $user user to resolve
     *
     * @return list<string> always an empty list
     */
    public function getTeamIdsForUser(UserInterface $user): array
    {
        return [];
    }
}
