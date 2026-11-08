<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Operation;

use Nowo\WikiBundle\Api\Security\WikiApiGuard;
use Nowo\WikiBundle\Api\Serializer\WikiApiContent;
use Nowo\WikiBundle\Api\Serializer\WikiApiPeople;
use Nowo\WikiBundle\Api\Serializer\WikiApiSerializer;
use Nowo\WikiBundle\Api\WikiApiCapability;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiQuery;
use Nowo\WikiBundle\Api\WikiApiRefusal;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiRevisionLabelRepositoryInterface;
use Nowo\WikiBundle\Service\WikiRevisionDiffService;

use function array_map;
use function array_slice;
use function count;

/**
 * 1.24 (spec 218 FR-015, FR-016): history reads. A revision's body follows the page's view; the diff is source, so it
 * needs the source view.
 */
final readonly class WikiApiRevisionOperations
{
    public function __construct(
        private WikiApiGuard $guard,
        private WikiPageRevisionRepositoryInterface $revisions,
        private WikiRevisionDiffService $diffs,
        private WikiApiPeople $people,
        private WikiApiContent $content,
        private WikiApiSerializer $serializer,
        private ?WikiRevisionLabelRepositoryInterface $labels = null,
    ) {
    }

    /**
     * `GET …/revisions`: newest first, 100 per page.
     *
     * @throws WikiApiRefusal
     *
     * @return array{revisions: list<array<string, mixed>>, last_page: bool}
     */
    public function list(WikiApiPrincipal $principal, string $slug, string $pageId, int $page): array
    {
        $target = $this->page($principal, $slug, $pageId);
        $rows   = $this->revisions->findByPage($target, WikiApiQuery::PAGE_SIZE + 1, $page * WikiApiQuery::PAGE_SIZE);
        $shown  = array_slice($rows, 0, WikiApiQuery::PAGE_SIZE);
        $labels = $this->labels?->labelsFor($shown) ?? [];
        $people = $this->people->presentAll($principal, array_map(static fn (WikiPageRevision $r): object => $r->getAuthor(), $shown), $target);
        $answer = [];
        foreach ($shown as $index => $revision) {
            $answer[] = $this->serializer->revisionHead($revision, $people[$index], $labels[$revision->getId()] ?? null);
        }

        return ['revisions' => $answer, 'last_page' => count($rows) <= WikiApiQuery::PAGE_SIZE];
    }

    /**
     * `GET …/revisions/{number}`.
     *
     * @throws WikiApiRefusal
     *
     * @return array<string, mixed>
     */
    public function get(WikiApiPrincipal $principal, string $slug, string $pageId, string $number): array
    {
        $target   = $this->page($principal, $slug, $pageId);
        $revision = $this->guard->revision($target, $number);
        $label    = $this->labels?->findForRevision($revision)?->getLabel();

        return $this->serializer->revisionHead($revision, $this->people->present($principal, $revision->getAuthor(), $target), $label)
            + $this->content->revision($principal, $target, $revision, $this->content->view($principal, $target));
    }

    /**
     * `GET …/revisions/{number}/diff?against={number}`: lines of the source, so only for whoever gets the source.
     *
     * @throws WikiApiRefusal
     *
     * @return array{from: int, to: int, lines: list<array{kind: string, line: string}>}
     */
    public function diff(WikiApiPrincipal $principal, string $slug, string $pageId, string $number, ?string $against): array
    {
        $target = $this->page($principal, $slug, $pageId);
        if (!WikiApiCapability::maySeeSource($principal->level)) {
            throw WikiApiRefusal::levelRefused();
        }
        if ($this->content->view($principal, $target) !== WikiApiContent::SOURCE) {
            throw WikiApiRefusal::permissionRefused();
        }
        if ($against === null) {
            throw WikiApiRefusal::invalid('against');
        }
        $to   = $this->guard->revision($target, $number);
        $from = $this->guard->revision($target, $against);

        return ['from' => $from->getRevisionNumber(), 'to' => $to->getRevisionNumber(), 'lines' => $this->diffs->diff($from, $to)];
    }

    /** @throws WikiApiRefusal */
    private function page(WikiApiPrincipal $principal, string $slug, string $pageId): WikiPage
    {
        $wiki = $this->guard->wiki($principal, $slug);
        $page = $this->guard->page($principal, $wiki, $pageId, true);
        $this->guard->require($principal, WikiAction::History, $page->getSpace(), $page);

        return $page;
    }
}
