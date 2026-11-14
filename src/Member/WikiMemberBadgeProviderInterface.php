<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Member;

/**
 * Spec 234: the host's badges next to a member's name — revision authors in the history, comment authors in
 * the comments panel (e.g. an "Agent" pill for service accounts). WikiBundle asks once per rendered list
 * ({@see self::badgesForMany()}) with the distinct members of that list, after its access filters.
 *
 * Badges are shown beside every author, also beside an opaque "Editor xxxx" label (`history.show_names: false`):
 * return categorical badges only ("Agent", "Bot"), never one that names or singles out a person, or the badge would
 * link that person's edits across pages. Badges are plain text value objects; the bundle escapes them.
 *
 * The default ({@see NullWikiMemberBadgeProvider}) returns none, and the output is unchanged. Alias this interface to
 * the host's service. WikiBundle memoises the answers per request only.
 */
interface WikiMemberBadgeProviderInterface
{
    /**
     * @return list<WikiMemberBadge>
     */
    public function badgesFor(WikiMemberRef $member): array;

    /**
     * @param list<WikiMemberRef> $members distinct members
     *
     * @return array<int, list<WikiMemberBadge>> keyed like the list given (its index); a missing key means no badge
     */
    public function badgesForMany(array $members): array;
}
