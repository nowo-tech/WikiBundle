<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Link;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageLink;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Port of the page ↔ host object links (1.27; public API, SemVer). Aliased to {@see WikiPageLinkService}.
 */
interface WikiPageLinkServiceInterface
{
    /**
     * Links the page to the object (idempotent); dispatches PAGE_LINKED for a new link.
     */
    public function link(WikiPage $page, string $objectType, string $objectId, ?object $actor = null): WikiPageLink;

    /**
     * Removes the link; returns false when there was none. Dispatches PAGE_UNLINKED.
     */
    public function unlink(WikiPage $page, string $objectType, string $objectId, ?object $actor = null): bool;

    /**
     * The linked host objects the viewer may open, grouped by type.
     *
     * @return array<string, list<WikiLinkedObject>>
     */
    public function usedIn(WikiPage $page, UserInterface $viewer): array;
}
