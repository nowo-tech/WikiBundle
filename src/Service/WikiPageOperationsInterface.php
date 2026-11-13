<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use InvalidArgumentException;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\ValueObject\WikiPageSnapshot;
use Nowo\WikiBundle\ValueObject\WikiRevisionSnapshot;
use Nowo\WikiBundle\ValueObject\WikiUserRef;

/**
 * The page operations a host calls, by id and value objects (1.33.0, REQ-ARCH-001 rule 1): the framework-free twin of
 * {@see WikiPageServiceInterface} (public alias; same events, same checks). Ids are matched byte for byte; an id
 * naming no stored page, space or revision (or a revision of another page, a parent in another space) fails closed
 * with an `InvalidArgumentException`. The caller checks the user's rights, as with the entity port.
 */
interface WikiPageOperationsInterface
{
    /** The page with exactly that id, or null. */
    public function findPage(string $pageId): ?WikiPageSnapshot;

    /**
     * Creates a page with its first revision.
     *
     * @throws InvalidArgumentException when an id is unknown, the slug is invalid or taken, or the format is unknown
     */
    public function createPage(string $spaceId, string $title, string $content, WikiUserRef $author, ?string $parentPageId = null, ?string $slug = null, string $format = WikiPageRevision::FORMAT_HTML): WikiPageSnapshot;

    /**
     * Appends a revision (optionally against an expected revision number).
     *
     * @throws InvalidArgumentException when the page is unknown
     */
    public function saveRevision(string $pageId, string $title, string $content, WikiUserRef $author, string $format = WikiPageRevision::FORMAT_HTML, ?int $expectedRevision = null): WikiRevisionSnapshot;

    /**
     * Appends a revision with the body of an older revision of the same page.
     *
     * @throws InvalidArgumentException when the page or the revision is unknown, or the revision is another page's
     */
    public function restoreRevision(string $pageId, string $revisionId, WikiUserRef $actor, ?int $expectedRevision = null): WikiRevisionSnapshot;

    /** @throws InvalidArgumentException when the page is unknown */
    public function archivePage(string $pageId, ?WikiUserRef $actor = null): void;

    /** @throws InvalidArgumentException when the page is unknown */
    public function unarchivePage(string $pageId, ?WikiUserRef $actor = null): void;
}
