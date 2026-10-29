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
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Creates pages and appends immutable revisions on each save.
 */
final readonly class WikiPageService
{
    /**
     * Wires the repositories, slugger, sanitizer, event dispatcher and optional logger.
     *
     * @param WikiPageRepositoryInterface $pageRepository page persistence
     * @param WikiPageRevisionRepositoryInterface $revisionRepository revision persistence
     * @param WikiSlugger $slugger slug generator and validator
     * @param WikiHtmlSanitizerInterface $htmlSanitizer sanitizes revision HTML before storage
     * @param EventDispatcherInterface $eventDispatcher dispatches page saved/archived events
     * @param LoggerInterface|null $logger logger (defaults to a NullLogger)
     */
    public function __construct(
        private WikiPageRepositoryInterface $pageRepository,
        private WikiPageRevisionRepositoryInterface $revisionRepository,
        private WikiSlugger $slugger,
        private WikiHtmlSanitizerInterface $htmlSanitizer,
        private EventDispatcherInterface $eventDispatcher,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    private LoggerInterface $logger;

    /**
     * Creates a page with its first sanitized revision and dispatches {@see WikiEvents::PAGE_SAVED}.
     *
     * @param WikiSpace $space space the page belongs to
     * @param string $title page title
     * @param string $contentHtml initial HTML content (sanitized before storage)
     * @param object $author application user recorded as revision author
     * @param WikiPage|null $parent optional parent page
     * @param string|null $slug explicit slug, or null to slugify the title
     *
     * @throws InvalidArgumentException when the slug is invalid or already exists in the space
     *
     * @return WikiPage the persisted page
     */
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

    /**
     * Updates the page title, appends a new sanitized revision and dispatches {@see WikiEvents::PAGE_SAVED}.
     *
     * @param WikiPage $page page to update
     * @param string $title new page title
     * @param string $contentHtml new HTML content (sanitized before storage)
     * @param object $author application user recorded as revision author
     *
     * @throws InvalidArgumentException when the page is archived
     *
     * @return WikiPageRevision the new current revision
     */
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

    /**
     * Archives the page, logs it and dispatches {@see WikiEvents::PAGE_ARCHIVED}; no-op when already archived.
     *
     * @param WikiPage $page page to archive
     */
    public function archive(WikiPage $page): void
    {
        if ($page->isArchived()) {
            return;
        }

        $page->archive();
        $this->pageRepository->save($page);
        $this->logger->info('Wiki page archived.', [
            'bundle' => 'nowo_wiki',
            'action' => 'archive',
            'space'  => $page->getSpace()->getSlug(),
            'page'   => $page->getSlug(),
        ]);
        $this->eventDispatcher->dispatch(new WikiPageArchivedEvent($page), WikiEvents::PAGE_ARCHIVED);
    }
}
