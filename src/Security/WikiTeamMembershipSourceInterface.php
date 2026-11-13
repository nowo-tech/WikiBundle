<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Security;

use Nowo\WikiBundle\ValueObject\WikiUserRef;

/**
 * Team membership, framework-free (1.33.0): the twin of the deprecated {@see WikiTeamMembershipResolverInterface}
 * (`nowo_wiki.team_membership_resolver` takes either). Return the ids exactly as stored as space owners (they are
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
