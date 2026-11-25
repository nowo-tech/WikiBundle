<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Search;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Markdown\WikiMarkdownLimitsInterface;
use Nowo\WikiBundle\Markdown\WikiMarkdownRendererInterface;
use Nowo\WikiBundle\Port\Internal\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Port\Internal\WikiSpaceAccessCheckerInterface;
use Nowo\WikiBundle\Port\Internal\WikiSpaceAccessResolverInterface;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Security\WikiPageVisibility;
use Nowo\WikiBundle\Service\WikiSearchService;
use Symfony\Component\Security\Core\User\UserInterface;

use function array_values;
use function in_array;
use function min;
use function trim;

/**
 * 1.44 (Beacon KIT-85, SearchKit CONTRACT-240): the wiki's own read rules as a query port, for the optional SearchKit
 * provider and any host that wraps it. Framework-free values in, wiki entities out; stateless (worker mode, `kernel.reset`
 * off: nothing is kept between calls).
 *
 * Deny by default, in this order, before anything is matched, counted, ranked or excerpted:
 *
 * 1. the role gate: {@see WikiAccessCheckerInterface::canList()} (the search page's `list` feature);
 * 2. the spaces: the viewer's spaces from the tenant / team resolver ({@see WikiSpaceAccessResolverInterface}), each one
 *    with {@see WikiAction::View} from the space checker — only those reach the SQL (`p.space IN (:spaces)`), so another
 *    tenant's or space's rows are never read;
 * 3. the rows: live pages only (`archivedAt IS NULL`: archived and trashed pages, a trashed page being archived), matched on
 *    the title and the plain text of the current revision, never the Markdown source
 *    ({@see WikiSearchService::searchPlainText()}, the REST API's search);
 * 4. the pages: {@see WikiAction::View} on the page **and every ancestor** ({@see WikiPageVisibility}, the page view's own
 *    rule), so a page below a hidden one is never a hit.
 *
 * The page ACL is the host's PHP checker, so it runs on a bounded candidate scan: the title matches first, then every
 * match, each at most `$candidates` rows read in id order; the hits and their number are the readable ones among them
 * only. A hidden row never shows as a title or an excerpt and is never counted. Residual (audit TI-L2, accepted): past the
 * budget, hidden matches in the viewer's own spaces use up scan slots, so whether a further readable body match shows and
 * whether the total is marked exact can depend on how many hidden pages match there — an existence signal of the same
 * kind as the scan's timing. Other tenants' and spaces' pages are never in the SQL.
 */
final readonly class WikiReadablePageFinder
{
    /** `search_kit_provider.scope: owner` — a scope id selects the spaces whose owner id it is (Beacon: a project). */
    public const SCOPE_OWNER = 'owner';

    /** `search_kit_provider.scope: slug` — a scope id selects the space with that slug. */
    public const SCOPE_SLUG = 'slug';

    /** Rows read per database round. */
    private const BATCH = 100;

    /**
     * @param WikiAccessCheckerInterface $roles the role gate (`list`)
     * @param WikiSpaceAccessResolverInterface $spaces the viewer's spaces (tenant / team resolver)
     * @param WikiSpaceAccessCheckerInterface $checker space- and page-aware decision
     * @param WikiSearchService $search the plain-text search
     * @param WikiPageRepositoryInterface $pages page lookup by slug (recents)
     * @param WikiMarkdownRendererInterface|null $renderer the page renderer: with limits ({@see WikiMarkdownLimitsInterface}), a page over them matches on its title only
     * @param int $candidates matching rows read at most per search (1 to {@see WikiSearchService::MAX_ROWS})
     * @param string $scopeMode {@see self::SCOPE_OWNER} or {@see self::SCOPE_SLUG}
     */
    public function __construct(
        private WikiAccessCheckerInterface $roles,
        private WikiSpaceAccessResolverInterface $spaces,
        private WikiSpaceAccessCheckerInterface $checker,
        private WikiSearchService $search,
        private WikiPageRepositoryInterface $pages,
        private ?WikiMarkdownRendererInterface $renderer = null,
        private int $candidates = 200,
        private string $scopeMode = self::SCOPE_OWNER,
    ) {
    }

    /**
     * The spaces the viewer may read, narrowed to a scope and to space slugs. An unknown and a forbidden scope or slug
     * give the same answer: no space.
     *
     * @param string|null $scope scope id (client input), or null for every readable space
     * @param list<string> $only keep only these space slugs (empty: no restriction)
     * @param list<string> $except leave these space slugs out
     *
     * @return list<WikiSpace>
     */
    public function spaces(UserInterface $viewer, ?string $scope = null, array $only = [], array $except = []): array
    {
        if (!$this->roles->canList($viewer)) {
            return [];
        }

        $readable = [];
        foreach ($this->spaces->listSpacesForUser($viewer) as $space) {
            if (($scope !== null && !$this->inScope($space, $scope))
                || ($only !== [] && !in_array($space->getSlug(), $only, true))
                || in_array($space->getSlug(), $except, true)
                || $space->isArchived() // 1.50: an archived space is never searched
                || !$this->checker->isGranted($viewer, WikiAction::View, $space)) {
                continue;
            }
            $readable[$space->getId()] = $space;
        }

        return array_values($readable);
    }

    /**
     * The readable pages of the spaces whose title or current text contains `$text` (literally, case-insensitive).
     *
     * @param list<WikiSpace> $spaces spaces from {@see self::spaces()} for the same viewer
     */
    public function find(UserInterface $viewer, array $spaces, string $text): WikiReadablePageHits
    {
        $text = trim($text);
        if ($text === '' || $spaces === []) {
            return new WikiReadablePageHits([], true);
        }

        $readable  = [];
        $decisions = [];
        $limits    = $this->renderer instanceof WikiMarkdownLimitsInterface ? $this->renderer : null;
        // 1.44 (audit TI-L2): title matches first, so body matches (or hidden pages) never crowd a title hit out of the
        // budget; then every match (title or text), skipping the pages already read. At most twice `candidates` rows.
        $this->scan($viewer, $readable, $decisions, fn (?string $after, int $batch, ?string &$last, ?int &$rows): array => $this->search->searchTitles($spaces, $text, $batch, $after, $last, $rows));
        $complete = $this->scan($viewer, $readable, $decisions, fn (?string $after, int $batch, ?string &$last, ?int &$rows): array => $this->search->searchPlainText($spaces, $text, $batch, $after, $limits, $last, $rows));

        return new WikiReadablePageHits($readable, $complete);
    }

    /**
     * Reads matching rows in batches (at most `candidates`), adding the readable pages not seen yet to `$readable`.
     *
     * @param list<array{page: WikiPage, space: WikiSpace, excerpt: string}> $readable
     * @param array<string, bool> $decisions page id => readable (shared memo; a page met twice is not added twice)
     * @param callable(?string, int, ?string, ?int): list<array{page: WikiPage, space: WikiSpace, excerpt: string}> $read
     *
     * @param-out list<array{page: WikiPage, space: WikiSpace, excerpt: string}> $readable
     *
     * @return bool whether every matching row was read
     */
    private function scan(UserInterface $viewer, array &$readable, array &$decisions, callable $read): bool
    {
        $seen = [];
        foreach ($readable as $hit) {
            $seen[$hit['page']->getId()] = true;
        }
        $done     = 0;
        $after    = null;
        $complete = false;
        while (!$complete && $done < $this->candidates) {
            $batch = min(self::BATCH, $this->candidates - $done);
            $last  = null;
            $rows  = 0;
            $hits  = $read($after, $batch, $last, $rows);
            $after = $last;
            $done += $rows;
            $complete = $rows < $batch;
            foreach ($hits as $hit) {
                if (!isset($seen[$hit['page']->getId()]) && WikiPageVisibility::isReadable($this->checker, $viewer, $hit['space'], $hit['page'], $decisions)) {
                    $seen[$hit['page']->getId()] = true;
                    $readable[]                  = $hit;
                }
            }
        }

        return $complete;
    }

    /**
     * The live page of a readable space the viewer may read now, or null — unknown, archived, trashed and forbidden
     * alike (recents: re-checked on every call, never cached).
     */
    public function page(UserInterface $viewer, string $spaceSlug, string $pageSlug): ?WikiPage
    {
        foreach ($this->spaces($viewer, null, [$spaceSlug]) as $space) {
            $page = $this->pages->findBySlug($space, $pageSlug);
            if ($page instanceof WikiPage
                && $page->getSlug() === $pageSlug
                && $page->getSpace()->getId() === $space->getId()
                && !$page->isArchived()
                && WikiPageVisibility::isReadable($this->checker, $viewer, $space, $page)) {
                return $page;
            }
        }

        return null;
    }

    private function inScope(WikiSpace $space, string $scope): bool
    {
        return $this->scopeMode === self::SCOPE_SLUG ? $space->getSlug() === $scope : $space->getOwnerScopeId() === $scope;
    }
}
