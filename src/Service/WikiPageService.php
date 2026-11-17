<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityNotFoundException;
use LogicException;
use Nowo\TransactionContract\TransientStorageFailure;
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
use Nowo\WikiBundle\Provenance\WikiWriteProvenance;
use Nowo\WikiBundle\Provenance\WikiWriteProvenanceProviderInterface;
use Nowo\WikiBundle\Repository\WikiPageLockInterface;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Security\WikiHtmlSanitizerInterface;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;
use Nowo\WikiBundle\User\WikiUserReferenceResolverInterface;
use Nowo\WikiBundle\Util\WikiSlugger;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

use function max;
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
final readonly class WikiPageService implements WikiPageServiceInterface
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
     * @param WikiEventDeferral|null $deferral 1.24: holds the page events of a write inside an outer transaction until
     *                                         it commits (audit IF-L2)
     * @param WikiUserReferenceResolverInterface|null $users 1.27: the stored revision author for a security user that
     *                                                       is no `user_class` entity
     * @param WikiWriteProvenanceProviderInterface|null $provenance Spec 234: the channel and actor label of each
     *                                                              new revision (null: nothing recorded)
     * @param WikiTransactionRunner|null $runner 1.37 (Beacon spec 239): one transaction per write, the page row locked
     *                                           and read again under it (null: the writes of 1.36)
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
        private ?WikiEventDeferral $deferral = null,
        private ?WikiUserReferenceResolverInterface $users = null,
        private ?WikiWriteProvenanceProviderInterface $provenance = null,
        private ?WikiTransactionRunner $runner = null,
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
     * @throws WikiInvalidSlugException when the slug is invalid (an {@see \InvalidArgumentException}, 1.40.2)
     * @throws WikiSlugTakenException when the slug already exists in the space (an {@see \InvalidArgumentException})
     * @throws WikiUnknownFormatException when the format is unknown (an {@see \InvalidArgumentException})
     * @throws UniqueConstraintViolationException when another create took the slug meanwhile (nothing written)
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
            throw new WikiInvalidSlugException('Invalid page slug.');
        }

        if ($this->pageRepository->countBySpaceAndSlug($space, $slug) > 0) {
            throw new WikiSlugTakenException('Page slug already exists in this space.');
        }

        // 1.37: the page, its first revision and the pointer to it in one transaction (a failure in between left a page
        // without a revision, whose slug stayed taken).
        [$page, $revision] = $this->unit(function () use ($space, $slug, $title, $parent, $contentHtml, $format, $author): array {
            $page = new WikiPage($space, $slug, $title, $parent);
            $this->pageRepository->save($page);

            $revision = $this->newRevision($page, 1, $contentHtml, $format, $author);
            $this->revisionRepository->save($revision);

            $page->setCurrentRevision($revision);
            $this->pageRepository->save($page);

            return [$page, $revision];
        });

        $this->saved(new WikiPageSavedEvent($page, $revision, null, $author));
        $this->dispatch(new WikiPageCreatedEvent($page, $author), WikiEvents::PAGE_CREATED);

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
     * @param int|null $expectedRevision 1.24 (spec 218): the revision number this save started from. With it, the page
     *                                   row is locked in a transaction, the revision is compared under the lock, and a
     *                                   title and body equal to the current revision's write nothing and return it.
     *                                   Null keeps the last-write-wins save of earlier versions
     *
     * @throws WikiPageArchivedException when the page is archived (an {@see \InvalidArgumentException}, 1.40.2)
     * @throws WikiUnknownFormatException when the format is unknown (an {@see \InvalidArgumentException})
     * @throws WikiRevisionCollisionException when the revision write lost a race: nothing was written (1.22)
     * @throws WikiRevisionConflictException when the current revision is not the expected one: nothing was written (1.24)
     * @throws LogicException when an expected revision is given and the page repository cannot lock a page (1.24)
     *
     * @return WikiPageRevision the new revision (or, with an expected revision and nothing changed, the current one)
     */
    public function saveRevision(
        WikiPage $page,
        string $title,
        string $contentHtml,
        object $author,
        string $format = WikiPageRevision::FORMAT_HTML,
        ?int $expectedRevision = null,
    ): WikiPageRevision {
        $this->assertFormat($format);
        if ($expectedRevision !== null) {
            return $this->saveFromRevision($page, $title, $contentHtml, $author, $format, $expectedRevision);
        }
        if ($this->runner instanceof WikiTransactionRunner && $this->pageRepository instanceof WikiPageLockInterface) {
            // 1.37: the last-write-wins save under the page lock: the page is read again (archived meanwhile, the current
            // revision), the number follows the current one, and the revision and the page's pointer commit together.
            // Two saves at once no longer collide on the number or leave the page on the older revision.
            [$revision, $previous] = $this->underPageLock($page, null, 'Archived pages cannot be edited.', static fn (): ?WikiPageRevision => null, function (?WikiPageRevision $current) use ($page, $title, $contentHtml, $format, $author): WikiPageRevision {
                $page->setTitle($title);

                return $this->appendRevision($page, $contentHtml, $format, $author, $this->nextAfter($page, $current?->getRevisionNumber() ?? 0));
            });
            $this->saved(new WikiPageSavedEvent($page, $revision, $previous, $author));

            return $revision;
        }
        if ($page->isArchived()) {
            throw new WikiPageArchivedException('Archived pages cannot be edited.');
        }

        $previous = $page->getCurrentRevision();
        $page->setTitle($title);
        $revision = $this->appendRevision($page, $contentHtml, $format, $author);

        $this->saved(new WikiPageSavedEvent($page, $revision, $previous, $author));

        return $revision;
    }

    /**
     * Restores an old revision: appends a new revision with its body in its own format (history stays append-only; the
     * title is kept; a Markdown revision is rendered again), then dispatches {@see WikiEvents::PAGE_SAVED} and {@see WikiEvents::PAGE_RESTORED} (1.9).
     *
     * @param WikiPage $page page to restore
     * @param WikiPageRevision $from revision whose body is copied
     * @param object $actor user who restores it
     * @param int|null $expectedRevision 1.24 (spec 218): the revision number the restore started from, compared under
     *                                   the page lock as in {@see self::saveRevision()}; a restore always appends
     *
     * @throws WikiPageArchivedException when the page is archived (an {@see \InvalidArgumentException}, 1.40.2)
     * @throws WikiRevisionMismatchException when the revision belongs to another page (an {@see \InvalidArgumentException})
     * @throws WikiRevisionCollisionException when the revision write lost a race: nothing was written (1.22)
     * @throws WikiRevisionConflictException when the current revision is not the expected one: nothing was written (1.24)
     * @throws LogicException when an expected revision is given and the page repository cannot lock a page (1.24)
     *
     * @return WikiPageRevision the new revision
     */
    public function restore(WikiPage $page, WikiPageRevision $from, object $actor, ?int $expectedRevision = null): WikiPageRevision
    {
        if ($expectedRevision === null && $page->isArchived()) {
            throw new WikiPageArchivedException('Archived pages cannot be restored.');
        }

        if ($from->getPage()->getId() !== $page->getId()) {
            throw new WikiRevisionMismatchException('The revision belongs to another page.');
        }

        $previous = $page->getCurrentRevision();
        $append   = fn (): WikiPageRevision => $from->isMarkdown()
            ? $this->appendRevision($page, (string) $from->getContentMarkdown(), WikiPageRevision::FORMAT_MARKDOWN, $actor)
            : $this->appendRevision($page, $from->getContentHtml(), WikiPageRevision::FORMAT_HTML, $actor);
        if ($expectedRevision === null && $this->runner instanceof WikiTransactionRunner && $this->pageRepository instanceof WikiPageLockInterface) {
            // 1.37: as the last-write-wins save, under the page lock.
            [$revision, $previous] = $this->underPageLock($page, null, 'Archived pages cannot be restored.', static fn (): ?WikiPageRevision => null, fn (?WikiPageRevision $current): WikiPageRevision => $from->isMarkdown()
                ? $this->appendRevision($page, (string) $from->getContentMarkdown(), WikiPageRevision::FORMAT_MARKDOWN, $actor, $this->nextAfter($page, $current?->getRevisionNumber() ?? 0))
                : $this->appendRevision($page, $from->getContentHtml(), WikiPageRevision::FORMAT_HTML, $actor, $this->nextAfter($page, $current?->getRevisionNumber() ?? 0)));
        } elseif ($expectedRevision === null) {
            $revision = $append();
        } else {
            [$revision, $previous] = $this->underPageLock($page, $expectedRevision, 'Archived pages cannot be restored.', static fn (): ?WikiPageRevision => null, fn (): WikiPageRevision => $from->isMarkdown()
                ? $this->appendRevision($page, (string) $from->getContentMarkdown(), WikiPageRevision::FORMAT_MARKDOWN, $actor, $this->nextAfter($page, $expectedRevision))
                : $this->appendRevision($page, $from->getContentHtml(), WikiPageRevision::FORMAT_HTML, $actor, $this->nextAfter($page, $expectedRevision)));
        }

        $this->logger->info('Wiki page revision restored.', [
            'bundle'   => 'nowo_wiki',
            'action'   => 'restore',
            'space'    => $page->getSpace()->getSlug(),
            'page'     => $page->getSlug(),
            'from'     => $from->getRevisionNumber(),
            'revision' => $revision->getRevisionNumber(),
        ]);

        $this->saved(new WikiPageSavedEvent($page, $revision, $previous, $actor));
        $this->dispatch(new WikiPageRestoredEvent($page, $from, $revision, $actor), WikiEvents::PAGE_RESTORED);

        return $revision;
    }

    /**
     * Appends a Markdown revision converted from the page's HTML (1.11, `wiki:convert-markdown`): the title is kept,
     * archived pages are converted too and {@see WikiEvents::PAGE_SAVED} is dispatched with
     * {@see WikiPageSavedEvent::$conversion} set (index it, do not notify).
     *
     * @param WikiPage $page page to convert
     * @param string $markdown Markdown of the current HTML revision
     * @param object $actor user recorded as revision author
     *
     * @throws WikiRevisionCollisionException when the revision write lost a race: nothing was written (1.22)
     *
     * @return WikiPageRevision the new Markdown revision
     */
    public function convertToMarkdown(WikiPage $page, string $markdown, object $actor): WikiPageRevision
    {
        $previous = $page->getCurrentRevision();
        if ($this->runner instanceof WikiTransactionRunner && $this->pageRepository instanceof WikiPageLockInterface) {
            // 1.37: under the page lock like a save (archived pages included).
            [$revision, $previous] = $this->underPageLock($page, null, null, static fn (): ?WikiPageRevision => null, fn (?WikiPageRevision $current): WikiPageRevision => $this->appendRevision($page, $markdown, WikiPageRevision::FORMAT_MARKDOWN, $actor, $this->nextAfter($page, $current?->getRevisionNumber() ?? 0)));
        } else {
            $revision = $this->appendRevision($page, $markdown, WikiPageRevision::FORMAT_MARKDOWN, $actor);
        }

        $this->logger->info('Wiki page converted to Markdown.', [
            'bundle'   => 'nowo_wiki',
            'action'   => 'convert_markdown',
            'space'    => $page->getSpace()->getSlug(),
            'page'     => $page->getSlug(),
            'from'     => $previous?->getRevisionNumber(),
            'revision' => $revision->getRevisionNumber(),
        ]);

        $this->saved(new WikiPageSavedEvent($page, $revision, $previous, $actor, true));

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
        if (!$this->toggleArchived($page, true)) {
            return;
        }

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
        if (!$this->toggleArchived($page, false)) {
            return;
        }

        $this->logger->info('Wiki page unarchived.', [
            'bundle' => 'nowo_wiki',
            'action' => 'unarchive',
            'space'  => $page->getSpace()->getSlug(),
            'page'   => $page->getSlug(),
        ]);

        $this->eventDispatcher->dispatch(new WikiPageUnarchivedEvent($page, $actor), WikiEvents::PAGE_UNARCHIVED);
    }

    /**
     * Archives or brings back a page; false when it already was (nothing written, no event). 1.37: with the transaction
     * runner the page row is locked and read again first, so two requests that both loaded the page active archive it
     * once and only one of them announces it.
     */
    private function toggleArchived(WikiPage $page, bool $archived): bool
    {
        $apply = function () use ($page, $archived): bool {
            if ($page->isArchived() === $archived) {
                return false;
            }
            $archived ? $page->archive() : $page->unarchive();
            $this->pageRepository->save($page);

            return true;
        };
        if (!$this->runner instanceof WikiTransactionRunner) {
            return $apply();
        }
        $runner = $this->runner;

        return $runner->run(static fn (): bool => $runner->lockEntity($page) && $apply());
    }

    /**
     * Runs a write as one unit of work of the transaction runner (1.37), or as it is without one.
     *
     * @template T
     *
     * @param callable(): T $write
     *
     * @return T
     */
    private function unit(callable $write): mixed
    {
        return $this->runner instanceof WikiTransactionRunner ? $this->runner->run($write) : $write();
    }

    /**
     * The save of {@see self::saveRevision()} with an expected revision (1.24).
     */
    private function saveFromRevision(WikiPage $page, string $title, string $content, object $author, string $format, int $expectedRevision): WikiPageRevision
    {
        [$revision, $previous] = $this->underPageLock(
            $page,
            $expectedRevision,
            'Archived pages cannot be edited.',
            fn (?WikiPageRevision $current): ?WikiPageRevision => $current instanceof WikiPageRevision && $this->isUnchanged($page, $current, $title, $content, $format) ? $current : null,
            function () use ($page, $title, $content, $format, $author, $expectedRevision): WikiPageRevision {
                $page->setTitle($title);

                return $this->appendRevision($page, $content, $format, $author, $this->nextAfter($page, $expectedRevision));
            },
        );
        if ($revision !== $previous) {
            $this->saved(new WikiPageSavedEvent($page, $revision, $previous, $author));
        }

        return $revision;
    }

    /**
     * Runs a write with the page row locked (1.24): the page is read again under the lock, then checked (archived, the
     * expected revision), then `$unchanged` may answer the current revision without writing, else `$write` appends.
     * Commit before any event, so no listener hears of a write that was rolled back.
     *
     * 1.37: with the transaction runner the whole of it is one unit of work of the runner (the page transaction is then a
     * savepoint of it); a null expected revision is the last-write-wins save, which compares nothing.
     *
     * @param callable(?WikiPageRevision): ?WikiPageRevision $unchanged
     * @param callable(?WikiPageRevision): WikiPageRevision $write
     *
     * @return array{0: WikiPageRevision, 1: WikiPageRevision|null} the revision written (or the current one) and the
     *                                                              revision that was current under the lock
     */
    private function underPageLock(WikiPage $page, ?int $expectedRevision, ?string $archivedMessage, callable $unchanged, callable $write): array
    {
        if (!$this->pageRepository instanceof WikiPageLockInterface) {
            throw new LogicException('An expected revision needs a page repository that implements ' . WikiPageLockInterface::class . '.');
        }

        $lock = $this->pageRepository;
        if ($this->runner instanceof WikiTransactionRunner) {
            try {
                return $this->runner->run(fn (): array => $this->lockedWrite($lock, $page, $expectedRevision, $archivedMessage, $unchanged, $write));
            } catch (TransientStorageFailure $failure) {
                // A deadlock or lock wait: nothing was written; the caller's answer to a lost race (the text stays with the person).
                throw new WikiRevisionCollisionException('The revision could not be written: another save got there first.', 0, $failure);
            }
        }

        return $this->lockedWrite($lock, $page, $expectedRevision, $archivedMessage, $unchanged, $write);
    }

    /**
     * The page transaction of {@see self::underPageLock()}.
     *
     * @param callable(?WikiPageRevision): ?WikiPageRevision $unchanged
     * @param callable(?WikiPageRevision): WikiPageRevision $write
     *
     * @return array{0: WikiPageRevision, 1: WikiPageRevision|null}
     */
    private function lockedWrite(WikiPageLockInterface $lock, WikiPage $page, ?int $expectedRevision, ?string $archivedMessage, callable $unchanged, callable $write): array
    {
        try {
            $lock->beginPageTransaction($page);
        } catch (RetryableException $exception) {
            throw new WikiRevisionCollisionException('The page could not be locked: another save holds it.', 0, $exception);
        }

        try {
            if ($archivedMessage !== null && $page->isArchived()) {
                throw new WikiPageArchivedException($archivedMessage);
            }
            $current = $page->getCurrentRevision();
            $number  = $current?->getRevisionNumber() ?? 0;
            if ($expectedRevision !== null && $number !== $expectedRevision) {
                throw new WikiRevisionConflictException($expectedRevision, $number, $current?->getAuthor(), $current?->getCreatedAt());
            }
            $revision = $unchanged($current) ?? $write($current);
            $lock->commitPageTransaction();
        } catch (Throwable $exception) {
            $lock->rollbackPageTransaction();
            if ($exception instanceof RetryableException || $exception instanceof EntityNotFoundException) {
                throw new WikiRevisionCollisionException('The revision could not be written: another save got there first.', 0, $exception);
            }

            throw $exception;
        }

        return [$revision, $current];
    }

    /**
     * The number of a save under the page lock: after the current revision, which the lock read with the latest data
     * (an outer snapshot could make `MAX()` stale, audit IF-I2), and past any revision left behind that never became
     * current (a save that died between its two writes, audit IF-I14).
     */
    private function nextAfter(WikiPage $page, int $current): int
    {
        return max($current + 1, $this->revisionRepository->getNextRevisionNumber($page));
    }

    /** Whether a save would store what the current revision already holds: same title, same format, same body. */
    private function isUnchanged(WikiPage $page, WikiPageRevision $current, string $title, string $content, string $format): bool
    {
        if ($page->getTitle() !== $title) {
            return false;
        }

        if ($format === WikiPageRevision::FORMAT_MARKDOWN) {
            return $current->getContentMarkdown() === $this->normalizeMarkdown($content);
        }

        return !$current->isMarkdown() && $current->getContentHtml() === $this->cleanAuthored($page->getSpace(), $content);
    }

    /**
     * A save's events: {@see WikiEvents::PAGE_WRITTEN} at once (the bundle's co-editing bookkeeping, which must run under
     * the log's lock), {@see WikiEvents::PAGE_SAVED} once the outermost transaction committed (1.24, audit IF-L2).
     */
    private function saved(WikiPageSavedEvent $event): void
    {
        $this->eventDispatcher->dispatch($event, WikiEvents::PAGE_WRITTEN);
        $this->dispatch($event, WikiEvents::PAGE_SAVED);
    }

    private function dispatch(object $event, string $name): void
    {
        if ($this->deferral instanceof WikiEventDeferral) {
            $this->deferral->dispatch($this->eventDispatcher, $event, $name);

            return;
        }
        $this->eventDispatcher->dispatch($event, $name);
    }

    private function appendRevision(WikiPage $page, string $content, string $format, object $author, ?int $number = null): WikiPageRevision
    {
        $number ??= $this->revisionRepository->getNextRevisionNumber($page);
        $revision = $this->newRevision($page, $number, $content, $format, $author);
        try {
            $this->revisionRepository->save($revision);
        } catch (RetryableException|UniqueConstraintViolationException $exception) {
            throw new WikiRevisionCollisionException('The revision could not be written: another save got there first.', 0, $exception);
        }

        $page->setCurrentRevision($revision);
        $this->pageRepository->save($page);

        return $revision;
    }

    private function newRevision(WikiPage $page, int $number, string $content, string $format, object $author): WikiPageRevision
    {
        $author = $this->users?->reference($author) ?? $author;
        if ($format === WikiPageRevision::FORMAT_MARKDOWN) {
            $markdown = $this->normalizeMarkdown($content);
            $revision = new WikiPageRevision($page, $number, $this->clean($page->getSpace(), $this->markdownRenderer->render($markdown)), $author, $markdown);
        } else {
            $revision = new WikiPageRevision($page, $number, $this->cleanAuthored($page->getSpace(), $content), $author);
        }
        // Spec 234: where the write came from, as the host says at write time (never from client input).
        $provenance = $this->provenance?->current();
        if ($provenance instanceof WikiWriteProvenance) {
            $revision->recordProvenance($provenance);
        }

        return $revision;
    }

    /**
     * 1.31 (audit NB-W-1): authored HTML never keeps the renderers' reference classes (a forged hidden marker would keep
     * the author's text out of search).
     */
    private function cleanAuthored(WikiSpace $space, string $html): string
    {
        return WikiHtmlSanitizer::withoutServerClasses($this->clean($space, $html));
    }

    private function normalizeMarkdown(string $content): string
    {
        return rtrim(str_replace(["\r\n", "\r"], "\n", $content));
    }

    private function assertFormat(string $format): void
    {
        if ($format !== WikiPageRevision::FORMAT_HTML && $format !== WikiPageRevision::FORMAT_MARKDOWN) {
            throw new WikiUnknownFormatException(sprintf('Unknown revision format "%s".', $format));
        }
    }

    private function clean(WikiSpace $space, string $html): string
    {
        $html = $this->htmlSanitizer->sanitize($html);

        return $this->attachmentFilter instanceof WikiAttachmentReferenceFilter ? $this->attachmentFilter->filter($space, $html) : $html;
    }
}
