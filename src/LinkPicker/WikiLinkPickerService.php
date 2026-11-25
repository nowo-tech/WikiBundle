<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\LinkPicker;

use Nowo\WikiBundle\Controller\WikiLinkPickerController;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Markdown\WikiHeadingOutline;
use Nowo\WikiBundle\Markdown\WikiMarkdownRendererInterface;
use Nowo\WikiBundle\Markdown\WikiRenderContext;
use Nowo\WikiBundle\Markdown\WikiViewerMarkdownRendererInterface;
use Nowo\WikiBundle\Port\Internal\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Port\Internal\WikiSpaceAccessResolverInterface;
use Nowo\WikiBundle\Port\WikiSnapshots;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageTitleFinderInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizerInterface;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use function array_slice;
use function count;
use function in_array;
use function mb_stripos;
use function mb_strlen;
use function mb_strtolower;
use function mb_substr;
use function preg_match;
use function preg_replace;
use function str_starts_with;
use function trim;
use function usort;

/**
 * 1.40: the data of the Milkdown link picker (contract "section anchors and link picker" §3.1 and §5), behind
 * {@see WikiLinkPickerController}:
 *
 * - **Candidates** `{here: [{id, label, url}], others: [{wiki, pages: [...]}]}`: pages of the current wiki first (an empty
 *   query gives the most recently changed ones, at most {@see self::MAX_HERE}); with at least {@see self::MIN_CROSS_QUERY}
 *   characters, `search.scope: all` and `cross_wiki`, pages of the other wikis of the **same tenant**
 *   ({@see WikiLinkTenantResolverInterface}, owner decision O4): at most
 *   {@see self::MAX_WIKIS} wikis, {@see self::MAX_PER_WIKI} pages each, {@see self::MAX_OTHERS} in all. A wiki is named
 *   only when it has a readable hit.
 * - **Sections** `[{id, level, text}]` of one page, taken from the viewer's own render of it (sanitized), so a heading the
 *   render hides never shows; null (404) when the page is missing, archived, not readable or out of reach.
 *
 * A wiki in `search.excluded_spaces` (which needs `search.scope: space`) gets the empty answer and no sections: it has
 * no search page, so its editor has no title search either. Gates on every page: the list gate, View on its space, then View on the page and on every ancestor
 * ({@see WikiSpaceGuard::isPageReadable()}). Titles only are matched, never the content. "No match" and "not allowed"
 * give the same empty answer. The query is cut to {@see self::MAX_QUERY} characters and stripped of control, format and
 * bidi characters. Stateless (FrankenPHP worker mode): every decision is made per call.
 */
final readonly class WikiLinkPickerService
{
    /** Most pages of the current wiki. */
    public const MAX_HERE = 10;

    /** Most other wikis. */
    public const MAX_WIKIS = 5;

    /** Most pages per other wiki. */
    public const MAX_PER_WIKI = 3;

    /** Most pages of other wikis in all. */
    public const MAX_OTHERS = 10;

    /** Longest query, in characters. */
    public const MAX_QUERY = 100;

    /** Shortest query that looks into other wikis. */
    public const MIN_CROSS_QUERY = 2;

    /** Rows read for the current wiki before the permission filter. */
    public const HERE_ROWS = 50;

    /** Rows read for the other wikis before the permission filter. */
    public const OTHER_ROWS = 100;

    /** The empty answer: no match, or not allowed (the same). */
    public const EMPTY = ['here' => [], 'others' => []];

    /**
     * @param WikiPageRepositoryInterface $pages page lookups (titles in one query with {@see WikiPageTitleFinderInterface})
     * @param WikiSpaceGuard $guard space and page decisions
     * @param WikiAccessCheckerInterface $accessChecker the general list gate
     * @param WikiSpaceAccessResolverInterface $spaces the spaces of a user
     * @param UrlGeneratorInterface $urlGenerator page URLs (tenant-aware router)
     * @param array<string, array{path: string, name: string}> $routes the nowo_wiki routes
     * @param WikiLinkPickerSettingsInterface $settings runtime settings (`cross_wiki`)
     * @param WikiLinkTenantResolverInterface $tenants the tenant of a wiki
     * @param WikiMarkdownRendererInterface $renderer the page renderer (sections: the viewer render)
     * @param WikiHtmlSanitizerInterface $sanitizer sanitizes the viewer render
     * @param WikiRenderContext $renderContext the render frame of the sections render
     * @param string $searchScope `search.scope` (`space`: the current wiki only)
     * @param list<string> $excludedSpaces `search.excluded_spaces`: wikis without the picker (no title search)
     * @param int $searchCandidates pages a host repository without {@see WikiPageTitleFinderInterface} reads per wiki
     *                              before the title filter (`api.search_candidates`, audit RY-L5)
     */
    public function __construct(
        private WikiPageRepositoryInterface $pages,
        private WikiSpaceGuard $guard,
        private WikiAccessCheckerInterface $accessChecker,
        private WikiSpaceAccessResolverInterface $spaces,
        private UrlGeneratorInterface $urlGenerator,
        private array $routes,
        private WikiLinkPickerSettingsInterface $settings,
        private WikiLinkTenantResolverInterface $tenants,
        private WikiMarkdownRendererInterface $renderer,
        private WikiHtmlSanitizerInterface $sanitizer,
        private WikiRenderContext $renderContext,
        private string $searchScope = 'all',
        private array $excludedSpaces = [],
        private int $searchCandidates = 500,
    ) {
    }

    /**
     * The query as the picker uses it: control, format and bidi characters removed, trimmed, at most
     * {@see self::MAX_QUERY} characters. Invalid UTF-8 gives `''`.
     *
     * @param string $query the raw `q` parameter
     */
    public static function normaliseQuery(string $query): string
    {
        if (preg_match('//u', $query) !== 1) {
            return '';
        }

        return trim(mb_substr(trim((string) preg_replace('/[\p{Cc}\p{Cf}]+/u', ' ', $query)), 0, self::MAX_QUERY, 'UTF-8'));
    }

    /**
     * The link candidates of a query typed while editing a page of `$space`.
     *
     * @param UserInterface $user the editor
     * @param WikiSpace|null $space the current wiki (null: missing or not readable, the empty answer)
     * @param string $query the raw query
     *
     * @return array{here: list<array{id: string, label: string, url: string}>, others: list<array{wiki: string, pages: list<array{id: string, label: string, url: string}>}>}
     */
    public function candidates(UserInterface $user, ?WikiSpace $space, string $query): array
    {
        if (!$this->isOpen($user, $space)) {
            return self::EMPTY;
        }

        $query     = self::normaliseQuery($query);
        $decisions = [];
        $here      = [];
        foreach ($this->readable($user, $this->find([$space], $query, self::HERE_ROWS), $decisions) as $page) {
            $here[] = $this->row($page);
            if (count($here) === self::MAX_HERE) {
                break;
            }
        }

        return ['here' => $here, 'others' => mb_strlen($query, 'UTF-8') >= self::MIN_CROSS_QUERY ? $this->others($user, $space, $query, $decisions) : []];
    }

    /**
     * The sections of a page, from the viewer's render of it.
     *
     * @param UserInterface $user the editor
     * @param WikiSpace|null $space the current wiki (null: missing or not readable)
     * @param string $pageId the page id
     *
     * @return list<array{id: string, level: int, text: string}>|null null when the page is missing, archived, not
     *                                                                readable or out of the picker's reach (404)
     */
    public function sections(UserInterface $user, ?WikiSpace $space, string $pageId): ?array
    {
        if (!$this->isOpen($user, $space)) {
            return null;
        }
        $page = $this->pages->findById($pageId);
        if (!$page instanceof WikiPage || $page->getId() !== $pageId || $page->isArchived()) {
            return null;
        }
        $pageSpace = $page->getSpace();
        $inReach   = $pageSpace->getId() === $space->getId() || $this->reachesOtherWiki($user, $space, $pageSpace);
        if (!$inReach || !$this->guard->isPageReadable($user, $pageSpace, $page)) {
            return null;
        }

        $revision = $page->getCurrentRevision();
        $markdown = $revision?->getContentMarkdown();
        if ($markdown === null || !$this->renderer instanceof WikiViewerMarkdownRendererInterface) {
            // Audit RY-I2: the stored (viewer-neutral) HTML: top-level headings only, as the section labels.
            return WikiHeadingOutline::fromHtml($revision?->getContentHtml() ?? '', true);
        }
        $renderer = $this->renderer;
        $html     = $this->sanitizer->sanitize($this->renderContext->within($pageSpace, $page, static fn (): string => $renderer->renderForViewer($markdown)));

        return WikiHeadingOutline::fromHtml($html);
    }

    /**
     * Whether the picker answers for the current wiki: the list gate, View on the space, and a space outside
     * `search.excluded_spaces` (a wiki without search has no title search in its editor either).
     *
     * @phpstan-assert-if-true WikiSpace $space
     */
    private function isOpen(UserInterface $user, ?WikiSpace $space): bool
    {
        return $space instanceof WikiSpace
            && !in_array($space->getSlug(), $this->excludedSpaces, true)
            && $this->accessChecker->canList($user)
            && $this->guard->isGranted($user, WikiAction::View, $space);
    }

    /**
     * @param array<string, bool> $decisions
     *
     * @return list<array{wiki: string, pages: list<array{id: string, label: string, url: string}>}>
     */
    private function others(UserInterface $user, WikiSpace $space, string $query, array &$decisions): array
    {
        $spaces = $this->otherWikis($user, $space);
        if ($spaces === []) {
            return [];
        }

        /** @var array<string, array{wiki: string, pages: list<array{id: string, label: string, url: string}>}> $groups */
        $groups = [];
        $total  = 0;
        foreach ($this->readable($user, $this->find($spaces, $query, self::OTHER_ROWS), $decisions) as $page) {
            $id = $page->getSpace()->getId();
            if (!isset($groups[$id])) {
                if (count($groups) === self::MAX_WIKIS) {
                    continue;
                }
                $groups[$id] = ['wiki' => $this->withoutFormatCharacters($page->getSpace()->getName()), 'pages' => []];
            }
            if (count($groups[$id]['pages']) === self::MAX_PER_WIKI) {
                continue;
            }
            $groups[$id]['pages'][] = $this->row($page);
            if (++$total === self::MAX_OTHERS) {
                break;
            }
        }

        $others = [];
        foreach ($groups as $group) {
            $others[] = $group;
        }

        return $others;
    }

    /**
     * The other wikis the picker may look into: the user's readable spaces of the same tenant (none with
     * `search.scope: space` or `cross_wiki` off).
     *
     * @return list<WikiSpace>
     */
    private function otherWikis(UserInterface $user, WikiSpace $space): array
    {
        if (!$this->crossWiki()) {
            return [];
        }
        $tenant = $this->tenants->tenantOf(WikiSnapshots::space($space));
        if ($tenant === null) {
            return [];
        }

        $spaces = [];
        foreach ($this->spaces->listSpacesForUser($user) as $candidate) {
            if ($candidate->getId() !== $space->getId() && $this->sameTenantAndOpen($user, $tenant, $candidate)) {
                $spaces[] = $candidate;
            }
        }

        return $spaces;
    }

    private function reachesOtherWiki(UserInterface $user, WikiSpace $space, WikiSpace $other): bool
    {
        $tenant = $this->crossWiki() ? $this->tenants->tenantOf(WikiSnapshots::space($space)) : null;

        return $tenant !== null && $this->sameTenantAndOpen($user, $tenant, $other);
    }

    private function sameTenantAndOpen(UserInterface $user, string $tenant, WikiSpace $space): bool
    {
        return $this->tenants->tenantOf(WikiSnapshots::space($space)) === $tenant
            && $this->guard->isGranted($user, WikiAction::View, $space);
    }

    private function crossWiki(): bool
    {
        return $this->searchScope !== 'space' && $this->settings->crossWiki();
    }

    /**
     * Non-archived pages of the spaces whose title contains the query (title-only).
     *
     * @param list<WikiSpace> $spaces
     *
     * @return list<WikiPage>
     */
    private function find(array $spaces, string $query, int $rows): array
    {
        if ($this->pages instanceof WikiPageTitleFinderInterface) {
            $ids = [];
            foreach ($spaces as $space) {
                $ids[] = $space->getId();
            }

            return $this->pages->findByTitle($ids, $query, $rows);
        }

        // A host page repository without the finder: up to `search_candidates` pages of each wiki in tree order (audit
        // RY-L5), filtered on their title in PHP. A wiki with more pages may miss matches past that bound.
        $found  = [];
        $needle = mb_strtolower($query, 'UTF-8');
        foreach ($spaces as $space) {
            foreach ($this->pages->findActiveBySpace($space, $this->searchCandidates) as $page) {
                if ($needle === '' || mb_stripos($page->getTitle(), $needle, 0, 'UTF-8') !== false) {
                    $found[] = $page;
                }
            }
        }
        if ($needle !== '') {
            usort($found, static fn (WikiPage $a, WikiPage $b): int => [str_starts_with(mb_strtolower($a->getTitle(), 'UTF-8'), $needle) ? 0 : 1, $a->getTitle()] <=> [str_starts_with(mb_strtolower($b->getTitle(), 'UTF-8'), $needle) ? 0 : 1, $b->getTitle()]);
        }

        return array_slice($found, 0, $rows);
    }

    /**
     * @param list<WikiPage> $pages
     * @param array<string, bool> $decisions
     *
     * @return list<WikiPage>
     */
    private function readable(UserInterface $user, array $pages, array &$decisions): array
    {
        $kept = [];
        foreach ($pages as $page) {
            if (!$page->isArchived() && $this->guard->isPageReadable($user, $page->getSpace(), $page, $decisions)) {
                $kept[] = $page;
            }
        }

        return $kept;
    }

    /**
     * @return array{id: string, label: string, url: string}
     */
    private function row(WikiPage $page): array
    {
        return ['id' => $page->getId(), 'label' => $this->withoutFormatCharacters($page->getTitle()), 'url' => $this->url($page)];
    }

    /**
     * Audit RY-I1: a title or wiki name without format characters (bidi overrides, zero-width), which could make a label
     * look like another one.
     */
    private function withoutFormatCharacters(string $text): string
    {
        return (string) preg_replace('/\p{Cf}+/u', '', $text);
    }

    private function url(WikiPage $page): string
    {
        $route = $this->routes['page_view']['name'] ?? null;
        if ($route === null) {
            return '';
        }

        try {
            return $this->urlGenerator->generate($route, ['spaceSlug' => $page->getSpace()->getSlug(), 'pageSlug' => $page->getSlug()]);
        } catch (RoutingException) {
            return '';
        }
    }
}
