<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use InvalidArgumentException;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizerInterface;
use Nowo\WikiBundle\Util\WikiSlugger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Creates pages and appends immutable revisions on each save.
 */
final readonly class WikiPageService
{
    public function __construct(
        private WikiPageRepositoryInterface $pageRepository,
        private WikiPageRevisionRepositoryInterface $revisionRepository,
        private WikiSlugger $slugger,
        private WikiHtmlSanitizerInterface $htmlSanitizer,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function create(
        WikiSpace $space,
        string $title,
        string $contentHtml,
        object $author,
        ?WikiPage $parent = null,
        ?string $slug = null,
    ): WikiPage {
        $slug ??= $this->slugger->slugify($title);
        if (!$this->slugger->isValid($slug)) {
            throw new InvalidArgumentException('Invalid page slug.');
        }

        if ($this->pageRepository->countBySpaceAndSlug($space, $slug) > 0) {
            throw new InvalidArgumentException('Page slug already exists in this space.');
        }

        $page = new WikiPage($space, $slug, $title, $parent);
        $this->pageRepository->save($page);

        $revision = new WikiPageRevision($page, 1, $this->htmlSanitizer->sanitize($contentHtml), $author);
        $this->revisionRepository->save($revision);
        $page->setCurrentRevision($revision);
        $this->pageRepository->save($page);

        $this->eventDispatcher->dispatch(new WikiPageSavedEvent($page, $revision), WikiEvents::PAGE_SAVED);

        return $page;
    }

    public function saveRevision(WikiPage $page, string $title, string $contentHtml, object $author): WikiPageRevision
    {
        if ($page->isArchived()) {
            throw new InvalidArgumentException('Archived pages cannot be edited.');
        }

        $page->setTitle($title);
        $number   = $this->revisionRepository->getNextRevisionNumber($page);
        $revision = new WikiPageRevision($page, $number, $this->htmlSanitizer->sanitize($contentHtml), $author);
        $this->revisionRepository->save($revision);
        $page->setCurrentRevision($revision);
        $this->pageRepository->save($page);

        $this->eventDispatcher->dispatch(new WikiPageSavedEvent($page, $revision), WikiEvents::PAGE_SAVED);

        return $revision;
    }

    public function archive(WikiPage $page): void
    {
        if ($page->isArchived()) {
            return;
        }

        $page->archive();
        $this->pageRepository->save($page);
        $this->eventDispatcher->dispatch(new WikiPageArchivedEvent($page), WikiEvents::PAGE_ARCHIVED);
    }
}
