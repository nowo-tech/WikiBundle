<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\DddHost;

use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Security\WikiAccessPolicyInterface;
use Nowo\WikiBundle\Security\WikiSpaceAccessPolicyInterface;
use Nowo\WikiBundle\Security\WikiTeamMembershipSourceInterface;
use Nowo\WikiBundle\ValueObject\WikiPageSnapshot;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;
use Nowo\WikiBundle\ValueObject\WikiUserRef;

/**
 * The DDD host's wiki policies, written against the framework-free twins only (no Symfony user, no wiki entity):
 * everyone signed in may use the wiki, a space belongs to a team, and a person reads and edits their team's spaces.
 */
final class DddPolicies implements WikiAccessPolicyInterface, WikiSpaceAccessPolicyInterface, WikiTeamMembershipSourceInterface
{
    /** @var list<array{string, string}> what the host was asked: [user id, space id] */
    public static array $asked = [];

    public function canAccess(?WikiUserRef $user = null): bool
    {
        return $user?->hasId() === true;
    }

    public function canList(?WikiUserRef $user = null): bool
    {
        return $this->canAccess($user);
    }

    public function canCreate(?WikiUserRef $user = null): bool
    {
        return $this->canAccess($user);
    }

    public function canEdit(?WikiUserRef $user = null): bool
    {
        return $this->canAccess($user);
    }

    public function canViewHistory(?WikiUserRef $user = null): bool
    {
        return $this->canAccess($user);
    }

    public function canArchive(?WikiUserRef $user = null): bool
    {
        return $this->canAccess($user);
    }

    public function canAskAi(?WikiUserRef $user = null): bool
    {
        return false;
    }

    public function canImport(?WikiUserRef $user = null): bool
    {
        return false;
    }

    public function canExport(?WikiUserRef $user = null): bool
    {
        return false;
    }

    public function isGranted(WikiUserRef $user, WikiAction $action, WikiSpaceSnapshot $space, ?WikiPageSnapshot $page = null): bool
    {
        self::$asked[] = [(string) $user->id, $space->id];

        return $user->id !== null && $space->ownerScopeId === (DddUsers::TEAMS[$user->id] ?? null);
    }

    public function teamIdsOf(WikiUserRef $user): array
    {
        return $user->id !== null && isset(DddUsers::TEAMS[$user->id]) ? [DddUsers::TEAMS[$user->id]] : [];
    }
}
