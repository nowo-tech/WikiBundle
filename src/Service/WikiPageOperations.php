<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use InvalidArgumentException;
use LogicException;
use Nowo\WikiBundle\Entity\WikiComment;
use Nowo\WikiBundle\Entity\WikiCommentThread;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Port\WikiSnapshots;
use Nowo\WikiBundle\Port\WikiUserRefs;
use Nowo\WikiBundle\Repository\WikiCommentByIdFinderInterface;
use Nowo\WikiBundle\Repository\WikiCommentRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Nowo\WikiBundle\ValueObject\WikiCommentSnapshot;
use Nowo\WikiBundle\ValueObject\WikiCommentThreadSnapshot;
use Nowo\WikiBundle\ValueObject\WikiPageSnapshot;
use Nowo\WikiBundle\ValueObject\WikiRevisionSnapshot;
use Nowo\WikiBundle\ValueObject\WikiUserRef;

use function array_map;
use function sprintf;

/**
 * {@see WikiPageOperationsInterface} over {@see WikiPageServiceInterface} (1.36.0).
 */
final readonly class WikiPageOperations implements WikiPageOperationsInterface
{
    public function __construct(
        private WikiPageServiceInterface $pageService,
        private WikiPageRepositoryInterface $pages,
        private WikiSpaceRepositoryInterface $spaces,
        private WikiPageRevisionRepositoryInterface $revisions,
        private ?WikiPageMover $mover = null,
        private ?WikiCommentRepositoryInterface $comments = null,
    ) {
    }

    public function findPage(string $pageId): ?WikiPageSnapshot
    {
        $page = $this->pages->findById($pageId);

        return $page instanceof WikiPage && $page->getId() === $pageId ? WikiSnapshots::page($page) : null;
    }

    public function createPage(string $spaceId, string $title, string $content, WikiUserRef $author, ?string $parentPageId = null, ?string $slug = null, string $format = WikiPageRevision::FORMAT_HTML): WikiPageSnapshot
    {
        [$space, $parent] = $this->placeIn($spaceId, $parentPageId);

        return WikiSnapshots::page($this->pageService->create($space, $title, $content, WikiUserRefs::object($author), $parent, $slug, $format));
    }

    public function createPageAt(string $spaceId, ?string $parentPageId, int $index, string $title, string $content, WikiUserRef $author, ?string $slug = null, string $format = WikiPageRevision::FORMAT_HTML): WikiPageSnapshot
    {
        $mover            = $this->mover();
        [$space, $parent] = $this->placeIn($spaceId, $parentPageId);
        $user             = WikiUserRefs::object($author);

        return WikiSnapshots::page($mover->createAt($space, $parent, $index, fn (): WikiPage => $this->pageService->create($space, $title, $content, $user, $parent, $slug, $format))->page);
    }

    public function createPageNextTo(string $siblingPageId, bool $after, string $title, string $content, WikiUserRef $author, ?string $slug = null, string $format = WikiPageRevision::FORMAT_HTML): WikiPageSnapshot
    {
        $mover   = $this->mover();
        $sibling = $this->page($siblingPageId);
        $space   = $sibling->getSpace();
        $parent  = $sibling->getParent();
        $user    = WikiUserRefs::object($author);

        return WikiSnapshots::page($mover->createNextTo($space, $sibling, $after, fn (): WikiPage => $this->pageService->create($space, $title, $content, $user, $parent, $slug, $format))->page);
    }

    public function saveRevision(string $pageId, string $title, string $content, WikiUserRef $author, string $format = WikiPageRevision::FORMAT_HTML, ?int $expectedRevision = null): WikiRevisionSnapshot
    {
        return WikiSnapshots::revision($this->pageService->saveRevision($this->page($pageId), $title, $content, WikiUserRefs::object($author), $format, $expectedRevision));
    }

    public function restoreRevision(string $pageId, string $revisionId, WikiUserRef $actor, ?int $expectedRevision = null): WikiRevisionSnapshot
    {
        $page     = $this->page($pageId);
        $revision = $this->revisions->findById($revisionId);
        if (!$revision instanceof WikiPageRevision || $revision->getId() !== $revisionId || $revision->getPage()->getId() !== $page->getId()) {
            throw new InvalidArgumentException(sprintf('Unknown revision "%s" of the wiki page "%s".', $revisionId, $pageId));
        }

        return WikiSnapshots::revision($this->pageService->restore($page, $revision, WikiUserRefs::object($actor), $expectedRevision));
    }

    public function archivePage(string $pageId, ?WikiUserRef $actor = null): void
    {
        $this->pageService->archive($this->page($pageId), $actor instanceof WikiUserRef ? WikiUserRefs::object($actor) : null);
    }

    public function unarchivePage(string $pageId, ?WikiUserRef $actor = null): void
    {
        $this->pageService->unarchive($this->page($pageId), $actor instanceof WikiUserRef ? WikiUserRefs::object($actor) : null);
    }

    public function findPageRevision(string $pageId, int $revisionNumber): ?WikiRevisionSnapshot
    {
        $page = $this->pages->findById($pageId);
        if (!$page instanceof WikiPage || $page->getId() !== $pageId) {
            return null;
        }
        $revision = $this->revisions->findByPageAndNumber($page, $revisionNumber);

        return $revision instanceof WikiPageRevision && $revision->getPage()->getId() === $pageId && $revision->getRevisionNumber() === $revisionNumber
            ? WikiSnapshots::revision($revision)
            : null;
    }

    public function findCommentThread(string $threadId): ?WikiCommentThreadSnapshot
    {
        $thread = $this->thread($threadId);

        return $thread instanceof WikiCommentThread ? WikiSnapshots::thread($thread) : null;
    }

    public function findComment(string $commentId): ?WikiCommentSnapshot
    {
        $comment = $this->comments instanceof WikiCommentByIdFinderInterface ? $this->comments->findCommentById($commentId) : null;

        return $comment instanceof WikiComment && $comment->getId() === $commentId ? WikiSnapshots::comment($comment) : null;
    }

    public function findThreadComments(string $threadId): array
    {
        $thread = $this->thread($threadId);
        if (!$thread instanceof WikiCommentThread || !$this->comments instanceof WikiCommentByIdFinderInterface) {
            return [];
        }

        return array_map(WikiSnapshots::comment(...), $this->comments->findThreadComments($threadId, self::MAX_THREAD_COMMENTS));
    }

    /** The comment thread with exactly that id, or null (no comments, or a repository that cannot read by id). */
    private function thread(string $threadId): ?WikiCommentThread
    {
        $thread = $this->comments instanceof WikiCommentByIdFinderInterface ? $this->comments->findThreadById($threadId) : null;

        return $thread instanceof WikiCommentThread && $thread->getId() === $threadId ? $thread : null;
    }

    /**
     * The space and the parent page a new page goes to, by exact ids.
     *
     * @return array{WikiSpace, WikiPage|null}
     */
    private function placeIn(string $spaceId, ?string $parentPageId): array
    {
        $space = $this->spaces->findById($spaceId);
        if (!$space instanceof WikiSpace || $space->getId() !== $spaceId) {
            throw new InvalidArgumentException(sprintf('Unknown wiki space "%s".', $spaceId));
        }
        $parent = null;
        if ($parentPageId !== null) {
            $parent = $this->page($parentPageId);
            if ($parent->getSpace()->getId() !== $space->getId()) {
                throw new InvalidArgumentException(sprintf('The parent page "%s" is not in the space "%s".', $parentPageId, $spaceId));
            }
        }

        return [$space, $parent];
    }

    private function mover(): WikiPageMover
    {
        return $this->mover ?? throw new LogicException('Creating a page at a place needs the page tree (nowo_wiki.tree.enabled: true).');
    }

    private function page(string $pageId): WikiPage
    {
        $page = $this->pages->findById($pageId);
        if (!$page instanceof WikiPage || $page->getId() !== $pageId) {
            throw new InvalidArgumentException(sprintf('Unknown wiki page "%s".', $pageId));
        }

        return $page;
    }
}
