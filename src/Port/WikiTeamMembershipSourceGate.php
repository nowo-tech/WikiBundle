<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Port;

use Nowo\WikiBundle\Security\WikiTeamMembershipResolverInterface;
use Nowo\WikiBundle\Security\WikiTeamMembershipSourceInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use function is_string;

/**
 * Asks a framework-free {@see WikiTeamMembershipSourceInterface} (1.34.0); non-string ids are dropped (the owner-id
 * rules of `WikiSpaceAccessResolver` still apply to the rest).
 *
 * @internal
 */
final readonly class WikiTeamMembershipSourceGate implements WikiTeamMembershipResolverInterface
{
    public function __construct(private WikiTeamMembershipSourceInterface $source)
    {
    }

    public function getTeamIdsForUser(UserInterface $user): array
    {
        $ids = [];
        foreach ($this->source->teamIdsOf(WikiUserRefs::fromUser($user)) as $id) {
            if (is_string($id)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
