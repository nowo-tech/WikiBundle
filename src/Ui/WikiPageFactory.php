<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Ui;

use DateTimeInterface;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\BackLink;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\Confirm;
use Nowo\UiKitBundle\View\Crumb;
use Nowo\UiKitBundle\View\DetailItem;
use Nowo\UiKitBundle\View\EmptyState;
use Nowo\UiKitBundle\View\FormPage;
use Nowo\UiKitBundle\View\Label;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\Pagination;
use Nowo\UiKitBundle\View\ShowPage;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Link\WikiLinkedObject;
use Nowo\WikiBundle\Service\WikiPageTreeBuilder;
use Symfony\Component\Form\FormView;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function count;
use function is_array;

/**
 * Builds the UiKit view models of the wiki pages (REQ-UI-003): page headers (title, intro, actions, breadcrumbs, back
 * link), columns, value-free rows, row actions (POST + CSRF FormViews behind a Confirm for restore / archive), the
 * pagination and the empty states, plus the value-free data of the wiki-specific regions (page tree, archived pages,
 * "Used in", search hits). Templates never receive an entity through a view model: rows and tree nodes are plain
 * arrays of strings, booleans and URLs. The controller and the page contract test share it, so the test renders exactly
 * the view models the application gets. Stateless: safe in worker mode.
 *
 * @phpstan-type TreeNode array{title: string, href: string, current: bool, children: list<mixed>}
 * @phpstan-type SourceNode array{page: WikiPage, children: list<mixed>}
 * @phpstan-type Hit array{page: WikiPage, space: WikiSpace, excerpt: string}
 */
final readonly class WikiPageFactory
{
    /** Id of the AI question dialog of the index (opened by the header action). */
    public const AI_DIALOG_ID = 'nowo-wiki-ai-dialog';

    /**
     * @param array<string, array{path: string, name: string}> $routes `nowo_wiki.routes` (route key => path / name)
     * @param string|null $dashboardRoute `nowo_wiki.dashboard_route` (a "Back to dashboard" header action)
     */
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private array $routes,
        private WikiPages $pages = new WikiPages(),
        private ?string $dashboardRoute = null,
    ) {
    }

    /**
     * Template of a page: the `web_ui.templates.<page>` override, else the bundle template.
     */
    public function template(string $page): string
    {
        return $this->pages->template($page);
    }

    /**
     * Page variables plus the shared ones (legacy layout bridge).
     *
     * @param array<string, mixed> $vars
     *
     * @return array<string, mixed>
     */
    public function context(array $vars): array
    {
        return $this->pages->context($vars);
    }

    /**
     * Wiki index: the spaces of the current page as a list (name, slug), Search / Ask AI / dashboard in the header,
     * the global search form in the filters region.
     *
     * @param list<WikiSpace> $spaces spaces of the current page
     */
    public function index(array $spaces, ?Pagination $pagination, FormView $searchForm, bool $canAskAi): ListPage
    {
        $actions = [];
        if ($canAskAi && $this->has('ai_ask')) {
            $actions[] = new Action('custom', WikiPages::label('wiki.ai.title'), variant: 'primary', attributes: ['data-testid' => 'wiki-ai-open'], dialogId: self::AI_DIALOG_ID);
        }
        $actions = [...$actions, ...$this->dashboardActions()];

        $rows = [];
        foreach ($spaces as $space) {
            $rows[] = [
                'id'   => $space->getSlug(),
                'name' => $space->getName(),
                'slug' => $space->getSlug(),
                'href' => $this->url('space', ['spaceSlug' => $space->getSlug()]),
            ];
        }

        return new ListPage(
            header: new PageHeader(WikiPages::label('wiki.index.title'), WikiPages::label('wiki.index.intro'), $actions),
            columns: [
                new Column('name', WikiPages::label('wiki.index.name'), required: true),
                new Column('slug', WikiPages::label('wiki.index.slug'), priority: 2),
            ],
            rows: $rows,
            filters: $searchForm,
            rowActions: static fn (array $row): array => [Action::view((string) $row['href'], WikiPages::label('wiki.index.open'))],
            pagination: $pagination,
            empty: new EmptyState(null, WikiPages::label('wiki.index.empty')),
            bundle: WikiPages::BUNDLE,
            layout: $this->pages->layout(),
            attributes: ['data-testid' => 'wiki-spaces'],
            actionsLabel: WikiPages::label('wiki.index.actions'),
            filtersSubmit: new Action('filter', WikiPages::label('wiki.search.submit'), variant: 'primary'),
        );
    }

    /**
     * Space home: New page / exports in the header; the template renders the tree, the search and import forms and the
     * archived pages ({@see self::archived()}).
     *
     * @param array{create: bool, import: bool, export: bool} $can what the user may do in the space
     */
    public function space(WikiSpace $space, array $can): ShowPage
    {
        $actions = [];
        if ($can['create'] && $this->has('page_new')) {
            $actions[] = new Action('create', WikiPages::label('wiki.space.new_page'), $this->url('page_new', ['spaceSlug' => $space->getSlug()]), variant: 'primary', attributes: ['data-testid' => 'wiki-new-page']);
        }
        if ($can['export'] && $this->has('space_export')) {
            foreach (['outline', 'notion'] as $format) {
                $actions[] = new Action('export', WikiPages::label('wiki.export.' . $format), $this->url('space_export', ['spaceSlug' => $space->getSlug(), 'format' => $format]));
            }
        }

        return new ShowPage(
            header: new PageHeader(
                $space->getName(),
                // 1.12: "or create one" only for users who may create pages.
                WikiPages::label($can['create'] ? 'wiki.space.pick_page' : 'wiki.space.pick_page_read_only'),
                [...$actions, ...$this->dashboardActions()],
                $this->crumbs($space),
            ),
            bundle: WikiPages::BUNDLE,
            layout: $this->pages->layout(),
            attributes: ['data-testid' => 'wiki-space'],
        );
    }

    /**
     * Page view: Edit / History / Archive (POST + CSRF behind a Confirm) in the header, revision meta; the template
     * renders the tree, the sanitized content and "Used in".
     *
     * @param array{edit: bool, history: bool} $can what the user may do on the page
     * @param FormView|null $archiveForm CSRF form of the archive action (null = no archive action)
     */
    public function pageView(WikiSpace $space, WikiPage $page, array $can, ?FormView $archiveForm = null): ShowPage
    {
        $params  = ['spaceSlug' => $space->getSlug(), 'pageSlug' => $page->getSlug()];
        $actions = [];
        if ($can['edit'] && $this->has('page_edit')) {
            $actions[] = new Action('edit', WikiPages::label('wiki.page.edit'), $this->url('page_edit', $params), attributes: ['data-testid' => 'wiki-edit']);
        }
        if ($can['history'] && $this->has('page_history')) {
            $actions[] = new Action('view', WikiPages::label('wiki.page.history'), $this->url('page_history', $params), attributes: ['data-testid' => 'wiki-history']);
        }
        if ($archiveForm instanceof FormView && $this->has('page_archive')) {
            $actions[] = new Action(
                'delete',
                WikiPages::label('wiki.page.archive'),
                $this->url('page_archive', $params),
                method: 'POST',
                confirm: new Confirm(
                    WikiPages::label('wiki.page.archive_confirm_title'),
                    WikiPages::label('wiki.page.archive_confirm_message', ['%title%' => $page->getTitle()]),
                    WikiPages::label('wiki.page.archive'),
                    cancelLabel: WikiPages::label('wiki.action.cancel'),
                ),
                form: $archiveForm,
            );
        }

        $revision = $page->getCurrentRevision();
        $meta     = [];
        if ($revision instanceof WikiPageRevision) {
            $meta[] = new DetailItem(WikiPages::label('wiki.page.revision'), 'v' . $revision->getRevisionNumber());
            $meta[] = new DetailItem(WikiPages::label('wiki.page.updated'), $this->date($revision->getCreatedAt()));
        }

        return new ShowPage(
            header: new PageHeader($page->getTitle(), $space->getName(), $actions, $this->crumbs($space, $page)),
            meta: $meta,
            bundle: WikiPages::BUNDLE,
            layout: $this->pages->layout(),
            attributes: ['data-testid' => 'wiki-page'],
        );
    }

    /**
     * Page create (no page) / edit form: title + Tiptap content, Save, Cancel back to the page (or the space).
     */
    public function pageForm(WikiSpace $space, ?WikiPage $page, FormView $form): FormPage
    {
        $back = $page instanceof WikiPage
            ? $this->url('page_view', ['spaceSlug' => $space->getSlug(), 'pageSlug' => $page->getSlug()])
            : $this->url('space', ['spaceSlug' => $space->getSlug()]);

        return new FormPage(
            header: new PageHeader(
                $page instanceof WikiPage ? WikiPages::label('wiki.page.edit_title', ['%title%' => $page->getTitle()]) : WikiPages::label('wiki.page.new'),
                $space->getName(),
                breadcrumbs: $this->crumbs($space, $page, WikiPages::label($page instanceof WikiPage ? 'wiki.page.edit' : 'wiki.page.new')),
                back: new BackLink($back, WikiPages::label('wiki.action.back'), 'wiki-back'),
            ),
            form: $form,
            submitLabel: WikiPages::label('wiki.form.save'),
            cancelHref: $back,
            cancelLabel: WikiPages::label('wiki.action.cancel'),
            bundle: WikiPages::BUNDLE,
            layout: $this->pages->layout(),
            attributes: ['data-testid' => 'wiki-page-form'],
        );
    }

    /**
     * Version history: revision number and date per row, Compare (diff with the next older revision) and Restore
     * (POST + CSRF behind a Confirm) per row, paginated.
     *
     * @param list<WikiPageRevision> $revisions revisions of the current page, newest first
     * @param WikiPageRevision|null $older first revision of the next page (compare target of the last row)
     * @param array<int|string, FormView> $restoreForms revision number => CSRF restore form
     */
    public function history(WikiSpace $space, WikiPage $page, array $revisions, ?WikiPageRevision $older, array $restoreForms, ?Pagination $pagination): ListPage
    {
        $params  = ['spaceSlug' => $space->getSlug(), 'pageSlug' => $page->getSlug()];
        $rows    = [];
        $actions = [];
        foreach ($revisions as $index => $revision) {
            $number = $revision->getRevisionNumber();
            $rows[] = [
                'id'      => $number,
                'number'  => 'v' . $number,
                'created' => $this->date($revision->getCreatedAt()),
                'current' => $revision->getId() === $page->getCurrentRevision()?->getId(),
            ];
            $previous = $revisions[$index + 1] ?? $older;
            $row      = [];
            if ($previous instanceof WikiPageRevision && $this->has('page_diff')) {
                $row[] = new Action('view', WikiPages::label('wiki.history.diff'), $this->url('page_diff', $params + ['from' => $previous->getRevisionNumber(), 'to' => $number]));
            }
            if (isset($restoreForms[$number]) && $this->has('page_restore')) {
                $row[] = new Action(
                    'restore',
                    WikiPages::label('wiki.history.restore'),
                    $this->url('page_restore', $params + ['revisionNumber' => $number]),
                    method: 'POST',
                    confirm: new Confirm(
                        WikiPages::label('wiki.history.restore_confirm_title'),
                        WikiPages::label('wiki.history.restore_confirm_message', ['%number%' => $number]),
                        WikiPages::label('wiki.history.restore'),
                        'primary',
                        WikiPages::label('wiki.action.cancel'),
                    ),
                    form: $restoreForms[$number],
                );
            }
            $actions[$number] = $row;
        }

        return new ListPage(
            header: new PageHeader(
                WikiPages::label('wiki.history.title'),
                $page->getTitle(),
                breadcrumbs: $this->crumbs($space, $page, WikiPages::label('wiki.history.title')),
                back: new BackLink($this->url('page_view', $params), WikiPages::label('wiki.action.back_to_page'), 'wiki-back'),
            ),
            columns: [
                new Column('number', WikiPages::label('wiki.history.revision'), required: true),
                new Column('created', WikiPages::label('wiki.history.created')),
            ],
            rows: $rows,
            rowActions: static fn (array $row): array => $actions[(int) $row['id']] ?? [],
            pagination: $pagination,
            empty: new EmptyState(null, WikiPages::label('wiki.history.empty')),
            bundle: WikiPages::BUNDLE,
            layout: $this->pages->layout(),
            attributes: ['data-testid' => 'wiki-history'],
            actionsLabel: WikiPages::label('wiki.index.actions'),
        );
    }

    /**
     * Diff of two revisions; the template renders the lines.
     */
    public function diff(WikiSpace $space, WikiPage $page, int $from, int $to): ShowPage
    {
        $params = ['spaceSlug' => $space->getSlug(), 'pageSlug' => $page->getSlug()];

        return new ShowPage(
            header: new PageHeader(
                WikiPages::label('wiki.diff.title'),
                WikiPages::label('wiki.diff.range', ['%from%' => $from, '%to%' => $to, '%title%' => $page->getTitle()]),
                breadcrumbs: $this->crumbs($space, $page, WikiPages::label('wiki.diff.title')),
                back: $this->has('page_history') ? new BackLink($this->url('page_history', $params), WikiPages::label('wiki.history.title'), 'wiki-back') : null,
            ),
            bundle: WikiPages::BUNDLE,
            layout: $this->pages->layout(),
            attributes: ['data-testid' => 'wiki-diff'],
        );
    }

    /**
     * Search: the search form in the filters region, one row per hit (title, space, excerpt), Open per row.
     *
     * @param list<Hit> $hits search hits the user may read
     */
    public function search(FormView $form, array $hits, string $query, bool $global): ListPage
    {
        $rows = [];
        foreach ($hits as $index => $hit) {
            $rows[] = [
                'id'      => $index,
                'title'   => $hit['page']->getTitle(),
                'space'   => $hit['space']->getName(),
                'excerpt' => $hit['excerpt'],
                'href'    => $this->url('page_view', ['spaceSlug' => $hit['space']->getSlug(), 'pageSlug' => $hit['page']->getSlug()]),
            ];
        }

        return new ListPage(
            header: new PageHeader(
                WikiPages::label('wiki.search.title'),
                WikiPages::label($global && $query !== '' ? 'wiki.search.global_results' : 'wiki.search.intro'),
                $this->dashboardActions(),
                [new Crumb(WikiPages::label('wiki.title'), $this->optionalUrl('index')), new Crumb(WikiPages::label('wiki.search.title'))],
            ),
            columns: [
                new Column('title', WikiPages::label('wiki.search.result_title'), required: true),
                new Column('space', WikiPages::label('wiki.search.result_space'), priority: 2),
                new Column('excerpt', WikiPages::label('wiki.search.result_excerpt'), priority: 2),
            ],
            rows: $rows,
            filters: $form,
            rowActions: static fn (array $row): array => [Action::view((string) $row['href'], WikiPages::label('wiki.index.open'))],
            empty: new EmptyState(null, WikiPages::label($query !== '' ? 'wiki.search.empty' : 'wiki.search.help')),
            caption: $rows === [] ? null : WikiPages::label('wiki.search.results_count', ['%count%' => count($rows)]),
            bundle: WikiPages::BUNDLE,
            layout: $this->pages->layout(),
            attributes: ['data-testid' => 'wiki-search'],
            actionsLabel: WikiPages::label('wiki.index.actions'),
            filtersSubmit: new Action('filter', WikiPages::label('wiki.search.submit'), variant: 'primary'),
        );
    }

    /**
     * AI question: the question form; the template renders the error, the answer and the sources ({@see self::hits()}).
     */
    public function aiAsk(FormView $form, bool $answered): FormPage
    {
        return new FormPage(
            header: new PageHeader(
                WikiPages::label('wiki.ai.title'),
                WikiPages::label('wiki.ai.intro'),
                $this->dashboardActions(),
                [new Crumb(WikiPages::label('wiki.title'), $this->optionalUrl('index')), new Crumb(WikiPages::label('wiki.ai.title'))],
            ),
            form: $form,
            submitLabel: WikiPages::label($answered ? 'wiki.ai.follow_up' : 'wiki.ai.submit'),
            bundle: WikiPages::BUNDLE,
            layout: $this->pages->layout(),
            attributes: ['data-testid' => 'wiki-ai'],
        );
    }

    /**
     * Value-free page tree: title, URL, current flag and children per node.
     *
     * @param list<SourceNode> $nodes {@see WikiPageTreeBuilder::build()} nodes
     *
     * @return list<TreeNode>
     */
    public function tree(WikiSpace $space, array $nodes, ?string $currentSlug = null): array
    {
        $tree = [];
        foreach ($nodes as $node) {
            /** @var list<SourceNode> $children */
            $children = is_array($node['children']) ? $node['children'] : [];
            $tree[]   = [
                'title'    => $node['page']->getTitle(),
                'href'     => $this->url('page_view', ['spaceSlug' => $space->getSlug(), 'pageSlug' => $node['page']->getSlug()]),
                'current'  => $currentSlug !== null && $node['page']->getSlug() === $currentSlug,
                'children' => $this->tree($space, $children, $currentSlug),
            ];
        }

        return $tree;
    }

    /**
     * Archived pages of a space: title and the Restore page action (POST + CSRF form).
     *
     * @param list<WikiPage> $pages archived pages the user may read
     * @param array<int|string, FormView> $forms page id => CSRF unarchive form
     *
     * @return list<array{title: string, action: Action|null}>
     */
    public function archived(WikiSpace $space, array $pages, array $forms): array
    {
        $items = [];
        foreach ($pages as $page) {
            $form    = $forms[$page->getId()] ?? null;
            $items[] = [
                'title'  => $page->getTitle(),
                'action' => $form instanceof FormView && $this->has('page_unarchive')
                    ? new Action('restore', WikiPages::label('wiki.page.unarchive'), $this->url('page_unarchive', ['spaceSlug' => $space->getSlug(), 'pageSlug' => $page->getSlug()]), method: 'POST', form: $form)
                    : null,
            ];
        }

        return $items;
    }

    /**
     * Value-free search hits / AI sources: title, space name, excerpt and URL.
     *
     * @param list<Hit> $hits
     *
     * @return list<array{title: string, space: string, excerpt: string, href: string}>
     */
    public function hits(array $hits): array
    {
        $items = [];
        foreach ($hits as $hit) {
            $items[] = [
                'title'   => $hit['page']->getTitle(),
                'space'   => $hit['space']->getName(),
                'excerpt' => $hit['excerpt'],
                'href'    => $this->url('page_view', ['spaceSlug' => $hit['space']->getSlug(), 'pageSlug' => $hit['page']->getSlug()]),
            ];
        }

        return $items;
    }

    /**
     * Value-free "Used in" entries (the resolver already kept only the objects the viewer may open).
     *
     * @param array<string, list<WikiLinkedObject>> $usedIn object type => objects
     *
     * @return list<array{type: string, label: string, href: string, badge: string|null}>
     */
    public function usedIn(array $usedIn): array
    {
        $items = [];
        foreach ($usedIn as $objects) {
            foreach ($objects as $object) {
                $items[] = ['type' => $object->type, 'label' => $object->label, 'href' => $object->url, 'badge' => $object->badge];
            }
        }

        return $items;
    }

    /**
     * @return list<Action>
     */
    private function dashboardActions(): array
    {
        if ($this->dashboardRoute === null || $this->dashboardRoute === '') {
            return [];
        }

        return [new Action('prev', WikiPages::label('wiki.back_dashboard'), $this->urlGenerator->generate($this->dashboardRoute))];
    }

    /**
     * Wiki > space (> page (> current)) trail.
     *
     * @return list<Crumb>
     */
    private function crumbs(WikiSpace $space, ?WikiPage $page = null, ?Label $current = null): array
    {
        $crumbs = [
            new Crumb(WikiPages::label('wiki.title'), $this->optionalUrl('index')),
            new Crumb($space->getName(), $page instanceof WikiPage || $current instanceof Label ? $this->optionalUrl('space', ['spaceSlug' => $space->getSlug()]) : null),
        ];
        if ($page instanceof WikiPage) {
            $crumbs[] = new Crumb($page->getTitle(), $current instanceof Label ? $this->optionalUrl('page_view', ['spaceSlug' => $space->getSlug(), 'pageSlug' => $page->getSlug()]) : null);
        }
        if ($current instanceof Label) {
            $crumbs[] = new Crumb($current);
        }

        return $crumbs;
    }

    private function has(string $route): bool
    {
        return isset($this->routes[$route]);
    }

    /**
     * URL of a configured route, null when the route is not configured (a trail entry without link).
     *
     * @param array<string, int|string> $params
     */
    private function optionalUrl(string $route, array $params = []): ?string
    {
        return $this->has($route) ? $this->url($route, $params) : null;
    }

    /**
     * @param array<string, int|string> $params
     */
    private function url(string $route, array $params = []): string
    {
        return $this->urlGenerator->generate($this->routes[$route]['name'], $params);
    }

    private function date(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i');
    }
}
