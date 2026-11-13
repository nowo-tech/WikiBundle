<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use InvalidArgumentException;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Port\WikiSnapshots;
use Nowo\WikiBundle\Port\WikiUserRefs;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Nowo\WikiBundle\ValueObject\WikiPageSnapshot;
use Nowo\WikiBundle\ValueObject\WikiRevisionSnapshot;
use Nowo\WikiBundle\ValueObject\WikiUserRef;

use function sprintf;

/**
 * {@see WikiPageOperationsInterface} over {@see WikiPageServiceInterface} (1.32.0).
 */
final readonly class WikiPageOperations implements WikiPageOperationsInterface
{
    public function __construct(
        private WikiPageServiceInterface $pageService,
        private WikiPageRepositoryInterface $pages,
        private WikiSpaceRepositoryInterface $spaces,
        private WikiPageRevisionRepositoryInterface $revisions,
    ) {
    }

    public function findPage(string $pageId): ?WikiPageSnapshot
    {
        $page = $this->pages->findById($pageId);

        return $page instanceof WikiPage && $page->getId() === $pageId ? WikiSnapshots::page($page) : null;
    }

    public function createPage(string $spaceId, string $title, string $content, WikiUserRef $author, ?string $parentPageId = null, ?string $slug = null, string $format = WikiPageRevision::FORMAT_HTML): WikiPageSnapshot
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

        return WikiSnapshots::page($this->pageService->create($space, $title, $content, WikiUserRefs::object($author), $parent, $slug, $format));
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
        $this->pageService->archive($this->page($pageId), $actor === null ? null : WikiUserRefs::object($actor));
    }

    public function unarchivePage(string $pageId, ?WikiUserRef $actor = null): void
    {
        $this->pageService->unarchive($this->page($pageId), $actor === null ? null : WikiUserRefs::object($actor));
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
