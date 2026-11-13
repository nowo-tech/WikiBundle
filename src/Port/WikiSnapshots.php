<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Port;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\User\WikiUserId;
use Nowo\WikiBundle\ValueObject\WikiPageSnapshot;
use Nowo\WikiBundle\ValueObject\WikiRevisionSnapshot;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;

/**
 * Copies the bundle's entities into the snapshots host ports and events carry (1.32.0, REQ-ARCH-001 rules 1 and 3).
 * Only ids and plain values are read: a lazy parent or current revision is never loaded for its id.
 */
final class WikiSnapshots
{
    /**
     * @param WikiSpace $space the space row
     *
     * @return WikiSpaceSnapshot its snapshot
     */
    public static function space(WikiSpace $space): WikiSpaceSnapshot
    {
        return new WikiSpaceSnapshot(
            $space->getId(),
            $space->getSlug(),
            $space->getName(),
            $space->getOwnerScopeType(),
            $space->getOwnerScopeId(),
            $space->getCreatedAt(),
            $space->getUpdatedAt(),
        );
    }

    /**
     * @param WikiSpace|null $space the space row, or null
     *
     * @return WikiSpaceSnapshot|null its snapshot, or null
     */
    public static function spaceOrNull(?WikiSpace $space): ?WikiSpaceSnapshot
    {
        return $space instanceof WikiSpace ? self::space($space) : null;
    }

    /**
     * @param WikiPage $page the page row
     *
     * @return WikiPageSnapshot its snapshot
     */
    public static function page(WikiPage $page): WikiPageSnapshot
    {
        return new WikiPageSnapshot(
            $page->getId(),
            $page->getSpace()->getId(),
            $page->getSlug(),
            $page->getTitle(),
            $page->getParent()?->getId(),
            $page->getPosition(),
            $page->getCurrentRevision()?->getId(),
            $page->getArchivedAt(),
            $page->getCreatedAt(),
            $page->getUpdatedAt(),
        );
    }

    /**
     * @param WikiPage|null $page the page row, or null
     *
     * @return WikiPageSnapshot|null its snapshot, or null
     */
    public static function pageOrNull(?WikiPage $page): ?WikiPageSnapshot
    {
        return $page instanceof WikiPage ? self::page($page) : null;
    }

    /**
     * @param WikiPageRevision $revision the revision row
     *
     * @return WikiRevisionSnapshot its snapshot
     */
    public static function revision(WikiPageRevision $revision): WikiRevisionSnapshot
    {
        return new WikiRevisionSnapshot(
            $revision->getId(),
            $revision->getPage()->getId(),
            $revision->getRevisionNumber(),
            $revision->getFormat(),
            WikiUserId::of($revision->getAuthor()),
            $revision->getCreatedAt(),
        );
    }

    /**
     * @param WikiPageRevision|null $revision the revision row, or null
     *
     * @return WikiRevisionSnapshot|null its snapshot, or null
     */
    public static function revisionOrNull(?WikiPageRevision $revision): ?WikiRevisionSnapshot
    {
        return $revision instanceof WikiPageRevision ? self::revision($revision) : null;
    }
}
