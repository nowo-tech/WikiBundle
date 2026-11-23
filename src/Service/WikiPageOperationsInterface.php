<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use InvalidArgumentException;
use LogicException;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\ValueObject\WikiCommentSnapshot;
use Nowo\WikiBundle\ValueObject\WikiCommentThreadSnapshot;
use Nowo\WikiBundle\ValueObject\WikiPageSnapshot;
use Nowo\WikiBundle\ValueObject\WikiRevisionSnapshot;
use Nowo\WikiBundle\ValueObject\WikiUserRef;

/**
 * The page operations a host calls, by id and value objects (1.36.0, REQ-ARCH-001 rule 1): the framework-free twin of
 * {@see WikiPageServiceInterface} (public alias; same events, same checks). Ids are matched byte for byte; an id
 * naming no stored page, space or revision (or a revision of another page, a parent in another space) fails closed
 * with an `InvalidArgumentException`. The caller checks the user's rights, as with the entity port.
 *
 * 1.55 (Beacon): read models by id for a host that keeps no wiki entity — a page revision by number and the comment
 * threads and comments of a page ({@see self::findPageRevision()}, {@see self::findCommentThread()},
 * {@see self::findComment()}, {@see self::findThreadComments()}). Read-only and stateless: they lock and write nothing,
 * answer what is stored (no access decision: ask the space checker, as for the page) and keep nothing between calls.
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
     * Creates a page at a place among the parent's children (the 1.35 "create at a position" by id): in one transaction
     * holding the space lock, with the ordering and `tree.max_depth` of {@see WikiPageMover::createAt()}. Needs
     * `tree.enabled`.
     *
     * @param string|null $parentPageId the parent (null: the root); must be an active page of the space
     * @param int $index 0-based index among the parent's active children, clamped (`PHP_INT_MAX`: last)
     *
     * @throws InvalidArgumentException when an id is unknown or the parent is in another space, the slug is invalid
     *                                  or taken, or the format is unknown
     * @throws WikiPageMoveException `parent_missing` when the parent went away meanwhile, `too_deep` past the depth
     *                               limit; nothing is written
     * @throws LogicException without the page tree (`tree.enabled: false`)
     */
    public function createPageAt(string $spaceId, ?string $parentPageId, int $index, string $title, string $content, WikiUserRef $author, ?string $slug = null, string $format = WikiPageRevision::FORMAT_HTML): WikiPageSnapshot;

    /**
     * Creates a page right above or below a sibling, under the sibling's parent (the 1.35 "New page above / below" by
     * id), as {@see WikiPageMover::createNextTo()}. Needs `tree.enabled`.
     *
     * @param bool $after true: right below the sibling; false: right above it
     *
     * @throws InvalidArgumentException when the sibling is unknown, the slug is invalid or taken, or the format is
     *                                  unknown
     * @throws WikiPageMoveException `page_missing`, `stale` (the sibling moved meanwhile) or `too_deep`; nothing is
     *                               written
     * @throws LogicException without the page tree (`tree.enabled: false`)
     */
    public function createPageNextTo(string $siblingPageId, bool $after, string $title, string $content, WikiUserRef $author, ?string $slug = null, string $format = WikiPageRevision::FORMAT_HTML): WikiPageSnapshot;

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

    /**
     * 1.55: the revision of a page with that number (1: the first save, whose author is the page's author), or null when
     * the page or the number is unknown.
     */
    public function findPageRevision(string $pageId, int $revisionNumber): ?WikiRevisionSnapshot;

    /**
     * 1.55: the comment thread with exactly that id, or null (also when `comments.enabled` is off, or the comment
     * repository the application configured cannot read by id).
     */
    public function findCommentThread(string $threadId): ?WikiCommentThreadSnapshot;

    /**
     * 1.55: the comment with exactly that id, deleted ones included (see {@see WikiCommentSnapshot::isDeleted()}), or
     * null (as {@see self::findCommentThread()}).
     */
    public function findComment(string $commentId): ?WikiCommentSnapshot;

    /**
     * 1.55: every comment of the thread with exactly that id, in thread order, deleted ones included (see
     * {@see WikiCommentSnapshot::isDeleted()}); empty when the thread is unknown (as {@see self::findCommentThread()}).
     *
     * @return list<WikiCommentSnapshot>
     */
    public function findThreadComments(string $threadId): array;
}
