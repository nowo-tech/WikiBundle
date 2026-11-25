<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Security;

use Nowo\WikiBundle\Port\Internal\WikiTeamMembershipResolverInterface;
use Nowo\WikiBundle\ValueObject\WikiUserRef;

/**
 * Team membership, framework-free (1.36.0): since 2.0 the only host port; it replaced the removed 1.x `WikiTeamMembershipResolverInterface`
 * (`nowo_wiki.team_membership_resolver` names your service). Return the ids exactly as stored as space owners (they are
 * compared byte for byte; ids with edge whitespace or control characters own nothing).
 */
interface WikiTeamMembershipSourceInterface
{
    /**
     * @param WikiUserRef $user the user
     *
     * @return list<string> ids of the user's teams
     */
    public function teamIdsOf(WikiUserRef $user): array;
}
