<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Operation;

use Nowo\WikiBundle\Api\Hook\WikiApiDirectoryInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiPlanGateInterface;
use Nowo\WikiBundle\Api\Security\WikiApiGuard;
use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Nowo\WikiBundle\Api\Value\WikiApiWikiFilter;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiQuery;
use Nowo\WikiBundle\Api\WikiApiRefusal;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Markdown\WikiMarkdownLimitsInterface;
use Nowo\WikiBundle\Markdown\WikiMarkdownRendererInterface;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiPageVisibility;
use Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface;
use Nowo\WikiBundle\Service\WikiSearchService;

use function array_map;
use function array_slice;
use function array_values;
use function count;
use function mb_strlen;
use function min;
use function trim;

/**
 * 1.24 (spec 218 FR-017, owner Q-O6): search over the wikis in reach. Titles and plain text only, never the source
 * ({@see WikiSearchService::searchPlainText()}); each hit checked for the person (the page, its ancestors, its space);
 * at most `api.search_candidates` rows read per request, and `total` counts only readable hits among them.
 */
final readonly class WikiApiSearchOperations
{
    /** Rows read per database round. */
    private const BATCH = 200;

    public function __construct(
        private WikiApiGuard $guard,
        private WikiApiDirectoryInterface $directory,
        private WikiApiPlanGateInterface $planGate,
        private WikiAccessCheckerInterface $roles,
        private WikiSpaceAccessCheckerInterface $checker,
        private WikiSearchService $search,
        private WikiMarkdownRendererInterface $renderer,
        private int $candidates,
    ) {
    }

    /**
     * `GET /search?q=&wiki=&page=`.
     *
     * @param list<string> $slugs wikis to search; empty: every wiki in reach
     *
     * @throws WikiApiRefusal
     *
     * @return array{hits: list<array<string, mixed>>, total: int, truncated: bool, last_page: bool}
     */
    public function search(WikiApiPrincipal $principal, string $query, array $slugs, int $page): array
    {
        $query = trim($query);
        if ($query === '' || mb_strlen($query) > 200) {
            throw WikiApiRefusal::invalid('q');
        }
        if (!$this->roles->canList($principal->user)) {
            throw WikiApiRefusal::permissionRefused();
        }

        $wikis = [];
        foreach ($this->wikis($principal, $slugs) as $wiki) {
            if ($wiki->canOpen && $wiki->space instanceof WikiSpace) {
                $wikis[$wiki->space->getId()] = $wiki;
            }
        }
        $spaces = array_values(array_map(static fn (WikiApiWiki $wiki): WikiSpace => $wiki->space ?? throw WikiApiRefusal::notFound(), $wikis));

        $limits    = $this->renderer instanceof WikiMarkdownLimitsInterface ? $this->renderer : null;
        $readable  = [];
        $decisions = [];
        $read      = 0;
        $after     = null;
        $exhausted = $spaces === [];
        while (!$exhausted && $read < $this->candidates) {
            $batch = min(self::BATCH, $this->candidates - $read);
            $hits  = $this->search->searchPlainText($spaces, $query, $batch, $after, $limits, $after, $rows);
            $read += $rows;
            $exhausted = $rows < $batch;
            foreach ($hits as $hit) {
                if (WikiPageVisibility::isReadable($this->checker, $principal->user, $hit['space'], $hit['page'], $decisions)) {
                    $readable[] = $hit;
                }
            }
        }

        $shown = [];
        foreach (array_slice($readable, $page * WikiApiQuery::PAGE_SIZE, WikiApiQuery::PAGE_SIZE) as $hit) {
            $shown[] = [
                'page'    => ['id' => $hit['page']->getId(), 'wiki' => $wikis[$hit['space']->getId()]->slug, 'slug' => $hit['page']->getSlug(), 'title' => $hit['page']->getTitle()],
                'excerpt' => $hit['excerpt'],
            ];
        }

        return [
            'hits'      => $shown,
            'total'     => count($readable),
            'truncated' => !$exhausted,
            'last_page' => count($readable) <= ($page + 1) * WikiApiQuery::PAGE_SIZE,
        ];
    }

    /**
     * The wikis searched: the ones named (each must be in reach: 404 otherwise), or every wiki the directory lists that
     * the token sees and the plan allows.
     *
     * @param list<string> $slugs
     *
     * @throws WikiApiRefusal
     *
     * @return list<WikiApiWiki>
     */
    private function wikis(WikiApiPrincipal $principal, array $slugs): array
    {
        $wikis = [];
        if ($slugs !== []) {
            foreach ($slugs as $slug) {
                $wiki = $this->guard->wiki($principal, $slug);
                if ($wiki->space instanceof WikiSpace) {
                    $this->guard->require($principal, WikiAction::View, $wiki->space);
                }
                $wikis[] = $wiki;
            }

            return $wikis;
        }
        foreach ($this->directory->list($principal, new WikiApiWikiFilter()) as $wiki) {
            if ($this->guard->isSlug($wiki->slug) && $this->guard->sees($principal, $wiki) && $this->planGate->allowsWiki($principal->user, $wiki)) {
                $wikis[] = $wiki;
            }
        }

        return $wikis;
    }
}
