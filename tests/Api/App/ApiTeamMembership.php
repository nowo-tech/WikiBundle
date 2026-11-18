<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\App;

use Nowo\WikiBundle\Security\WikiTeamMembershipResolverInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * 1.44: the tenants (space owner ids) of each test person, scripted by the test (`team_membership_resolver: app.teams`),
 * for the spaces the bundle's resolver lists (the SearchKit provider's tenant rule).
 */
final class ApiTeamMembership implements WikiTeamMembershipResolverInterface
{
    /** @var array<string, list<string>> user id => owner ids */
    public static array $teams = [];

    public function getTeamIdsForUser(UserInterface $user): array
    {
        return self::$teams[$user->getUserIdentifier()] ?? [];
    }
}
