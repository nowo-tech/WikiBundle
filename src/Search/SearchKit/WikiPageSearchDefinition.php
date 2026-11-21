<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Search\SearchKit;

use Doctrine\DBAL\Query\QueryBuilder as DbalQueryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder as OrmQueryBuilder;
use LogicException;
use Nowo\SearchKitBundle\Model\SearchItem;
use Nowo\SearchKitBundle\Model\SearchQuery;
use Nowo\SearchKitBundle\Model\SearchReference;
use Nowo\SearchKitBundle\Provider\Entity\EntitySearchDefinition;
use Nowo\SearchKitBundle\Search\Query\LikePattern;
use Nowo\SearchKitBundle\Search\Query\SeeAllLink;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Event\WikiSearchSpace;
use Nowo\WikiBundle\Event\WikiSearchSpacesEvent;
use Nowo\WikiBundle\LinkPicker\WikiLinkTenantResolverInterface;
use Nowo\WikiBundle\Markdown\WikiMarkdownLimitsInterface;
use Nowo\WikiBundle\Markdown\WikiMarkdownRendererInterface;
use Nowo\WikiBundle\Port\WikiSnapshots;
use Nowo\WikiBundle\Search\WikiReadablePageFinder;
use Nowo\WikiBundle\Search\WikiSearchTenantResolverInterface;
use Nowo\WikiBundle\Search\WikiSearchTenantsResolverInterface;
use Nowo\WikiBundle\Search\WikiSearchViewerResolverInterface;
use Nowo\WikiBundle\Security\WikiPageVisibility;
use Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface;
use Nowo\WikiBundle\Service\WikiSearchService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_keys;
use function array_map;
use function array_unique;
use function array_unshift;
use function array_values;
use function count;
use function hash;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function json_encode;
use function mb_strtolower;
use function preg_match;
use function sort;
use function str_starts_with;
use function strlen;
use function trim;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const SORT_STRING;

/**
 * 1.46: the wiki's pages in SearchKit's "search everywhere" (header search, palette, results page, recents) on
 * SearchKit's provider base (>= 1.10.1): this definition holds the wiki's rules, SearchKit's EntitySearchProvider runs the
 * one flow every bundle shares (group switch, scope shape, one bounded candidate query, the per-row check, the host's
 * item visibility event, the visible total, `null` on any refusal). {@see WikiPageSearchKitProvider} adds the `space:`
 * filter, searches by user identifier and the recents' slug lookup on top, delegating every search to that flow.
 *
 * The rows are the pages the viewer can open on the wiki's own pages, decided as the wiki's search page does:
 *
 * 1. the viewer: exactly SearchKit's user identifier ({@see WikiSearchViewerResolverInterface}; none: nothing);
 * 2. the spaces, before the SQL: the role gate (`list`), the tenant / team resolver, `View` on the space, the SearchKit
 *    scope and the `space:` filters ({@see WikiReadablePageFinder::spaces()}), the **current tenant** only (the space's
 *    {@see WikiLinkTenantResolverInterface} key equals {@see WikiSearchTenantResolverInterface::currentTenant()}; owner
 *    decision of 2026-11-19 for every quick-access list, `search_kit_provider.tenant: all` widens it), then the host's
 *    {@see WikiEvents::SEARCH_KIT_SPACES} (narrowing only) — only these space ids reach the SQL;
 * 3. the rows, in SQL: live pages only (archived and trashed pages are left out), the typed text matched literally
 *    (escaped LIKE, `ESCAPE '!'`, case-insensitive) on the title or the current revision's plain text, title matches
 *    first, at most `candidates` rows;
 * 4. per row, on one fresh read of the candidates and their ancestors: still live, still a match by the wiki search's
 *    own rule ({@see WikiSearchService::plainTextHit()}: never markup only, never the source of a page over the
 *    renderer's limits), `View` on its space and on the page **and every ancestor** ({@see WikiPageVisibility});
 * 5. then SearchKit's item visibility event (deny wins), the window, the visible total.
 *
 * Every hook resolves what it needs on each call and keeps nothing (FrankenPHP worker mode with kernel reset off).
 * Read-only: no write, flush, clear, transaction or reset of any entity manager, and no route of its own.
 */
final class WikiPageSearchDefinition extends EntitySearchDefinition
{
    /** Group key and row kind. */
    public const GROUP = 'wiki';

    /** Typed filter `space:<slug>` (and `-space:<slug>`), applied by {@see WikiPageSearchKitProvider}. */
    public const FILTER_SPACE = 'space';

    /** `search_kit_provider.tenant: current` (default): only the spaces of the current tenant. */
    public const TENANT_CURRENT = 'current';

    /** `search_kit_provider.tenant: all`: every space the viewer may read; the SearchKit tenant is {@see self::TENANT_ALL}. */
    public const TENANT_ALL_MODE = 'all';

    /** SearchKit tenant of a search across every tenant (`tenant: all`). */
    public const TENANT_ALL = '*';

    /** 1.47: prefix of the SearchKit tenant of a search over several current tenants ({@see self::tenantId()}). */
    public const TENANTS_PREFIX = 'tenants:';

    /** 1.47: most current tenant keys one search accepts; more find nothing (fail closed). */
    public const MAX_TENANTS = 500;

    /**
     * Route parameter the recents check of {@see WikiPageSearchKitProvider} adds to a reference: the page id it found for
     * the viewer from the reference's slugs. Never part of a wiki URL.
     */
    public const REFERENCE_ID = '_wiki_page_id';

    /** Most pages one recent's slugs may name for one viewer (spaces of several owners can share a slug). */
    public const MAX_REFERENCE_PAGES = 5;

    /** Translation domain of the label. */
    private const DOMAIN = 'NowoWikiBundle';

    /** Score of a row SQL matched that SearchKit's folding matcher does not (in ]0, 1]). */
    private const FALLBACK_SCORE = 0.1;

    /** A body match counts this much of a title match. */
    private const BODY_WEIGHT = 0.8;

    /** Page and space ids (UUID, lower case, as the wiki writes them). */
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/';

    /** Longest slug a reference may carry (the columns are 120 characters). */
    private const MAX_SLUG = 120;

    /**
     * @param WikiReadablePageFinder $finder the wiki's role gate, space resolver and space checker
     * @param WikiSearchViewerResolverInterface|null $viewers the viewer of an identifier (null: no rows at all)
     * @param WikiSpaceAccessCheckerInterface $checker space- and page-aware decision
     * @param WikiSearchService $search the wiki search's match and excerpt rule
     * @param EntityManagerInterface|WikiEntityManagerProvider $entityManager the wiki entity manager (or its provider)
     * @param UrlGeneratorInterface $urls the router
     * @param TranslatorInterface $translator the group label
     * @param string $pageViewRoute `routes.page_view.name`
     * @param list<string> $pageViewRoutes `routes.page_view.name` and its `tenant_paths` copies (recents)
     * @param string|null $searchRoute `routes.search.name`, or null (no "see all" link)
     * @param bool $globalSearchPage `search.scope: all` (the search page answers across spaces)
     * @param list<string> $excludedSearchSpaces `search.excluded_spaces` (no search page there)
     * @param string $scopeMode `search_kit_provider.scope` ({@see WikiReadablePageFinder::SCOPE_OWNER} or `SCOPE_SLUG`)
     * @param int $candidates `search_kit_provider.candidates`: rows read and checked per search
     * @param string|null $icon UX Icons name of the rows, or null for SearchKit's icon of the `wiki` kind
     * @param bool $sensitive `search_kit_provider.sensitive`: titles are sensitive (off while recents are not revalidated)
     * @param WikiMarkdownRendererInterface|null $renderer the page renderer: with limits, a page over them matches on its title only
     * @param EventDispatcherInterface|null $dispatcher dispatches {@see WikiEvents::SEARCH_KIT_SPACES} (null: none)
     * @param WikiLinkTenantResolverInterface|null $tenants the tenant key of each space (the current-tenant rule, that event; null: none)
     * @param string $tenantMode `search_kit_provider.tenant`: {@see self::TENANT_CURRENT} or {@see self::TENANT_ALL_MODE}
     * @param WikiSearchTenantResolverInterface|WikiSearchTenantsResolverInterface|null $currentTenants the current tenant(s) (null: none, so nothing with `current`)
     */
    public function __construct(
        private readonly WikiReadablePageFinder $finder,
        private readonly ?WikiSearchViewerResolverInterface $viewers,
        private readonly WikiSpaceAccessCheckerInterface $checker,
        private readonly WikiSearchService $search,
        private readonly EntityManagerInterface|WikiEntityManagerProvider $entityManager,
        private readonly UrlGeneratorInterface $urls,
        private readonly TranslatorInterface $translator,
        private readonly string $pageViewRoute,
        private readonly array $pageViewRoutes = [],
        private readonly ?string $searchRoute = null,
        private readonly bool $globalSearchPage = true,
        private readonly array $excludedSearchSpaces = [],
        private readonly string $scopeMode = WikiReadablePageFinder::SCOPE_OWNER,
        private readonly int $candidates = 200,
        private readonly ?string $icon = null,
        private readonly bool $sensitive = false,
        private readonly ?WikiMarkdownRendererInterface $renderer = null,
        private readonly ?EventDispatcherInterface $dispatcher = null,
        private readonly ?WikiLinkTenantResolverInterface $tenants = null,
        private readonly string $tenantMode = self::TENANT_CURRENT,
        private readonly WikiSearchTenantResolverInterface|WikiSearchTenantsResolverInterface|null $currentTenants = null,
    ) {
    }

    public function group(): string
    {
        return self::GROUP;
    }

    public function label(SearchQuery $query): string
    {
        return $this->translator->trans('wiki.search_kit.group', [], self::DOMAIN, $query->locale);
    }

    public function icon(): ?string
    {
        return $this->icon;
    }

    /**
     * The SearchKit tenant of the search (owner decision of 2026-11-19: the palette, like every quick-access list, shows
     * the current tenant only): {@see self::tenantId()} of the current tenant keys, or {@see self::TENANT_ALL} with
     * `tenant: all`. Null (no tenant context, no valid key, too many keys): no results.
     */
    public function tenant(SearchQuery $query): ?string
    {
        // SearchKit's queries always carry a non-empty identifier; whose spaces they are is decided per space.
        if ($this->tenantMode === self::TENANT_ALL_MODE) {
            return self::TENANT_ALL;
        }

        return self::tenantId($this->currentKeys($query->userIdentifier));
    }

    /**
     * 1.47: the stable SearchKit tenant of a list of current tenant keys (what SearchKit's item visibility event and the
     * recents carry): the key itself for one key (as in 1.46), {@see self::TENANTS_PREFIX} plus the SHA-256 of the JSON
     * array of the sorted keys for several (1.47.1: unambiguous, no two lists share an id), null for none. The same keys
     * always give the same value, whatever their order. Keys are never control characters and never start with the
     * prefix ({@see self::currentKeys()}), so a key can never equal the id of a list.
     *
     * @param list<string> $keys valid current tenant keys ({@see self::currentKeys()})
     */
    public static function tenantId(array $keys): ?string
    {
        if ($keys === []) {
            return null;
        }
        if (count($keys) === 1) {
            return $keys[0];
        }
        sort($keys, SORT_STRING);

        return self::TENANTS_PREFIX . hash('sha256', json_encode($keys, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * 1.47: the current tenant keys of the viewer, from either resolver port: strings only, never empty nor
     * {@see self::TENANT_ALL} (audit VN-1), without control characters nor the {@see self::TENANTS_PREFIX} prefix (audit VN2-1;
     * such a key is dropped), without duplicates, compared exactly; more than {@see self::MAX_TENANTS} distinct keys: none.
     *
     * @return list<string>
     */
    public function currentKeys(string $userIdentifier): array
    {
        $answer = match (true) {
            $this->currentTenants instanceof WikiSearchTenantsResolverInterface => $this->currentTenants->currentTenants($userIdentifier),
            $this->currentTenants instanceof WikiSearchTenantResolverInterface  => [$this->currentTenants->currentTenant($userIdentifier)],
            default                                                             => [],
        };
        $seen = [];
        foreach ($answer as $key) {
            if (!is_string($key) || $key === '' || $key === self::TENANT_ALL || str_starts_with($key, self::TENANTS_PREFIX) || preg_match('/\p{Cc}/u', $key) !== 0) {
                continue;
            }
            $seen[$key] = true;
            // Stop at the first key over the cap: a huge answer is never walked to the end (audit VN2-2).
            if (count($seen) > self::MAX_TENANTS) {
                return [];
            }
        }

        // Numeric-looking keys become int array keys: back to the strings they were.
        $keys = [];
        foreach (array_keys($seen) as $key) {
            $keys[] = (string) $key;
        }

        return $keys;
    }

    /** On with a viewer resolver; the role gate (`list`) is decided for the viewer before the SQL ({@see self::baseQuery()}). */
    public function featureEnabled(int|string $tenant, SearchQuery $query): bool
    {
        return $this->viewers instanceof WikiSearchViewerResolverInterface;
    }

    /** Declared for SearchKit; the wiki's own match ({@see self::applyFullText()}) is the one used. */
    public function searchableFields(): array
    {
        return ['p.title', 'r.contentText'];
    }

    public function baseQuery(int|string $tenant, SearchQuery $query): OrmQueryBuilder
    {
        $builder = $this->entityManager()->createQueryBuilder()
            ->select('p', 's', 'r.contentHtml AS contentHtml', 'r.contentText AS contentText', 'r.contentMarkdown AS contentMarkdown')
            ->from(WikiPage::class, 'p')
            ->innerJoin('p.space', 's')
            ->leftJoin('p.currentRevision', 'r')
            ->where('p.archivedAt IS NULL')
            ->andWhere('s.archivedAt IS NULL') // 1.50: nothing of an archived space
            ->orderBy('p.updatedAt', 'DESC')
            ->addOrderBy('p.id', 'ASC');
        $spaces = $this->spaceIds($query, (string) $tenant, $query->scope);
        if ($spaces === []) {
            // Nothing the viewer may read: the same answer as no match (no count, no hint).
            return $builder->andWhere('1 = 0');
        }

        return $builder->andWhere('s.id IN (:wiki_search_spaces)')->setParameter('wiki_search_spaces', $spaces);
    }

    /**
     * The scope narrowed the spaces already ({@see self::baseQuery()}); the same condition again in SQL. Every scope a
     * mode can read is handled; one that selects no readable space finds nothing.
     */
    public function applyScope(DbalQueryBuilder|OrmQueryBuilder $queryBuilder, string $scope, int|string $tenant, SearchQuery $query): bool
    {
        $queryBuilder->andWhere($this->scopeMode === WikiReadablePageFinder::SCOPE_SLUG ? 's.slug = :wiki_search_scope' : 's.ownerScopeId = :wiki_search_scope')
            ->setParameter('wiki_search_scope', $scope);

        return true;
    }

    /**
     * The wiki's own match, the one its search API and the 1.44 group use: the typed text **as one phrase**, literally
     * (escaped LIKE, `ESCAPE '!'`, case-insensitive), in the title or the current revision's plain text (a revision not
     * backfilled yet: its viewer-neutral HTML, re-checked per row so that markup alone never matches), title matches
     * first. The terms are not used: no operator, no word split, so existing hosts get the rows they got before.
     *
     * @param list<string> $terms
     */
    public function applyFullText(DbalQueryBuilder|OrmQueryBuilder $queryBuilder, array $terms, SearchQuery $query): bool
    {
        $match = "LOWER(p.title) LIKE :wiki_search_text ESCAPE '!'";
        $queryBuilder
            ->andWhere('(' . $match . " OR LOWER(r.contentText) LIKE :wiki_search_text ESCAPE '!' OR (r.contentText IS NULL AND LOWER(r.contentHtml) LIKE :wiki_search_text ESCAPE '!'))")
            ->setParameter('wiki_search_text', LikePattern::contains(mb_strtolower(trim($query->text))));
        if ($queryBuilder instanceof OrmQueryBuilder) {
            // Audit TI-L2: a title match is never crowded out of the candidate window by body matches.
            $queryBuilder->addSelect('CASE WHEN ' . $match . ' THEN 0 ELSE 1 END AS HIDDEN wiki_search_title_rank')
                ->orderBy('wiki_search_title_rank', 'ASC')
                ->addOrderBy('p.updatedAt', 'DESC')
                ->addOrderBy('p.id', 'ASC');
        }

        return true;
    }

    /**
     * The candidates the viewer may open: one fresh read of them, their spaces and their ancestors (one query per two
     * tree levels, whatever the number of rows), then the wiki's row rules (see the class description).
     *
     * @param list<array<string, mixed>|object> $rows
     *
     * @return list<array<string, mixed>|object>
     */
    public function filterVisible(array $rows, int|string $tenant, SearchQuery $query): array
    {
        $viewer = $this->viewer($query->userIdentifier);
        if (!$viewer instanceof UserInterface) {
            return [];
        }
        $pages = [];
        foreach ($rows as $row) {
            $page = $this->pageOf($row);
            if ($page instanceof WikiPage) {
                $pages[$page->getId()] = $page;
            }
        }
        $this->refresh(array_keys($pages));

        $text      = trim($query->text);
        $limits    = $this->renderer instanceof WikiMarkdownLimitsInterface ? $this->renderer : null;
        $spaces    = [];
        $decisions = [];
        $visible   = [];
        foreach ($rows as $row) {
            $page = $this->pageOf($row);
            if (!$page instanceof WikiPage || !is_array($row) || $page->isArchived()) {
                continue;
            }
            if ($text !== '' && $this->hit($page, $row, $text, $limits) === null) {
                continue;
            }
            $space = $page->getSpace();
            $spaces[$space->getId()] ??= $this->checker->isGranted($viewer, WikiAction::View, $space);
            if ($spaces[$space->getId()] && WikiPageVisibility::isReadable($this->checker, $viewer, $space, $page, $decisions)) {
                $visible[] = $row;
            }
        }

        return $visible;
    }

    /**
     * The page id of a row.
     *
     * @param array<string, mixed>|object $row
     *
     * @throws LogicException when the row is not a page row of {@see self::baseQuery()}
     */
    public function subjectId(array|object $row): string
    {
        $page = $this->pageOf($row);
        if (!$page instanceof WikiPage) {
            throw new LogicException('A wiki search row holds its page at index 0.');
        }

        return $page->getId();
    }

    /**
     * The row: the page title; the page view URL (the wiki's `routes.page_view` through the router); the space name,
     * the readable ancestors (checked by {@see self::filterVisible()}, already loaded) and — when the title does not
     * match — the plain-text excerpt of the wiki's search; `score` (title before body) and `date` (last change).
     *
     * @param array<string, mixed>|object $row
     */
    public function toItem(array|object $row, SearchQuery $query): SearchItem
    {
        $page = $this->pageOf($row);
        if (!$page instanceof WikiPage || !is_array($row)) {
            throw new LogicException('A wiki search row holds its page at index 0.');
        }
        $space = $page->getSpace();
        $parts = [$space->getName()];
        $path  = [];
        $seen  = [$page->getId() => true];
        for ($parent = $page->getParent(); $parent instanceof WikiPage && !isset($seen[$parent->getId()]); $parent = $parent->getParent()) {
            $seen[$parent->getId()] = true;
            array_unshift($path, $parent->getTitle());
        }
        if ($path !== []) {
            $parts[] = implode(' › ', $path);
        }
        $score = null;
        $text  = trim($query->text);
        if ($text !== '') {
            $excerpt = $this->hit($page, $row, $text, $this->renderer instanceof WikiMarkdownLimitsInterface ? $this->renderer : null)['excerpt'] ?? '';
            $title   = $query->score($page->getTitle());
            $body    = $excerpt === '' ? null : $query->score($excerpt);
            $score   = $title ?? ($body === null ? self::FALLBACK_SCORE : $body * self::BODY_WEIGHT);
            if (trim($excerpt) !== '' && !$query->matches($page->getTitle())) {
                $parts[] = $excerpt;
            }
        }

        return new SearchItem(
            $page->getTitle(),
            $this->urls->generate($this->pageViewRoute, ['spaceSlug' => $space->getSlug(), 'pageSlug' => $page->getSlug()]),
            implode(' · ', $parts),
            null,
            null,
            null,
            $score,
            $page->getUpdatedAt(),
        );
    }

    /**
     * The wiki's search page with the query, only where that page answers the same question: the one space a `space:`
     * filter or a `scope: slug` scope names (unless it has no search page), or — with `search.scope: all` and no scope or
     * filter — the page across spaces. Anything the page cannot express (an owner scope, a negated or a second space): none.
     */
    public function seeAllUrl(SearchQuery $query): ?string
    {
        if ($this->searchRoute === null) {
            return null;
        }
        $named = [];
        foreach ($query->filters as $filter) {
            if ($filter->key !== self::FILTER_SPACE || $filter->negated) {
                return null;
            }
            $named[] = $filter->value;
        }
        if ($query->scope !== null) {
            if ($this->scopeMode !== WikiReadablePageFinder::SCOPE_SLUG) {
                return null;
            }
            $named[] = $query->scope;
        }
        $named = array_values(array_unique($named));
        if (count($named) > 1 || ($named === [] && !$this->globalSearchPage) || ($named !== [] && in_array($named[0], $this->excludedSearchSpaces, true))) {
            return null;
        }

        return SeeAllLink::build($this->urls, $this->searchRoute, $named === [] ? [] : ['space' => $named[0]], $query);
    }

    public function maxCandidates(): int
    {
        return $this->candidates;
    }

    public function sensitive(): bool
    {
        return $this->sensitive;
    }

    public function idField(): string
    {
        return 'p.id';
    }

    /**
     * The page id {@see WikiPageSearchKitProvider} found for the viewer from a recent's slugs ({@see self::REFERENCE_ID}),
     * on a page view route only, in the wiki's id shape; anything else is null.
     */
    public function referenceId(SearchReference $reference): ?string
    {
        $id = $reference->routeParameters[self::REFERENCE_ID] ?? null;

        return $this->isPageViewReference($reference) && is_string($id) && preg_match(self::UUID, $id) === 1 ? $id : null;
    }

    /**
     * The ids of the live pages a recent names for this viewer (its `spaceSlug` and `pageSlug`, in the spaces the viewer
     * may read now, after the host's space rules), at most {@see self::MAX_REFERENCE_PAGES}; empty for anything else.
     * The rows are then read again through every check of a search ({@see WikiPageSearchKitProvider::resolve()}).
     *
     * @return list<string>
     */
    public function referencedPageIds(SearchReference $reference, string $userIdentifier, ?string $locale = null): array
    {
        $spaceSlug = $reference->routeParameters['spaceSlug'] ?? null;
        $pageSlug  = $reference->routeParameters['pageSlug'] ?? null;
        if ($userIdentifier === '' || $reference->kind !== self::GROUP || !$this->isPageViewReference($reference) || !$this->isSlug($spaceSlug) || !$this->isSlug($pageSlug)) {
            return [];
        }
        $query  = new SearchQuery('', null, 1, null, false, $userIdentifier, [], $locale);
        $tenant = $this->tenant($query);
        $spaces = $tenant === null ? [] : $this->spaceIds($query, $tenant, null, [$spaceSlug]);
        if ($spaces === []) {
            return [];
        }

        /** @var list<array{id: string}> $rows */
        $rows = $this->entityManager()->createQueryBuilder()
            ->select('p.id AS id')
            ->from(WikiPage::class, 'p')
            ->innerJoin('p.space', 's')
            ->where('s.id IN (:wiki_search_spaces)')
            ->andWhere('p.slug = :wiki_search_page')
            ->andWhere('p.archivedAt IS NULL')
            ->andWhere('s.archivedAt IS NULL') // 1.50: nothing of an archived space
            ->setParameter('wiki_search_spaces', $spaces)
            ->setParameter('wiki_search_page', $pageSlug)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(self::MAX_REFERENCE_PAGES)
            ->getQuery()
            ->getScalarResult();

        return array_values(array_map(static fn (array $row): string => (string) $row['id'], $rows));
    }

    /**
     * The ids of the spaces a search of this viewer reads: the wiki's own space rules, narrowed by the scope and the
     * `space:` filters (any other filter key: none), then by the host's {@see WikiEvents::SEARCH_KIT_SPACES} listeners.
     *
     * @param list<string> $only keep only these space slugs (recents)
     *
     * @return list<string>
     */
    private function spaceIds(SearchQuery $query, string $tenant, ?string $scope, array $only = []): array
    {
        $viewer = $this->viewer($query->userIdentifier);
        if (!$viewer instanceof UserInterface) {
            return [];
        }
        $except = [];
        foreach ($query->filters as $filter) {
            // A filter the wiki does not apply would widen the rows the viewer asked for: none (deny by default).
            if ($filter->key !== self::FILTER_SPACE) {
                return [];
            }
            if ($filter->negated) {
                $except[] = $filter->value;
            } else {
                $only[] = $filter->value;
            }
        }
        $all  = $this->tenantMode === self::TENANT_ALL_MODE;
        $keys = $all ? [] : $this->currentKeys($query->userIdentifier);
        // The keys again for this call: a SearchKit tenant that is not theirs (changed meanwhile, forged) finds nothing.
        if (!$all && ($keys === [] || self::tenantId($keys) !== $tenant)) {
            return [];
        }
        $candidates = [];
        foreach ($this->finder->spaces($viewer, $scope, $only, $except) as $space) {
            $snapshot = WikiSnapshots::space($space);
            $key      = $this->tenants?->tenantOf($snapshot);
            // The current tenants only (default): a space of another tenant, or of none, is not searched; keys compared
            // exactly. Audit VN-1: every tenant only by configuration (`tenant: all`), never because of a key's value.
            if ($all || ($key !== null && in_array($key, $keys, true))) {
                $candidates[] = [$space, new WikiSearchSpace($snapshot, $key)];
            }
        }
        $spaces = array_map(static fn (array $candidate): WikiSpace => $candidate[0], $candidates);
        if ($spaces === [] || !$this->dispatcher instanceof EventDispatcherInterface) {
            return array_map(static fn (WikiSpace $space): string => $space->getId(), $spaces);
        }

        $event = new WikiSearchSpacesEvent($query->userIdentifier, $scope, $query->locale, array_map(static fn (array $candidate): WikiSearchSpace => $candidate[1], $candidates), $keys);
        $this->dispatcher->dispatch($event, WikiEvents::SEARCH_KIT_SPACES);
        $kept = [];
        foreach ($spaces as $space) {
            if (!$event->isExcluded($space->getId())) {
                $kept[] = $space->getId();
            }
        }

        return $kept;
    }

    /**
     * Reads the candidates again (their spaces and parents with them), then their further ancestors, two tree levels per
     * query, bypassing the identity map's copies (worker mode: another request may have renamed, moved or archived them).
     * Each page is read once per call, so a parent cycle ends the walk.
     *
     * @param list<string> $ids page ids
     */
    private function refresh(array $ids): void
    {
        $read = [];
        while ($ids !== []) {
            foreach ($ids as $id) {
                $read[$id] = true;
            }

            /** @var list<WikiPage> $pages */
            $pages = $this->entityManager()->createQueryBuilder()
                ->select('p', 's', 'pp')
                ->from(WikiPage::class, 'p')
                ->innerJoin('p.space', 's')
                ->leftJoin('p.parent', 'pp')
                ->where('p.id IN (:wiki_search_pages)')
                ->setParameter('wiki_search_pages', $ids)
                ->getQuery()
                ->setHint(Query::HINT_REFRESH, true)
                ->getResult();
            $next = [];
            foreach ($pages as $page) {
                $parent = $page->getParent();
                if ($parent instanceof WikiPage) {
                    $read[$parent->getId()] = true;
                    $grand                  = $parent->getParent();
                    if ($grand instanceof WikiPage && !isset($read[$grand->getId()])) {
                        $next[$grand->getId()] = true;
                    }
                }
            }
            $ids = array_keys($next);
        }
    }

    /**
     * The wiki search's decision for a row of {@see self::baseQuery()}.
     *
     * @param array<mixed> $row
     *
     * @return array{page: WikiPage, space: WikiSpace, excerpt: string}|null
     */
    private function hit(WikiPage $page, array $row, string $text, ?WikiMarkdownLimitsInterface $limits): ?array
    {
        return $this->search->plainTextHit(
            $page,
            is_string($row['contentHtml'] ?? null) ? $row['contentHtml'] : null,
            is_string($row['contentText'] ?? null) ? $row['contentText'] : null,
            is_string($row['contentMarkdown'] ?? null) ? $row['contentMarkdown'] : null,
            $text,
            $limits,
        );
    }

    /**
     * @param array<mixed>|object $row
     */
    private function pageOf(array|object $row): ?WikiPage
    {
        $page = is_array($row) ? ($row[0] ?? null) : null;

        return $page instanceof WikiPage ? $page : null;
    }

    private function isPageViewReference(SearchReference $reference): bool
    {
        return $reference->route === $this->pageViewRoute || in_array($reference->route, $this->pageViewRoutes, true);
    }

    /**
     * A slug-sized string without control characters (the route requirements decided its shape already).
     *
     * @phpstan-assert-if-true string $value
     */
    private function isSlug(mixed $value): bool
    {
        return is_string($value) && $value !== '' && strlen($value) <= self::MAX_SLUG && preg_match('/^[^\p{Cc}\s]+\z/u', $value) === 1;
    }

    /** The viewer of the identifier, only when its own identifier is exactly that one. */
    private function viewer(string $userIdentifier): ?UserInterface
    {
        $viewer = $userIdentifier === '' ? null : $this->viewers?->viewer($userIdentifier);

        return $viewer instanceof UserInterface && $viewer->getUserIdentifier() === $userIdentifier ? $viewer : null;
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->entityManager instanceof WikiEntityManagerProvider ? $this->entityManager->get() : $this->entityManager;
    }
}
