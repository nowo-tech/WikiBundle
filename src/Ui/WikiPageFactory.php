<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Ui;

use DateTimeInterface;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\AreaNav;
use Nowo\UiKitBundle\View\AreaNavItem;
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

use function array_fill_keys;
use function array_key_exists;
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
 * @phpstan-type TreeNode array{id: string, title: string, href: string, current: bool, children: list<mixed>}
 * @phpstan-type UiTreeNode array{id: string, label: string, href: string, current: bool, expanded: bool, children: list<mixed>}
 * @phpstan-type SourceNode array{page: WikiPage, children: list<mixed>}
 * @phpstan-type Hit array{page: WikiPage, space: WikiSpace, excerpt: string}
 */
final readonly class WikiPageFactory
{
    /** Id of the AI question dialog of the index (opened by the header action). */
    public const AI_DIALOG_ID = 'nowo-wiki-ai-dialog';

    /** 1.21: id of the Import & export dialog of the space home (`web_ui.import_export: dialog`). */
    public const IMPORT_EXPORT_DIALOG_ID = 'nowo-wiki-import-export-dialog';

    /** Formats of the space export links. */
    public const EXPORT_FORMATS = ['outline', 'notion'];

    /**
     * 1.18 (Beacon spec 177): default icon of each page tab (`web_ui.tab_icons`), so the tab row follows the host's tab
     * style (text / icon and text / icon only). An icon name of the UiKit `icon` component (`tabler:*` renders with the
     * `ux_icon` or `tabler-icons` set, else the label alone).
     */
    public const TAB_ICONS = [
        'page'    => 'tabler:file-text',
        'edit'    => 'tabler:pencil',
        'history' => 'tabler:history',
    ];

    /**
     * @param array<string, array{path: string, name: string}> $routes `nowo_wiki.routes` (route key => path / name)
     * @param string|null $dashboardRoute `nowo_wiki.dashboard_route` (a "Back to dashboard" header action)
     * @param array<string, string|null> $tabIcons 1.18 `web_ui.tab_icons` (page | edit | history => icon name; null or
     *                                             '' = no icon on that tab)
     * @param bool $importExportDialog 1.21 `web_ui.import_export: dialog`: one header action opens the Import & export
     *                                 dialog instead of the export actions (and the import card)
     * @param bool $compactHeader 1.29 `web_ui.page_header.layout: compact`: the page view's header actions use the
     *                            UiKit `split` layout (icon buttons, one primary, a "more" menu) beside the inline tabs
     */
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private array $routes,
        private WikiPages $pages = new WikiPages(),
        private ?string $dashboardRoute = null,
        private array $tabIcons = self::TAB_ICONS,
        private bool $importExportDialog = false,
        private bool $compactHeader = false,
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
        $exports = $this->exportLinks($space, $can);
        if ($this->importExportDialog && ($exports !== [] || $can['import'])) {
            // 1.21: one secondary action; the dialog (space template, nowo_ui_modals) holds the exports and the import form.
            $actions[] = new Action('custom', WikiPages::label('wiki.import_export.title'), attributes: ['data-testid' => 'wiki-import-export-open'], dialogId: self::IMPORT_EXPORT_DIALOG_ID);
        } elseif (!$this->importExportDialog) {
            foreach ($exports as $export) {
                $actions[] = new Action('export', WikiPages::label($export['label']), $export['href']);
            }
        }
        // 1.14: the trash of the space, for users who may delete pages (`trash.enabled`).
        if (($can['trash'] ?? false) && $this->has('space_trash')) {
            $actions[] = new Action('view', WikiPages::label('wiki.trash.title'), $this->url('space_trash', ['spaceSlug' => $space->getSlug()]), attributes: ['data-testid' => 'wiki-trash']);
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
     * The space export links (Outline, Notion) for a user who may export, else none.
     *
     * @param array{export?: bool} $can what the user may do in the space
     *
     * @return list<array{format: string, label: string, href: string}> label = catalogue key of the bundle domain
     */
    public function exportLinks(WikiSpace $space, array $can): array
    {
        if (!($can['export'] ?? false) || !$this->has('space_export')) {
            return [];
        }

        $links = [];
        foreach (self::EXPORT_FORMATS as $format) {
            $links[] = ['format' => $format, 'label' => 'wiki.export.' . $format, 'href' => $this->url('space_export', ['spaceSlug' => $space->getSlug(), 'format' => $format])];
        }

        return $links;
    }

    /**
     * Page view: Save version / Move / Delete / Archive (POST + CSRF behind a Confirm) in the header, the tab row
     * (Page / Edit / History, {@see self::pageTabs()}), revision meta; the template renders the tree, the sanitized
     * content and "Used in". 1.25 (WK-6): Edit and History are only tabs (until 1.24 they were header actions too).
     *
     * @param array{edit: bool, history: bool, move?: bool} $can what the user may do on the page (1.14: move)
     * @param FormView|null $archiveForm CSRF form of the archive action (null = no archive action)
     * @param FormView|null $deleteForm CSRF form of the Delete action (1.14 trash; null = no Delete action)
     */
    public function pageView(WikiSpace $space, WikiPage $page, array $can, ?FormView $archiveForm = null, ?FormView $deleteForm = null): ShowPage
    {
        $params = ['spaceSlug' => $space->getSlug(), 'pageSlug' => $page->getSlug()];
        $tabs   = $this->pageTabs($space, $page, $can, 'page');
        // 1.25 (WK-6): Edit and History are tabs of the tab row (1.16, same rights), never header actions as well.
        $actions = [];
        // 1.15: Save version names the current revision (history.timeline, editors).
        if (($can['version'] ?? false) && $this->has('page_version_save')) {
            $actions[] = new Action('create', WikiPages::label('wiki.history.save_version'), $this->url('page_version_save', $params), attributes: ['data-testid' => 'wiki-save-version']);
        }
        if (($can['move'] ?? false) && $this->has('page_move')) {
            // 1.29 compact header: Move, Delete and Archive close the "more" menu in the danger style, each with its verb icon.
            $actions[] = $this->compactHeader
                ? new Action('move', WikiPages::label('wiki.tree.move'), $this->url('page_move', $params), variant: 'danger', attributes: ['data-testid' => 'wiki-move'])
                : new Action('edit', WikiPages::label('wiki.tree.move'), $this->url('page_move', $params), attributes: ['data-testid' => 'wiki-move']);
        }
        // 1.14: Delete moves the page and its sub-pages to the trash (POST + CSRF behind a Confirm).
        if ($deleteForm instanceof FormView && $this->has('page_delete')) {
            $actions[] = new Action(
                'delete',
                WikiPages::label('wiki.trash.delete'),
                $this->url('page_delete', $params),
                method: 'POST',
                confirm: new Confirm(
                    WikiPages::label('wiki.trash.delete_confirm_title'),
                    WikiPages::label('wiki.trash.delete_confirm_message', ['%title%' => $page->getTitle()]),
                    WikiPages::label('wiki.trash.delete'),
                    cancelLabel: WikiPages::label('wiki.action.cancel'),
                ),
                attributes: ['data-testid' => 'wiki-delete'],
                form: $deleteForm,
            );
        }
        if ($archiveForm instanceof FormView && $this->has('page_archive')) {
            $actions[] = new Action(
                $this->compactHeader ? 'archive' : 'delete',
                WikiPages::label('wiki.page.archive'),
                $this->url('page_archive', $params),
                method: 'POST',
                variant: $this->compactHeader ? 'danger' : null,
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
            header: (new PageHeader($page->getTitle(), $space->getName(), $actions, $this->crumbs($space, $page), actionsLayout: $this->compactHeader ? 'split' : 'toolbar'))->withTabs($tabs),
            meta: $meta,
            bundle: WikiPages::BUNDLE,
            layout: $this->pages->layout(),
            attributes: ['data-testid' => 'wiki-page'],
        );
    }

    /**
     * Page create (no page) / edit form: title + Tiptap content, Save, Cancel back to the page (or the space).
     */
    /**
     * @param array{history?: bool} $can 1.16: what the user may do on the page (the History tab of an existing page)
     * @param array<string, string> $formAttributes extra `<form>` attributes
     */
    public function pageForm(WikiSpace $space, ?WikiPage $page, FormView $form, ?FormView $collabResetForm = null, array $can = [], array $formAttributes = []): FormPage
    {
        $back = $page instanceof WikiPage
            ? $this->url('page_view', ['spaceSlug' => $space->getSlug(), 'pageSlug' => $page->getSlug()])
            : $this->url('space', ['spaceSlug' => $space->getSlug()]);
        $actions = [];
        // 1.13: co-editing — the editor's explicit reset of the shared session (POST + CSRF behind a confirm).
        if ($page instanceof WikiPage && $collabResetForm instanceof FormView) {
            $actions[] = new Action(
                'delete',
                WikiPages::label('wiki.collab.reset'),
                (string) ($collabResetForm->vars['action'] ?? ''),
                method: 'POST',
                confirm: new Confirm(
                    WikiPages::label('wiki.collab.reset_confirm_title'),
                    WikiPages::label('wiki.collab.reset_confirm_message'),
                    WikiPages::label('wiki.collab.reset'),
                    cancelLabel: WikiPages::label('wiki.action.cancel'),
                ),
                attributes: ['data-testid' => 'wiki-collab-reset'],
                form: $collabResetForm,
            );
        }

        return new FormPage(
            header: new PageHeader(
                $page instanceof WikiPage ? WikiPages::label('wiki.page.edit_title', ['%title%' => $page->getTitle()]) : WikiPages::label('wiki.page.new'),
                $space->getName(),
                $actions,
                breadcrumbs: $this->crumbs($space, $page, WikiPages::label($page instanceof WikiPage ? 'wiki.page.edit' : 'wiki.page.new')),
                // 1.16 (B-Q1): the edit view of an existing page gets the tab row (Edit is the current tab).
                tabs: $page instanceof WikiPage ? $this->pageTabs($space, $page, ['edit' => true, 'history' => $can['history'] ?? false], 'edit') : null,
                back: new BackLink($back, WikiPages::label('wiki.action.back'), 'wiki-back'),
            ),
            form: $form,
            submitLabel: WikiPages::label('wiki.form.save'),
            cancelHref: $back,
            cancelLabel: WikiPages::label('wiki.action.cancel'),
            bundle: WikiPages::BUNDLE,
            layout: $this->pages->layout(),
            attributes: ['data-testid' => 'wiki-page-form'],
            formAttributes: $formAttributes,
        );
    }

    /**
     * The confirmation of a restore while the shared session has unsaved changes (audit HO-L3).
     */
    public function restoreConflict(WikiSpace $space, WikiPage $page, FormView $form, ?string $cancelHref): FormPage
    {
        return new FormPage(
            header: new PageHeader(
                WikiPages::label('wiki.collab.restore_conflict_title'),
                WikiPages::label('wiki.collab.restore_conflict_message'),
                breadcrumbs: $this->crumbs($space, $page, WikiPages::label('wiki.collab.restore_conflict_title')),
            ),
            form: $form,
            submitLabel: WikiPages::label('wiki.collab.conflict_replace'),
            cancelHref: $cancelHref,
            cancelLabel: WikiPages::label('wiki.action.cancel'),
            bundle: WikiPages::BUNDLE,
            layout: $this->pages->layout(),
            attributes: ['data-testid' => 'wiki-collab-restore-conflict'],
        );
    }

    /**
     * The tab row of the read and edit views (1.16, B-Q1): Page (always), Edit (`can.edit`), History (`can.history`);
     * the current view is the active tab (`aria-current="page"`). Rendered at the secondary level, with the comments
     * toggle at the end of the same row when the viewer may see comments. 1.18: each tab carries its icon
     * (`web_ui.tab_icons`, {@see self::TAB_ICONS}).
     *
     * @param array{edit?: bool, history?: bool} $can what the user may do on the page
     * @param string $current page | edit | history (1.30: the history view's toolbar buttons, `page_header.tabs: toolbar`)
     */
    public function pageTabs(WikiSpace $space, WikiPage $page, array $can, string $current): AreaNav
    {
        $params = ['spaceSlug' => $space->getSlug(), 'pageSlug' => $page->getSlug()];
        $items  = [new AreaNavItem(WikiPages::label('wiki.tabs.page'), $this->url('page_view', $params), $current === 'page', ['data-testid' => 'wiki-tab-page'], $this->tabIcon('page'))];
        if (($can['edit'] ?? false) && $this->has('page_edit')) {
            $items[] = new AreaNavItem(WikiPages::label('wiki.tabs.edit'), $this->url('page_edit', $params), $current === 'edit', ['data-testid' => 'wiki-tab-edit'], $this->tabIcon('edit'));
        }
        if (($can['history'] ?? false) && $this->has('page_history')) {
            $items[] = new AreaNavItem(WikiPages::label('wiki.tabs.history'), $this->url('page_history', $params), $current === 'history', ['data-testid' => 'wiki-tab-history'], $this->tabIcon('history'));
        }

        return new AreaNav($items, WikiPages::label('wiki.tabs.label'));
    }

    /**
     * Version history: revision number and date per row, Compare (diff with the next older revision) and Restore
     * (POST + CSRF behind a Confirm) per row, paginated.
     *
     * @param list<WikiPageRevision> $revisions revisions of the current page, newest first
     * @param WikiPageRevision|null $older first revision of the next page (compare target of the last row)
     * @param array<int|string, FormView> $restoreForms revision number => CSRF restore form
     * @param array<int, array{author: string, avatar?: string|null, named?: bool, label: string|null, saves: int, deleted?: bool}>|null $timeline 1.15
     *                                                                                                                                             timeline rows per revision number (null: the 1.14 list); 1.16: `avatar` / `named`; 1.26: `deleted`
     * @param array<int|string, FormView> $copyForms 1.15 Copy to new page forms per revision number
     */
    public function history(WikiSpace $space, WikiPage $page, array $revisions, ?WikiPageRevision $older, array $restoreForms, ?Pagination $pagination, ?array $timeline = null, array $copyForms = []): ListPage
    {
        $params  = ['spaceSlug' => $space->getSlug(), 'pageSlug' => $page->getSlug()];
        $rows    = [];
        $actions = [];
        foreach ($revisions as $index => $revision) {
            $number = $revision->getRevisionNumber();
            $row    = [
                'id'      => $number,
                'number'  => 'v' . $number,
                'created' => $this->date($revision->getCreatedAt()),
                'current' => $revision->getId() === $page->getCurrentRevision()?->getId(),
            ];
            // 1.15 (`history.timeline`): named version, author and the size of the session on its newest row.
            if ($timeline !== null) {
                $entry = $timeline[$number] ?? ['author' => '', 'avatar' => null, 'named' => false, 'label' => null, 'saves' => 0, 'deleted' => false];
                $row += [
                    'label'  => $entry['label'] ?? '',
                    'author' => $entry['author'],
                    // 1.16: the author's picture, only beside a real name (else no avatar at all: avatar contract §11).
                    'author_named'  => $entry['named'] ?? false,
                    'author_avatar' => $entry['avatar'] ?? '',
                    // 1.26: the author's account was deleted (the template shows "Former person").
                    'author_deleted' => $entry['deleted'] ?? false,
                    // Rows hold scalars: the count of saves (2 or more) on the newest row of a session.
                    'session' => $entry['saves'] > 1 ? $entry['saves'] : '',
                ];
            }
            $rows[]   = $row;
            $previous = $revisions[$index + 1] ?? $older;
            $row      = [];
            if ($previous instanceof WikiPageRevision && $this->has('page_diff')) {
                $row[] = new Action('view', WikiPages::label('wiki.history.diff'), $this->url('page_diff', $params + ['from' => $previous->getRevisionNumber(), 'to' => $number]));
            }
            if (isset($copyForms[$number]) && $this->has('page_version_copy')) {
                $row[] = new Action('create', WikiPages::label('wiki.history.copy'), $this->url('page_version_copy', $params + ['revisionNumber' => $number]), method: 'POST', attributes: ['data-testid' => 'wiki-version-copy'], form: $copyForms[$number]);
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
            columns: $timeline === null ? [
                new Column('number', WikiPages::label('wiki.history.revision'), required: true),
                new Column('created', WikiPages::label('wiki.history.created')),
            ] : [
                new Column('number', WikiPages::label('wiki.history.revision'), required: true),
                new Column('label', WikiPages::label('wiki.history.label')),
                new Column('author', WikiPages::label('wiki.history.author')),
                new Column('created', WikiPages::label('wiki.history.created')),
                new Column('session', WikiPages::label('wiki.history.session_column')),
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
     * Value-free page tree: id (1.14), title, URL, current flag and children per node.
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
                'id'       => $node['page']->getId(),
                'title'    => $node['page']->getTitle(),
                'href'     => $this->url('page_view', ['spaceSlug' => $space->getSlug(), 'pageSlug' => $node['page']->getSlug()]),
                'current'  => $currentSlug !== null && $node['page']->getSlug() === $currentSlug,
                'children' => $this->tree($space, $children, $currentSlug),
            ];
        }

        return $tree;
    }

    /**
     * Nodes of the UiKit `tree` partial (1.14, `tree.enabled`): the {@see self::tree()} nodes with the fold state of the
     * user (`expanded` for the ids in it; the current branch is always unfolded by the partial).
     *
     * @param list<TreeNode> $nodes
     * @param list<string> $expanded ids of the pages the user expanded
     *
     * @return list<UiTreeNode>
     */
    public function uiTree(array $nodes, array $expanded): array
    {
        $open = array_fill_keys($expanded, true);
        $map  = static function (array $nodes) use (&$map, $open): array {
            $out = [];
            foreach ($nodes as $node) {
                /** @var TreeNode $node */
                /** @var list<TreeNode> $children */
                $children = $node['children'];
                $out[]    = [
                    'id'       => $node['id'],
                    'label'    => $node['title'],
                    'href'     => $node['href'],
                    'current'  => $node['current'],
                    'expanded' => isset($open[$node['id']]),
                    'children' => $map($children),
                ];
            }

            return $out;
        };

        /* @var list<UiTreeNode> */
        return $map($nodes);
    }

    /**
     * Root attributes of the page tree (1.14): the endpoints and CSRF token read by wiki.js, `data-wiki-tree-movable`
     * when the user may move pages of the space.
     *
     * @return array<string, bool|string>
     */
    public function treeAttributes(WikiSpace $space, string $token, bool $movable): array
    {
        $attributes = [
            'id'                   => 'nowo-wiki-tree',
            'data-wiki-tree'       => true,
            'data-wiki-tree-state' => $this->url('space_tree', ['spaceSlug' => $space->getSlug(), 'action' => 'state']),
            'data-wiki-tree-token' => $token,
            'data-testid'          => 'wiki-tree',
        ];
        if ($movable) {
            $attributes['data-wiki-tree-move'] = $this->url('space_tree', ['spaceSlug' => $space->getSlug(), 'action' => 'move']);
        }

        return $attributes;
    }

    /**
     * The "Move" form of a page (1.14): new parent and position, Move, Cancel back to the page.
     */
    public function pageMove(WikiSpace $space, WikiPage $page, FormView $form): FormPage
    {
        $back = $this->url('page_view', ['spaceSlug' => $space->getSlug(), 'pageSlug' => $page->getSlug()]);

        return new FormPage(
            header: new PageHeader(
                WikiPages::label('wiki.tree.move_title', ['%title%' => $page->getTitle()]),
                $space->getName(),
                [],
                breadcrumbs: $this->crumbs($space, $page, WikiPages::label('wiki.tree.move')),
                back: new BackLink($back, WikiPages::label('wiki.action.back'), 'wiki-back'),
            ),
            form: $form,
            submitLabel: WikiPages::label('wiki.tree.move'),
            cancelHref: $back,
            cancelLabel: WikiPages::label('wiki.action.cancel'),
            bundle: WikiPages::BUNDLE,
            layout: $this->pages->layout(),
            attributes: ['data-testid' => 'wiki-page-move'],
        );
    }

    /**
     * The Save version form of a page (1.15): the label of the current revision.
     */
    public function pageVersion(WikiSpace $space, WikiPage $page, FormView $form): FormPage
    {
        $back = $this->url('page_view', ['spaceSlug' => $space->getSlug(), 'pageSlug' => $page->getSlug()]);

        return new FormPage(
            header: new PageHeader(
                WikiPages::label('wiki.history.save_version_title', ['%title%' => $page->getTitle()]),
                $space->getName(),
                [],
                breadcrumbs: $this->crumbs($space, $page, WikiPages::label('wiki.history.save_version')),
                back: new BackLink($back, WikiPages::label('wiki.action.back'), 'wiki-back'),
            ),
            form: $form,
            submitLabel: WikiPages::label('wiki.history.save_version'),
            cancelHref: $back,
            cancelLabel: WikiPages::label('wiki.action.cancel'),
            bundle: WikiPages::BUNDLE,
            layout: $this->pages->layout(),
            attributes: ['data-testid' => 'wiki-page-version'],
        );
    }

    /**
     * The trash of a space (1.14): one row per deletion — title of the deleted page, date, number of pages — with
     * Restore (POST + CSRF) and Purge (a link to the second confirmation) per row.
     *
     * @param list<array{title: string, deleted: DateTimeInterface, pages: int, restore: FormView, purge: bool, batch: string}> $rows
     *
     * @return array{page: ShowPage, items: list<array{title: string, deleted: string, pages: int, actions: list<Action>}>}
     */
    public function trash(WikiSpace $space, array $rows, int $retentionDays): array
    {
        $items = [];
        foreach ($rows as $row) {
            $params  = ['spaceSlug' => $space->getSlug(), 'batchId' => $row['batch']];
            $items[] = [
                'title'   => $row['title'],
                'deleted' => $this->date($row['deleted']),
                'pages'   => $row['pages'],
                'actions' => [
                    new Action('restore', WikiPages::label('wiki.trash.restore'), $this->url('trash_restore', $params), method: 'POST', attributes: ['data-testid' => 'wiki-trash-restore'], form: $row['restore']),
                    ...($row['purge'] ? [new Action('delete', WikiPages::label('wiki.trash.purge'), $this->url('trash_purge', $params), attributes: ['data-testid' => 'wiki-trash-purge'])] : []),
                ],
            ];
        }

        return ['items' => $items, 'page' => new ShowPage(
            header: new PageHeader(
                WikiPages::label('wiki.trash.title'),
                WikiPages::label('wiki.trash.intro', ['%days%' => $retentionDays]),
                [],
                $this->crumbs($space, null, WikiPages::label('wiki.trash.title')),
                back: new BackLink($this->url('space', ['spaceSlug' => $space->getSlug()]), WikiPages::label('wiki.action.back'), 'wiki-back'),
            ),
            bundle: WikiPages::BUNDLE,
            layout: $this->pages->layout(),
            attributes: ['data-testid' => 'wiki-trash-page'],
        )];
    }

    /**
     * The second confirmation of a purge (1.14): what will be deleted for good, and the final button (POST + CSRF
     * behind a Confirm).
     */
    public function trashPurge(WikiSpace $space, string $batchId, string $title, int $pages, FormView $form): ShowPage
    {
        $trash = $this->url('space_trash', ['spaceSlug' => $space->getSlug()]);

        return new ShowPage(
            header: new PageHeader(
                WikiPages::label('wiki.trash.purge_title', ['%title%' => $title]),
                WikiPages::label('wiki.trash.purge_intro', ['%count%' => $pages]),
                [new Action(
                    'delete',
                    WikiPages::label('wiki.trash.purge'),
                    $this->url('trash_purge', ['spaceSlug' => $space->getSlug(), 'batchId' => $batchId]),
                    method: 'POST',
                    confirm: new Confirm(
                        WikiPages::label('wiki.trash.purge_confirm_title'),
                        WikiPages::label('wiki.trash.purge_confirm_message', ['%title%' => $title, '%count%' => $pages]),
                        WikiPages::label('wiki.trash.purge'),
                        cancelLabel: WikiPages::label('wiki.action.cancel'),
                    ),
                    attributes: ['data-testid' => 'wiki-trash-purge-confirm'],
                    form: $form,
                )],
                $this->crumbs($space, null, WikiPages::label('wiki.trash.title')),
                back: new BackLink($trash, WikiPages::label('wiki.action.back'), 'wiki-back'),
            ),
            bundle: WikiPages::BUNDLE,
            layout: $this->pages->layout(),
            attributes: ['data-testid' => 'wiki-trash-purge-page'],
        );
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

    /**
     * Icon of a page tab: the configured one (`web_ui.tab_icons.<tab>`), else the default; null or '' = no icon.
     *
     * @param 'edit'|'history'|'page' $tab
     */
    private function tabIcon(string $tab): ?string
    {
        $icon = array_key_exists($tab, $this->tabIcons) ? $this->tabIcons[$tab] : self::TAB_ICONS[$tab];

        return $icon === null || $icon === '' ? null : $icon;
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
