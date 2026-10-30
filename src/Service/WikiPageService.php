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
use Nowo\WikiBundle\Markdown\WikiMarkdownRenderer;
use Nowo\WikiBundle\Markdown\WikiMarkdownRendererInterface;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizerInterface;
use Nowo\WikiBundle\Util\WikiSlugger;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

use function rtrim;
use function sprintf;
use function str_replace;

/**
 * Creates pages and appends immutable revisions on each save (restores included). Events are dispatched after the
 * repositories flushed.
 *
 * 1.11: content is HTML ({@see WikiPageRevision::FORMAT_HTML}, sanitized before storage) or Markdown
 * ({@see WikiPageRevision::FORMAT_MARKDOWN}: stored as the source, rendered by {@see WikiMarkdownRendererInterface}
 * and sanitized into the `content_html` cache).
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
     * @param WikiMarkdownRendererInterface|null $markdownRenderer renders Markdown revisions (1.11; default renderer
     *                                                             when null)
     */
    public function __construct(
        private WikiPageRepositoryInterface $pageRepository,
        private WikiPageRevisionRepositoryInterface $revisionRepository,
        private WikiSlugger $slugger,
        private WikiHtmlSanitizerInterface $htmlSanitizer,
        private EventDispatcherInterface $eventDispatcher,
        ?LoggerInterface $logger = null,
        private ?WikiAttachmentReferenceFilter $attachmentFilter = null,
        ?WikiMarkdownRendererInterface $markdownRenderer = null,
    ) {
        $this->logger           = $logger ?? new NullLogger();
        $this->markdownRenderer = $markdownRenderer ?? new WikiMarkdownRenderer();
    }

    private LoggerInterface $logger;

    private WikiMarkdownRendererInterface $markdownRenderer;

    /**
     * Creates a page with its first sanitized revision and dispatches {@see WikiEvents::PAGE_SAVED} and
     * {@see WikiEvents::PAGE_CREATED}.
     *
     * @param WikiSpace $space space the page belongs to
     * @param string $title page title
     * @param string $contentHtml initial content: HTML (sanitized before storage) or Markdown, see $format
     * @param object $author application user recorded as revision author
     * @param WikiPage|null $parent optional parent page
     * @param string|null $slug explicit slug, or null to slugify the title
     * @param string $format content format, {@see WikiPageRevision::FORMAT_HTML} or
     *                       {@see WikiPageRevision::FORMAT_MARKDOWN} (1.11)
     *
     * @throws InvalidArgumentException when the slug is invalid or already exists in the space, or the format is unknown
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
        string $format = WikiPageRevision::FORMAT_HTML,
    ): WikiPage {
        $this->assertFormat($format);
        $slug ??= $this->slugger->slugify($title);
        if (!$this->slugger->isValid($slug)) {
            throw new InvalidArgumentException('Invalid page slug.');
        }

        if ($this->pageRepository->countBySpaceAndSlug($space, $slug) > 0) {
            throw new InvalidArgumentException('Page slug already exists in this space.');
        }

        $page = new WikiPage($space, $slug, $title, $parent);
        $this->pageRepository->save($page);

        $revision = $this->newRevision($page, 1, $contentHtml, $format, $author);
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
     * @param string $contentHtml new content: HTML (sanitized before storage) or Markdown, see $format
     * @param object $author application user recorded as revision author
     * @param string $format content format, {@see WikiPageRevision::FORMAT_HTML} or
     *                       {@see WikiPageRevision::FORMAT_MARKDOWN} (1.11)
     *
     * @throws InvalidArgumentException when the page is archived or the format is unknown
     *
     * @return WikiPageRevision the new revision
     */
    public function saveRevision(
        WikiPage $page,
        string $title,
        string $contentHtml,
        object $author,
        string $format = WikiPageRevision::FORMAT_HTML,
    ): WikiPageRevision {
        $this->assertFormat($format);
        if ($page->isArchived()) {
            throw new InvalidArgumentException('Archived pages cannot be edited.');
        }

        $previous = $page->getCurrentRevision();
        $page->setTitle($title);
        $revision = $this->appendRevision($page, $contentHtml, $format, $author);

        $this->eventDispatcher->dispatch(new WikiPageSavedEvent($page, $revision, $previous, $author), WikiEvents::PAGE_SAVED);

        return $revision;
    }

    /**
     * Restores an old revision: appends a new revision with its body in its own format (history stays append-only; the
     * title is kept; a Markdown revision is rendered again), then dispatches {@see WikiEvents::PAGE_SAVED} and {@see WikiEvents::PAGE_RESTORED} (1.9).
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
        $revision = $from->isMarkdown()
            ? $this->appendRevision($page, (string) $from->getContentMarkdown(), WikiPageRevision::FORMAT_MARKDOWN, $actor)
            : $this->appendRevision($page, $from->getContentHtml(), WikiPageRevision::FORMAT_HTML, $actor);

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
     * Appends a Markdown revision converted from the page's HTML (1.11, `wiki:convert-markdown`): the title is kept,
     * archived pages are converted too and {@see WikiEvents::PAGE_SAVED} is dispatched as for any save.
     *
     * @param WikiPage $page page to convert
     * @param string $markdown Markdown of the current HTML revision
     * @param object $actor user recorded as revision author
     *
     * @return WikiPageRevision the new Markdown revision
     */
    public function convertToMarkdown(WikiPage $page, string $markdown, object $actor): WikiPageRevision
    {
        $previous = $page->getCurrentRevision();
        $revision = $this->appendRevision($page, $markdown, WikiPageRevision::FORMAT_MARKDOWN, $actor);

        $this->logger->info('Wiki page converted to Markdown.', [
            'bundle'   => 'nowo_wiki',
            'action'   => 'convert_markdown',
            'space'    => $page->getSpace()->getSlug(),
            'page'     => $page->getSlug(),
            'from'     => $previous?->getRevisionNumber(),
            'revision' => $revision->getRevisionNumber(),
        ]);

        $this->eventDispatcher->dispatch(new WikiPageSavedEvent($page, $revision, $previous, $actor), WikiEvents::PAGE_SAVED);

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

    private function appendRevision(WikiPage $page, string $content, string $format, object $author): WikiPageRevision
    {
        $number   = $this->revisionRepository->getNextRevisionNumber($page);
        $revision = $this->newRevision($page, $number, $content, $format, $author);
        $this->revisionRepository->save($revision);

        $page->setCurrentRevision($revision);
        $this->pageRepository->save($page);

        return $revision;
    }

    private function newRevision(WikiPage $page, int $number, string $content, string $format, object $author): WikiPageRevision
    {
        if ($format === WikiPageRevision::FORMAT_MARKDOWN) {
            $markdown = rtrim(str_replace(["\r\n", "\r"], "\n", $content));

            return new WikiPageRevision($page, $number, $this->clean($page->getSpace(), $this->markdownRenderer->render($markdown)), $author, $markdown);
        }

        return new WikiPageRevision($page, $number, $this->clean($page->getSpace(), $content), $author);
    }

    private function assertFormat(string $format): void
    {
        if ($format !== WikiPageRevision::FORMAT_HTML && $format !== WikiPageRevision::FORMAT_MARKDOWN) {
            throw new InvalidArgumentException(sprintf('Unknown revision format "%s".', $format));
        }
    }

    private function clean(WikiSpace $space, string $html): string
    {
        $html = $this->htmlSanitizer->sanitize($html);

        return $this->attachmentFilter instanceof WikiAttachmentReferenceFilter ? $this->attachmentFilter->filter($space, $html) : $html;
    }
}
