<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\DddHost;

use Nowo\WikiBundle\Member\WikiMemberBadge;
use Nowo\WikiBundle\Member\WikiMemberBadgeProviderInterface;
use Nowo\WikiBundle\Member\WikiMemberRef;

use function array_map;

/**
 * The DDD host's member badges (spec 234): `u-bob` is an agent, keyed by class and id exactly. It records what the
 * wiki asked (class and id of each member), for the test.
 */
final class DddBadges implements WikiMemberBadgeProviderInterface
{
    /** @var list<list<string>> "class#id" of each asked member, per call */
    public static array $asked = [];

    public function badgesFor(WikiMemberRef $member): array
    {
        return $this->badgesForMany([$member])[0];
    }

    public function badgesForMany(array $members): array
    {
        self::$asked[] = array_map(static fn (WikiMemberRef $member): string => $member->userClass . '#' . $member->id, $members);

        return array_map(static fn (WikiMemberRef $member): array => $member->isSameMemberAs(new WikiMemberRef(DddUser::class, 'u-bob')) ? [new WikiMemberBadge('Agent', WikiMemberBadge::VARIANT_AGENT)] : [], $members);
    }
}
