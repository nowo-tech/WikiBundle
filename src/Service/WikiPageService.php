<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use InvalidArgumentException;
use Nowo\WikiBundle\Attachment\WikiAttachmentReferenceFilter;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Event\WikiPageCreatedEvent;
use Nowo\WikiBundle\Event\WikiPageRestoredEvent;
use Nowo\WikiBundle\Event\WikiPageUnarchivedEvent;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizerInterface;
use Nowo\WikiBundle\Util\WikiSlugger;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Creates pages and appends immutable revisions on each save (restores included). Events are dispatched after the
 * repositories flushed.
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
     * @param EventDispatcherInterface $eventDispatcher dispatches the page events
     * @param LoggerInterface|null $logger logger (defaults to a NullLogger)
     * @param WikiAttachmentReferenceFilter|null $attachmentFilter drops attachment URLs of other spaces (1.9)
     */
    public function __construct(
        private WikiPageRepositoryInterface $pageRepository,
        private WikiPageRevisionRepositoryInterface $revisionRepository,
        private WikiSlugger $slugger,
        private WikiHtmlSanitizerInterface $htmlSanitizer,
        private EventDispatcherInterface $eventDispatcher,
        ?LoggerInterface $logger = null,
        private ?WikiAttachmentReferenceFilter $attachmentFilter = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    private LoggerInterface $logger;

    /**
     * Creates a page with its first sanitized revision and dispatches {@see WikiEvents::PAGE_SAVED} and
     * {@see WikiEvents::PAGE_CREATED}.
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

        $revision = new WikiPageRevision($page, 1, $this->clean($space, $contentHtml), $author);
        $this->revisionRepository->save($revision);

        $page->setCurrentRevision($revision);
        $this->pageRepository->save($page);

        $this->eventDispatcher->dispatch(new WikiPageSavedEvent($page, $revision, null, $author), WikiEvents::PAGE_SAVED);
        $this->eventDispatcher->dispatch(new WikiPageCreatedEvent($page, $author), WikiEvents::PAGE_CREATED);

        return $page;
    }

    /**
     * Appends a new sanitized revision to a page, updates its title and dispatches {@see WikiEvents::PAGE_SAVED}.
     *
     * @param WikiPage $page page to update
     * @param string $title new page title
     * @param string $contentHtml new HTML content (sanitized before storage)
     * @param object $author application user recorded as revision author
     *
     * @throws InvalidArgumentException when the page is archived
     *
     * @return WikiPageRevision the new revision
     */
    public function saveRevision(WikiPage $page, string $title, string $contentHtml, object $author): WikiPageRevision
    {
        if ($page->isArchived()) {
            throw new InvalidArgumentException('Archived pages cannot be edited.');
        }

        $previous = $page->getCurrentRevision();
        $page->setTitle($title);
        $revision = $this->appendRevision($page, $this->clean($page->getSpace(), $contentHtml), $author);

        $this->eventDispatcher->dispatch(new WikiPageSavedEvent($page, $revision, $previous, $author), WikiEvents::PAGE_SAVED);

        return $revision;
    }

    /**
     * Restores an old revision: appends a new revision with its body (history stays append-only; the title is kept),
     * then dispatches {@see WikiEvents::PAGE_SAVED} and {@see WikiEvents::PAGE_RESTORED} (1.9).
     *
     * @param WikiPage $page page to restore
     * @param WikiPageRevision $from revision whose body is copied
     * @param object $actor user who restores it
     *
     * @throws InvalidArgumentException when the page is archived or the revision belongs to another page
     *
     * @return WikiPageRevision the new revision
     */
    public function restore(WikiPage $page, WikiPageRevision $from, object $actor): WikiPageRevision
    {
        if ($page->isArchived()) {
            throw new InvalidArgumentException('Archived pages cannot be restored.');
        }

        if ($from->getPage()->getId() !== $page->getId()) {
            throw new InvalidArgumentException('The revision belongs to another page.');
        }

        $previous = $page->getCurrentRevision();
        $revision = $this->appendRevision($page, $this->clean($page->getSpace(), $from->getContentHtml()), $actor);

        $this->logger->info('Wiki page revision restored.', [
            'bundle'   => 'nowo_wiki',
            'action'   => 'restore',
            'space'    => $page->getSpace()->getSlug(),
            'page'     => $page->getSlug(),
            'from'     => $from->getRevisionNumber(),
            'revision' => $revision->getRevisionNumber(),
        ]);

        $this->eventDispatcher->dispatch(new WikiPageSavedEvent($page, $revision, $previous, $actor), WikiEvents::PAGE_SAVED);
        $this->eventDispatcher->dispatch(new WikiPageRestoredEvent($page, $from, $revision, $actor), WikiEvents::PAGE_RESTORED);

        return $revision;
    }

    /**
     * Archives a page and dispatches {@see WikiEvents::PAGE_ARCHIVED}; no-op when it is already archived.
     *
     * @param WikiPage $page page to archive
     * @param object|null $actor user who archives it (1.9)
     */
    public function archive(WikiPage $page, ?object $actor = null): void
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

        $this->eventDispatcher->dispatch(new WikiPageArchivedEvent($page, $actor), WikiEvents::PAGE_ARCHIVED);
    }

    /**
     * Brings an archived page back and dispatches {@see WikiEvents::PAGE_UNARCHIVED}; no-op when it is active (1.9).
     *
     * @param WikiPage $page page to unarchive
     * @param object|null $actor user who unarchives it
     */
    public function unarchive(WikiPage $page, ?object $actor = null): void
    {
        if (!$page->isArchived()) {
            return;
        }

        $page->unarchive();
        $this->pageRepository->save($page);

        $this->logger->info('Wiki page unarchived.', [
            'bundle' => 'nowo_wiki',
            'action' => 'unarchive',
            'space'  => $page->getSpace()->getSlug(),
            'page'   => $page->getSlug(),
        ]);

        $this->eventDispatcher->dispatch(new WikiPageUnarchivedEvent($page, $actor), WikiEvents::PAGE_UNARCHIVED);
    }

    private function appendRevision(WikiPage $page, string $html, object $author): WikiPageRevision
    {
        $number   = $this->revisionRepository->getNextRevisionNumber($page);
        $revision = new WikiPageRevision($page, $number, $html, $author);
        $this->revisionRepository->save($revision);

        $page->setCurrentRevision($revision);
        $this->pageRepository->save($page);

        return $revision;
    }

    private function clean(WikiSpace $space, string $html): string
    {
        $html = $this->htmlSanitizer->sanitize($html);

        return $this->attachmentFilter instanceof WikiAttachmentReferenceFilter ? $this->attachmentFilter->filter($space, $html) : $html;
    }
}
