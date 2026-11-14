<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Member;

/**
 * Spec 234: the default badge provider — no badge (the history and the comments render as before).
 */
final readonly class NullWikiMemberBadgeProvider implements WikiMemberBadgeProviderInterface
{
    public function badgesFor(WikiMemberRef $member): array
    {
        return [];
    }

    public function badgesForMany(array $members): array
    {
        return [];
    }
}
