<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Search\SearchKit;

use Nowo\SearchKitBundle\Contract\FilterableSearchProviderInterface;
use Nowo\SearchKitBundle\Contract\IdentifierAwareSearchProviderInterface;
use Nowo\SearchKitBundle\Contract\PaginatedSearchProviderInterface;
use Nowo\SearchKitBundle\Contract\SearchReferenceResolverInterface;
use Nowo\SearchKitBundle\Model\SearchGroup;
use Nowo\SearchKitBundle\Model\SearchItem;
use Nowo\SearchKitBundle\Model\SearchQuery;
use Nowo\SearchKitBundle\Model\SearchReference;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Search\WikiReadablePageFinder;
use Nowo\WikiBundle\Search\WikiSearchViewerResolverInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_map;
use function array_slice;
use function array_unique;
use function array_unshift;
use function array_values;
use function count;
use function implode;
use function is_string;
use function max;
use function trim;
use function usort;

/**
 * 1.44 (Beacon KIT-85, SearchKit spec 240 / CONTRACT-240): wiki pages in SearchKit's "search everywhere" — the header
 * search, the palette, the results page and the recents. Registered only with `nowo_wiki.search_kit_provider` on and
 * SearchKitBundle >= 1.8 installed ({@see WikiSearchKitAvailability}); off by default.
 *
 * Every row comes from {@see WikiReadablePageFinder}: the wiki's own role gate, tenant / space resolver, space checker,
 * archived / trashed exclusion and page-plus-ancestors read rule, applied before matching, counting, ranking and
 * excerpting. Deny by default: no viewer for the identifier (no {@see WikiSearchViewerResolverInterface} answer), a
 * filter it does not apply, an unknown or forbidden scope or `space:` value — all answer null, the same as no match.
 *
 * Rows: the page title, the page view URL (the wiki's `routes.page_view`, through the router, so `tenant_paths` copies
 * apply), the space name, the in-wiki path of readable ancestors and — when the title does not match — the plain-text
 * excerpt of the wiki's own search (no markup: the browser marks the match through DOM text nodes). Totals are exact
 * (`exactTotal`) only when the candidate scan read every match; otherwise SearchKit caps them to the rows returned.
 *
 * Stateless (FrankenPHP worker mode with `kernel.reset` off): nothing is kept between searches.
 */
final readonly class WikiPageSearchKitProvider implements PaginatedSearchProviderInterface, IdentifierAwareSearchProviderInterface, FilterableSearchProviderInterface, SearchReferenceResolverInterface
{
    /** Group key and row kind. */
    public const GROUP = 'wiki';

    /** Typed filter `space:<slug>` (and `-space:<slug>`). */
    public const FILTER_SPACE = 'space';

    /** Translation domain of the labels. */
    private const DOMAIN = 'NowoWikiBundle';

    /** Score of a row SQL matched that the folding matcher does not (in ]0, 1]). */
    private const FALLBACK_SCORE = 0.1;

    /** A body match counts this much of a title match. */
    private const BODY_WEIGHT = 0.8;

    /**
     * @param WikiReadablePageFinder $finder the wiki's read rules
     * @param WikiSearchViewerResolverInterface|null $viewers the viewer of an identifier (null: no rows at all)
     * @param UrlGeneratorInterface $urls the router (tenant-aware with `tenant_paths`)
     * @param TranslatorInterface $translator group and "see all" labels
     * @param string $pageViewRoute `routes.page_view.name`
     * @param string|null $searchRoute `routes.search.name`, or null (no "see all" link)
     * @param bool $globalSearchPage `search.scope: all` (the search page answers across spaces)
     * @param string|null $icon UX Icons name of the rows, or null for SearchKit's icon of the `wiki` kind
     */
    public function __construct(
        private WikiReadablePageFinder $finder,
        private ?WikiSearchViewerResolverInterface $viewers,
        private UrlGeneratorInterface $urls,
        private TranslatorInterface $translator,
        private string $pageViewRoute,
        private ?string $searchRoute = null,
        private bool $globalSearchPage = true,
        private ?string $icon = null,
    ) {
    }

    public function groupKey(): string
    {
        return self::GROUP;
    }

    public function supportedFilters(): array
    {
        return [self::FILTER_SPACE];
    }

    public function search(SearchQuery $query): ?SearchGroup
    {
        $ranked = $this->ranked($query);
        if ($ranked === null) {
            return null;
        }
        [$hits, $complete]     = $ranked;
        [$moreUrl, $moreLabel] = $this->more($hits, $query);

        return new SearchGroup(
            self::GROUP,
            $this->label('wiki.search_kit.group', $query->locale),
            array_map(fn (array $hit): SearchItem => $this->item($hit['page'], $hit['excerpt'], $query, $hit['score']), array_slice($hits, 0, max(1, $query->limit))),
            count($hits),
            $moreUrl,
            $moreLabel,
            $complete,
        );
    }

    public function searchPage(SearchQuery $query, int $page, int $perPage): ?SearchGroup
    {
        $ranked  = $this->ranked($query);
        $perPage = max(1, $perPage);
        $rows    = $ranked === null ? [] : array_slice($ranked[0], (max(1, $page) - 1) * $perPage, $perPage);
        if ($rows === []) {
            return null;
        }

        return new SearchGroup(
            self::GROUP,
            $this->label('wiki.search_kit.group', $query->locale),
            array_map(fn (array $hit): SearchItem => $this->item($hit['page'], $hit['excerpt'], $query, $hit['score']), $rows),
        );
    }

    public function resolve(SearchReference $reference, string $userIdentifier, ?string $locale): ?SearchItem
    {
        $spaceSlug = $reference->routeParameters['spaceSlug'] ?? null;
        $pageSlug  = $reference->routeParameters['pageSlug'] ?? null;
        $viewer    = $this->viewer($userIdentifier);
        if ($reference->kind !== self::GROUP || !is_string($spaceSlug) || !is_string($pageSlug) || !$viewer instanceof UserInterface) {
            return null;
        }
        $page = $this->finder->page($viewer, $spaceSlug, $pageSlug);
        if (!$page instanceof WikiPage) {
            return null;
        }
        $item = $this->item($page, '', null, null);

        // The same URL as the reference only: another route with these parameters (edit, history …) is not this row.
        return $item->url === $reference->url ? $item : null;
    }

    /**
     * The readable hits of the query, best first, with their score, and whether the scan read every match; null when
     * the query cannot have rows for this viewer.
     *
     * @return array{0: list<array{page: WikiPage, space: WikiSpace, excerpt: string, score: float}>, 1: bool}|null
     */
    private function ranked(SearchQuery $query): ?array
    {
        $viewer = $this->viewer($query->userIdentifier);
        if (trim($query->text) === '' || !$viewer instanceof UserInterface) {
            return null;
        }
        $only   = [];
        $except = [];
        foreach ($query->filters as $filter) {
            // A filter this provider does not apply would widen the rows the viewer asked for: none (deny by default).
            if ($filter->key !== self::FILTER_SPACE) {
                return null;
            }
            if ($filter->negated) {
                $except[] = $filter->value;
            } else {
                $only[] = $filter->value;
            }
        }

        $found = $this->finder->find($viewer, $this->finder->spaces($viewer, $query->scope, $only, $except), $query->text);
        if ($found->hits === []) {
            return null;
        }
        $hits = [];
        foreach ($found->hits as $hit) {
            $title  = $query->score($hit['page']->getTitle());
            $body   = $hit['excerpt'] === '' ? null : $query->score($hit['excerpt']);
            $hits[] = $hit + ['score' => $title ?? ($body === null ? self::FALLBACK_SCORE : $body * self::BODY_WEIGHT)];
        }
        // Best score first, then the latest change, then the page id (a stable order for paging).
        usort($hits, static fn (array $a, array $b): int => [$b['score'], $b['page']->getUpdatedAt(), $a['page']->getId()] <=> [$a['score'], $a['page']->getUpdatedAt(), $b['page']->getId()]);

        return [$hits, $found->complete];
    }

    /**
     * The "see all" link and label: the wiki search page of the one space the hits live in, or the search page across
     * spaces (`search.scope: all`), only when there are more readable hits than rows.
     *
     * @param list<array{page: WikiPage, space: WikiSpace, excerpt: string, score: float}> $hits
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function more(array $hits, SearchQuery $query): array
    {
        if ($this->searchRoute === null || count($hits) <= max(1, $query->limit)) {
            return [null, null];
        }
        $spaces = array_values(array_unique(array_map(static fn (array $hit): string => $hit['space']->getSlug(), $hits)));
        if (count($spaces) !== 1 && !$this->globalSearchPage) {
            return [null, null];
        }
        $parameters = count($spaces) === 1 ? ['space' => $spaces[0], 'q' => $query->text] : ['q' => $query->text];

        return [$this->urls->generate($this->searchRoute, $parameters), $this->label('wiki.search_kit.see_all', $query->locale)];
    }

    private function item(WikiPage $page, string $excerpt, ?SearchQuery $query, ?float $score): SearchItem
    {
        $space = $page->getSpace();
        $parts = [$space->getName()];
        $path  = [];
        $seen  = [$page->getId() => true];
        // The ancestors were read with the page (WikiPageVisibility walked them): readable, no further query.
        for ($parent = $page->getParent(); $parent instanceof WikiPage && !isset($seen[$parent->getId()]); $parent = $parent->getParent()) {
            $seen[$parent->getId()] = true;
            array_unshift($path, $parent->getTitle());
        }
        if ($path !== []) {
            $parts[] = implode(' › ', $path);
        }
        if ($query instanceof SearchQuery && trim($excerpt) !== '' && !$query->matches($page->getTitle())) {
            $parts[] = $excerpt;
        }

        return new SearchItem(
            $page->getTitle(),
            $this->urls->generate($this->pageViewRoute, ['spaceSlug' => $space->getSlug(), 'pageSlug' => $page->getSlug()]),
            implode(' · ', $parts),
            $this->icon,
            null,
            null,
            $score,
            $page->getUpdatedAt(),
        );
    }

    /** The viewer of the identifier, only when its own identifier is exactly that one. */
    private function viewer(string $userIdentifier): ?UserInterface
    {
        $viewer = $userIdentifier === '' ? null : $this->viewers?->viewer($userIdentifier);

        return $viewer instanceof UserInterface && $viewer->getUserIdentifier() === $userIdentifier ? $viewer : null;
    }

    private function label(string $key, ?string $locale): string
    {
        return $this->translator->trans($key, [], self::DOMAIN, $locale);
    }
}
