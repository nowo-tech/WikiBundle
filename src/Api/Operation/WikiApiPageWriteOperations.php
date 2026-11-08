<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Operation;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use InvalidArgumentException;
use Nowo\WikiBundle\Api\Hook\WikiApiDirectoryInterface;
use Nowo\WikiBundle\Api\Security\WikiApiGuard;
use Nowo\WikiBundle\Api\Serializer\WikiApiPeople;
use Nowo\WikiBundle\Api\Serializer\WikiApiSerializer;
use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Nowo\WikiBundle\Api\WikiApiCapability;
use Nowo\WikiBundle\Api\WikiApiInput;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiRefusal;
use Nowo\WikiBundle\Collaboration\WikiCollabService;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Event\WikiApiWriteEvent;
use Nowo\WikiBundle\Event\WikiQuotaCheckEvent;
use Nowo\WikiBundle\Markdown\WikiMarkdownLimitsInterface;
use Nowo\WikiBundle\Markdown\WikiMarkdownRendererInterface;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Security\WikiPageVisibility;
use Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface;
use Nowo\WikiBundle\Service\WikiEventDeferral;
use Nowo\WikiBundle\Service\WikiPageMoveException;
use Nowo\WikiBundle\Service\WikiPageMover;
use Nowo\WikiBundle\Service\WikiPageMoveResult;
use Nowo\WikiBundle\Service\WikiPageMoveScope;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Service\WikiRevisionCollisionException;
use Nowo\WikiBundle\Service\WikiRevisionConflictException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

use function trim;

use const PHP_INT_MAX;

/**
 * 1.24 (spec 218 FR-021 – FR-030): page writes. Each checks reach, plan, the archived wiki, the level and the person
 * (in that order) before anything is written, asks the host's audit and plan limits, writes through the bundle's
 * services (so their events fire as in the UI, with the token's user as actor), and raises one
 * {@see WikiApiWriteEvent} after the commit.
 *
 * Every write runs in a {@see WikiEventDeferral} scope: the page events fire once it committed, and a host listener that
 * fails then is logged, never turned into a refusal of a write that stands (audit IF-L3), so the audit event and the
 * idempotent answer always follow a committed write.
 *
 * Every method answers `[page body, wiki, page]`; the controller keeps the body.
 */
final readonly class WikiApiPageWriteOperations
{
    public function __construct(
        private WikiApiGuard $guard,
        private WikiApiDirectoryInterface $directory,
        private WikiApiAudit $audit,
        private WikiPageService $service,
        private WikiPageRepositoryInterface $pages,
        private WikiSpaceAccessCheckerInterface $checker,
        private WikiMarkdownRendererInterface $renderer,
        private WikiApiPageReadOperations $reads,
        private WikiApiPeople $people,
        private WikiEventDeferral $deferral,
        private ?WikiCollabService $collab = null,
        private ?WikiPageMover $mover = null,
        private ?RequestStack $requestStack = null,
    ) {
    }

    /**
     * `POST /wikis/{wiki}/pages`: `title`, `content_markdown`, optional `parent` and `slug`.
     *
     * @throws WikiApiRefusal
     *
     * @return array{0: array<string, mixed>, 1: WikiApiWiki, 2: WikiPage}
     */
    public function create(WikiApiPrincipal $principal, string $slug, WikiApiInput $input): array
    {
        $wiki = $this->guard->wiki($principal, $slug);
        $this->guard->requireWritable($wiki);
        if (!WikiApiCapability::allows($principal->level, WikiAction::Create)) {
            throw WikiApiRefusal::levelRefused();
        }
        $this->guard->openSpace($wiki);
        $title    = $this->title($input, true) ?? throw WikiApiRefusal::invalid('title');
        $markdown = $this->markdown($input, false) ?? '';
        $parentId = $input->string('parent', false, 36);
        $pageSlug = $input->string('slug', false, 120);
        $this->audit->requireAudit();

        // The first write of a wiki nobody opened creates it, with the rights of a first visit (FR-009).
        $space = $wiki->space ?? $this->directory->provision($principal, $wiki);
        $wiki  = $wiki->withSpace($space);
        $this->guard->require($principal, WikiAction::Create, $space);
        $parent = null;
        if ($parentId !== null) {
            $parent = $this->guard->page($principal, $wiki, $parentId);
            $this->guard->require($principal, WikiAction::Edit, $space, $parent);
        }
        $this->audit->requireQuota(WikiQuotaCheckEvent::PAGE_CREATE, $principal->user, $space);

        try {
            $page = $this->deferral->run(fn (): WikiPage => $this->service->create($space, $title, $markdown, $principal->user, $parent, $pageSlug, WikiPageRevision::FORMAT_MARKDOWN));
        } catch (InvalidArgumentException $exception) {
            throw $exception->getMessage() === 'Invalid page slug.' ? WikiApiRefusal::invalid('slug') : new WikiApiRefusal(409, 'WIKI_003', 'This slug is taken');
        } catch (UniqueConstraintViolationException) {
            // Another create took the slug between the check and the insert (audit IF-I10): nothing was written.
            throw new WikiApiRefusal(409, 'WIKI_003', 'This slug is taken');
        }
        $this->audit->written($principal, 'page.create', $wiki->slug, $space->getId(), $page->getId(), [], ['title', 'content_markdown']);

        return [$this->reads->body($principal, $wiki, $page), $wiki, $page];
    }

    /**
     * `PUT …/pages/{page}`: `content_markdown`, the mandatory `base_revision`, optional `title` and `allow_empty`.
     *
     * @throws WikiApiRefusal
     *
     * @return array{0: array<string, mixed>, 1: WikiApiWiki, 2: WikiPage}
     */
    public function update(WikiApiPrincipal $principal, string $slug, string $pageId, WikiApiInput $input): array
    {
        [$wiki, $page] = $this->target($principal, $slug, $pageId, WikiAction::Edit);
        $base          = $this->baseRevision($input);
        $title         = $this->title($input, false) ?? $page->getTitle();
        $markdown      = $this->markdown($input, true) ?? '';
        $current       = $page->getCurrentRevision();
        $titleChanged  = $title !== $page->getTitle();
        if (trim($markdown) === '' && $input->bool('allow_empty') !== true && trim((string) ($current?->getContentMarkdown() ?? $current?->getContentText() ?? '')) !== '') {
            throw new WikiApiRefusal(422, 'WIKI_EMPTY_BODY', 'An empty body would replace this page; send allow_empty: true');
        }
        $this->audit->requireAudit();

        $revision = $this->underCollab($principal, $page, fn (): WikiPageRevision => $this->service->saveRevision($page, $title, $markdown, $principal->user, WikiPageRevision::FORMAT_MARKDOWN, $base));
        if ($revision->getRevisionNumber() !== $base) {
            $this->audit->written($principal, 'page.update', $wiki->slug, $page->getSpace()->getId(), $page->getId(), ['revision' => $revision->getId()], $titleChanged ? ['title', 'content_markdown'] : ['content_markdown']);
        }

        return [$this->reads->body($principal, $wiki, $page), $wiki, $page];
    }

    /**
     * `POST …/revisions/{number}/restore`: the mandatory `base_revision`.
     *
     * @throws WikiApiRefusal
     *
     * @return array{0: array<string, mixed>, 1: WikiApiWiki, 2: WikiPage}
     */
    public function restore(WikiApiPrincipal $principal, string $slug, string $pageId, string $number, WikiApiInput $input): array
    {
        [$wiki, $page] = $this->target($principal, $slug, $pageId, WikiAction::Restore);
        $from          = $this->guard->revision($page, $number);
        $base          = $this->baseRevision($input);
        $this->audit->requireAudit();

        $revision = $this->underCollab($principal, $page, fn (): WikiPageRevision => $this->service->restore($page, $from, $principal->user, $base));
        $this->audit->written($principal, 'page.restore', $wiki->slug, $page->getSpace()->getId(), $page->getId(), ['revision' => $revision->getId(), 'from' => $from->getId()], ['content_markdown']);

        return [$this->reads->body($principal, $wiki, $page), $wiki, $page];
    }

    /**
     * `POST …/pages/{page}/move`: `parent` (a page id, or null for the top level) and `index` (0-based).
     *
     * @throws WikiApiRefusal
     *
     * @return array{0: array<string, mixed>, 1: WikiApiWiki, 2: WikiPage}
     */
    public function move(WikiApiPrincipal $principal, string $slug, string $pageId, WikiApiInput $input): array
    {
        if (!$this->mover instanceof WikiPageMover) {
            throw WikiApiRefusal::notFound();
        }
        [$wiki, $page] = $this->target($principal, $slug, $pageId, WikiAction::Edit);
        $space         = $page->getSpace();
        $parentId      = $input->has('parent') ? $input->string('parent', false, 36) : $page->getParent()?->getId();
        $index         = $input->int('index') ?? PHP_INT_MAX;
        $from          = $page->getParent();
        $parent        = $parentId === null ? null : $this->guard->page($principal, $wiki, $parentId);
        // The rights of the tree (WikiTreeMoveTrait): Edit on the page, on its parent and on the new parent.
        foreach ([$from, $parent] as $node) {
            $this->guard->require($principal, WikiAction::Edit, $space, $node);
        }
        $this->audit->requireAudit();

        $readable  = [];
        $decisions = [];
        foreach ($this->pages->findActiveBySpace($space) as $candidate) {
            if (WikiPageVisibility::isReadable($this->checker, $principal->user, $space, $candidate, $decisions)) {
                $readable[$candidate->getId()] = true;
            }
        }
        try {
            $mover  = $this->mover;
            $result = $this->deferral->run(static fn (): WikiPageMoveResult => $mover->move($page, $parent, $index, $principal->user, new WikiPageMoveScope($readable, $from?->getId())));
        } catch (WikiPageMoveException $exception) {
            throw match ($exception->reason) {
                'too_deep'                       => new WikiApiRefusal(422, 'WIKI_MOVE_002', 'The page would be too deep'),
                'page_missing', 'parent_missing' => WikiApiRefusal::notFound(),
                default                          => new WikiApiRefusal(409, 'WIKI_MOVE_001', 'The tree changed, or the move would make a cycle'),
            };
        }
        if ($result->moved) {
            $this->audit->written($principal, 'page.move', $wiki->slug, $space->getId(), $page->getId(), $parent === null ? [] : ['parent' => $parent->getId()], ['parent', 'position']);
        }

        return [$this->reads->body($principal, $wiki, $page), $wiki, $page];
    }

    /**
     * `POST …/pages/{page}/archive` and `/unarchive`: one's own pages at Edit, anyone's at Full (contract F7).
     *
     * @throws WikiApiRefusal
     *
     * @return array{0: array<string, mixed>, 1: WikiApiWiki, 2: WikiPage}
     */
    public function archive(WikiApiPrincipal $principal, string $slug, string $pageId, bool $archive): array
    {
        [$wiki, $page] = $this->target($principal, $slug, $pageId, WikiAction::Archive, true);
        $this->guard->requireOwnPage($principal, $page);
        $this->audit->requireAudit();
        if ($archive === $page->isArchived()) {
            return [$this->reads->body($principal, $wiki, $page), $wiki, $page];
        }
        if (!$archive) {
            $this->audit->requireQuota(WikiQuotaCheckEvent::PAGE_UNARCHIVE, $principal->user, $page->getSpace(), $page);
        }
        $this->deferral->run(fn () => $archive ? $this->service->archive($page, $principal->user) : $this->service->unarchive($page, $principal->user));
        $this->audit->written($principal, $archive ? 'page.archive' : 'page.unarchive', $wiki->slug, $page->getSpace()->getId(), $page->getId(), [], ['archived']);

        return [$this->reads->body($principal, $wiki, $page), $wiki, $page];
    }

    /**
     * The page a write targets: reach, the archived wiki, level and person.
     *
     * @throws WikiApiRefusal
     *
     * @return array{0: WikiApiWiki, 1: WikiPage}
     */
    private function target(WikiApiPrincipal $principal, string $slug, string $pageId, WikiAction $action, bool $archived = false): array
    {
        $wiki = $this->guard->wiki($principal, $slug);
        $page = $this->guard->page($principal, $wiki, $pageId, $archived);
        $this->guard->requireWritable($wiki);
        $this->guard->require($principal, $action, $page->getSpace(), $page);

        return [$wiki, $page];
    }

    /**
     * Runs a save of the page's body: outside the live co-editing session, it is refused over updates no revision has
     * (FR-025), and it resets the log; the base revision is compared under the page lock.
     *
     * @param callable(): WikiPageRevision $save
     *
     * @throws WikiApiRefusal
     */
    private function underCollab(WikiApiPrincipal $principal, WikiPage $page, callable $save): WikiPageRevision
    {
        $revision = null;
        $run      = static function () use ($save, &$revision): void {
            $revision = $save();
        };
        try {
            if ($this->collab instanceof WikiCollabService) {
                $request = $this->requestStack?->getCurrentRequest() ?? new Request();
                if (!$this->collab->saveOutsideSession($request, $page, $run)) {
                    throw new WikiApiRefusal(409, 'WIKI_LIVE_SESSION', 'Someone is editing this page live; their changes are not saved yet');
                }
            } else {
                $this->deferral->run($run);
            }
        } catch (WikiRevisionConflictException $conflict) {
            throw new WikiApiRefusal(409, 'WIKI_REVISION_CONFLICT', 'The page changed since base_revision', ['current_revision' => $conflict->currentRevision, 'updated_by' => $this->people->present($principal, $conflict->updatedBy, $page), 'updated_at' => WikiApiSerializer::ms($conflict->updatedAt)]);
        } catch (WikiRevisionCollisionException) {
            throw new WikiApiRefusal(409, 'WIKI_SAVE_COLLISION', 'Another save got there first; nothing was written', [], ['Retry-After' => '1']);
        } catch (InvalidArgumentException) {
            throw new WikiApiRefusal(409, 'WIKI_PAGE_ARCHIVED', 'This page is archived');
        }

        return $revision ?? throw new WikiApiRefusal(500, 'SERVER_001', 'Server error');
    }

    /** @throws WikiApiRefusal */
    private function baseRevision(WikiApiInput $input): int
    {
        try {
            $base = $input->int('base_revision');
        } catch (WikiApiRefusal) {
            $base = null;
        }

        return $base ?? throw new WikiApiRefusal(422, 'INPUT_004', 'base_revision is required');
    }

    /** @throws WikiApiRefusal */
    private function title(WikiApiInput $input, bool $required): ?string
    {
        $title = $input->string('title', $required, 255);
        if ($title !== null && trim($title) === '') {
            throw WikiApiRefusal::invalid('title');
        }

        return $title === null ? null : trim($title);
    }

    /** @throws WikiApiRefusal */
    private function markdown(WikiApiInput $input, bool $required): ?string
    {
        $markdown = $input->string('content_markdown', $required);
        if ($markdown !== null && $this->renderer instanceof WikiMarkdownLimitsInterface && $this->renderer->exceedsLimits($markdown)) {
            throw new WikiApiRefusal(413, 'WIKI_TOO_LARGE', 'The body is over the Markdown limits');
        }

        return $markdown;
    }
}
