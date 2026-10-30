# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[1.11.0] - 2026-10-30](#1110---2026-10-30)
- [[1.10.0] - 2026-10-30](#1100---2026-10-30)
- [[1.9.0] - 2026-10-30](#190---2026-10-30)
- [[1.8.1] - 2026-10-29](#181---2026-10-29)
- [[1.8.0] - 2026-10-29](#180---2026-10-29)
- [[1.7.2] - 2026-10-28](#172---2026-10-28)
- [[1.7.1] - 2026-10-28](#171---2026-10-28)
- [[1.7.0] - 2026-10-26](#170---2026-10-26)
- [[1.6.4] - 2026-10-24](#164---2026-10-24)
- [[1.6.3] - 2026-10-18](#163---2026-10-18)
- [[1.6.2] - 2026-10-13](#162---2026-10-13)
- [[1.6.1] - 2026-10-13](#161---2026-10-13)
- [[1.6.0] - 2026-10-13](#160---2026-10-13)
- [[1.5.0] - 2026-10-13](#150---2026-10-13)
- [[1.4.1] - 2026-10-13](#141---2026-10-13)
- [[1.4.0] - 2026-10-09](#140---2026-10-09)
- [[1.3.5] - 2026-09-27](#135---2026-09-27)
- [[1.3.4] - 2026-08-24](#134---2026-08-24)
- [[1.3.3] - 2026-08-19](#133---2026-08-19)
- [[1.3.2] - 2026-08-18](#132---2026-08-18)
- [[1.3.1] - 2026-08-07](#131---2026-08-07)
- [[1.3.0] - 2026-08-04](#130---2026-08-04)
- [[1.2.0] - 2026-08-03](#120---2026-08-03)
- [[1.1.1] - 2026-07-31](#111---2026-07-31)
- [[1.1.0] - 2026-07-30](#110---2026-07-30)
- [[1.0.5] - 2026-07-29](#105---2026-07-29)
- [[1.0.4] - 2026-07-22](#104---2026-07-22)
- [[1.0.3] - 2026-07-16](#103---2026-07-16)
- [[1.0.2] - 2026-07-16](#102---2026-07-16)
- [[1.0.1] - 2026-07-16](#101---2026-07-16)
- [[1.0.0] - 2026-07-13](#100---2026-07-13)

## [Unreleased]

### Fixed

- **Wiki routes registered without the route loader (REQ-SF-004 rule 7):** on Symfony >= 7.3 `#[AsController]` and the `#[Route]` attributes autoconfigured `WikiManageController` and `WikiAttachmentController` with the `routing.controller` tag, so a host whose `config/routes.yaml` has the default `controllers: { resource: routing.controllers }` got the default `/tools/wiki/...` routes even without importing the `nowo_wiki` loader, bypassing `nowo_wiki.routes`, `route_prefix` and `web_ui.enabled: false` (still behind the wiki access checks). The controllers are registered explicitly (`autoconfigure: false`, `controller.service_arguments` + `container.service_subscriber`, no `#[AsController]`); the routes exist only where the host imports `type: nowo_wiki`. Regression test with a kernel that only loads `routing.controllers`.

## [1.11.0] - 2026-10-30

Wiki: Markdown storage (`editor.type: markdown`) — revisions keep their Markdown source with a sanitized HTML render cache, an extensible league/commonmark renderer, GFM everywhere, a configurable sanitizer allowlist and `wiki:convert-markdown` to move existing pages. **Schema change:** two columns on `<prefix>_page_revisions`. See [UPGRADING](UPGRADING.md#to-1110).

### Added

- **Markdown storage:** `nowo_wiki.editor.type` (`tiptap` default | `markdown`). Revisions gain `content_markdown` (nullable) and `format` (`html` | `markdown`); with `markdown` the page form writes Markdown, the Markdown is the source and `content_html` is its sanitized render cache (page view, AI context, attachment references keep reading it). The page body is a plain textarea, or the host form type of `editor.markdown_form_type` (e.g. a Markdown editor bundle) with the editor options of `WikiEvents::EDITOR_OPTIONS`; an HTML page opened in the Markdown editor is converted for the form and saved as Markdown.
- `Markdown\WikiMarkdownRendererInterface` (alias) and `Markdown\WikiMarkdownRenderer`: league/commonmark + GitHub-flavoured Markdown, raw HTML escaped, unsafe links dropped, plus every `WikiMarkdownExtensionInterface` service (autoconfigured tag `nowo_wiki.markdown_extension`; none by default) — the extension point for the page-link / mention / icon / directive dialect of an editor bundle.
- `Markdown\WikiMarkdownReferenceExtractor`: the `[[wiki:<uuid>]]` / `[[wiki:<uuid>#anchor]]` page references of a Markdown revision (outside code), for host backlink indexes (listen to `nowo_wiki.page.saved`).
- `bin/console wiki:convert-markdown <space>... --author-id=<id> [--dry-run]`: appends one Markdown revision per page whose current revision is HTML (`WikiMarkdownConverter::htmlToMarkdown()`, archived pages included, older revisions untouched); idempotent. `WikiPageService::convertToMarkdown()`.
- `WikiPageService::create()` / `saveRevision()` take an optional `$format` (`WikiPageRevision::FORMAT_HTML` default, `FORMAT_MARKDOWN`); `restore()` keeps the format of the restored revision.
- `nowo_wiki.sanitizer.extra_allowed_elements` (element => its extra attributes) and `extra_allowed_attributes` (on every element): extra HTML an editor dialect emits (e.g. `<details>` / `<summary>`, `data-*`). Script-capable, embedding and form elements, `style`, URL attributes and `on*` handlers are refused at configuration time and by `WikiHtmlSanitizer::assertSafeAllowlist()` in the constructor.
- `WikiPageSavedEvent::$conversion`: `true` on the events dispatched by `wiki:convert-markdown`, so hosts index the new revision without notifying anyone.
- Translation key `wiki.editor.markdown_help` in the seven locales; demo migration for the two new columns.

### Changed

- `WikiMarkdownConverter::markdownToHtml()` reads GitHub-flavoured Markdown (tables, task lists, strikethrough, autolinks); task lists come out in the Tiptap markup (`data-type="taskList"` / `taskItem`, `data-checked`) instead of `<input>` checkboxes. `htmlToMarkdown()` writes GFM tables, task lists (`- [x]`) and strikethrough (`~~`).
- Markdown revisions: search matches the Markdown source, export writes it as is, diffs compare Markdown lines, and imports keep the Markdown when `editor.type` is `markdown`.

## [1.10.0] - 2026-10-30

Wiki: UiKit page contract (scaffolds, value-free view models, dialogs); SEC-004 BR: hidden pages never listed, searched, exported or sent to AI, access_roles enforced, attachment downloads gated, allow_unauthenticated debug-only. See [UPGRADING](UPGRADING.md#to-1100).

Wiki: pages on the UiKit page contract (scaffolds, value-free view models, `<dialog>` confirm), canonical web_ui / security.roles keys, hidden pages filtered from lists and AI, CSRF and roles fail closed. See [UPGRADING](UPGRADING.md#unreleased).

### Added

- **UiKit page contract (REQ-UI-003):** every wiki page extends a UiKit scaffold — index, history and search the list scaffold; space, page view and diff the show scaffold; page edit and AI question the form scaffold. `Ui\WikiPageFactory` builds the view models (page headers with actions, breadcrumbs and back links, columns, value-free rows, row actions, `Pagination`, empty states) and `Ui\WikiPages` resolves the layout and the per-page templates. `docs/TEMPLATES.md` lists every page, view model and data block; `tests/Integration/Contract/PageContractTest.php` extends UiKit `PageContractTestCase` (17 fixtures × none / bootstrap5 / tailwind, strict missing-translation rule).
- Canonical configuration: `web_ui.path_prefix`, `web_ui.templates.<page>` and `security.roles.<action>` (`admin`, `list`, `create`, `edit`, `history`, `archive`, `ai`, `import`, `export`); `web_ui.enabled: false` now registers no route.
- **Archive** action on the page view (POST + CSRF behind the UiKit confirm dialog); Restore of a revision also asks for confirmation.
- `CsrfProtectionPass`: with the Web UI enabled the container refuses to compile without form CSRF protection or the CSRF token manager (fail closed); `symfony/security-csrf` is a direct requirement.
- Demo: UiKit host layout (`templates/layouts/admin.html.twig` + masthead), Bootstrap 5 form theme, migration for the 1.9 tables (`<prefix>_page_links`, `<prefix>_attachments`; the 1.9.0 demo answered 500 on page views without it), seeded revisions and an archived page; Playwright e2e for the space page, Archive and Restore confirmations; README screenshots of the page view, the space home and the history.
- Translation keys for the new labels and the FormKit convention keys of the page form (`wiki_page_form.*`), in the seven locales.

### Changed

- **`security.access_roles` is enforced as the general gate** of the default checker: every permission needs an access role and a role of that action (admin roles excepted). The container refuses to compile when `access_roles` and every action list are empty without an `access_checker`.
- Pages the user may not view (`WikiAction::View` per page) are left out of the sidebar tree (with their sub-pages), the archived list, search results, AI sources and the AI context (`WikiContextRetriever`, `WikiKnowledgeSearchTool`).
- Templates: no layout, CDN, framework class, local page header / pagination / flashes / modal partial or inline JS of their own; the AI question of the index is a UiKit `<dialog>`. `wiki.css` only lays out the tree, the two columns, the page body and the diff with the UiKit `--nowo-ui-*` tokens; `wiki.js` scrolls the current page of the tree into view.
- Form types carry no framework classes; flash messages are translated when they are added.
- `nowo-tech/ui-kit-bundle` ^2.17.
- CI: the coverage job runs `composer test-coverage-100` (fails below 100%).

### Security

- **Hidden pages no longer leak** (1.9.0 regression of the per-page `view` rule of a host `WikiSpaceAccessCheckerInterface`): they are left out of the sidebar tree (with their sub-pages; the truncation notice counts readable pages only), the archived list, search, **the space export ZIP**, AI sources, the AI context and `wiki_knowledge_search`. Space lists and the global AI question follow the per-space `view` / `ask_ai` decision.
- **`security.access_roles` is enforced** (it was inert in 1.0.0–1.9.0); attachment downloads need `canList()` and uploads `canEdit()` of the role checker.
- An explicit `~` on `security.roles.<action>` or a deprecated `<action>_roles` key means no role (it fell back to the default in the 1.10 development line).
- `security.allow_unauthenticated` and `web_ui.enabled` must be literal booleans; `allow_unauthenticated` is refused in the `prod` environment and in non-debug kernels. With the Web UI enabled the container refuses to compile without form CSRF protection.
- Restore checks the CSRF token before looking the revision up; `.gitattributes` keeps demo, tests and tooling out of the dist archives.
- REQ-SEC-004 re-audit 2026-10-30 (Appendix BR): see [SECURITY](SECURITY.md#req-sec-004).

### Deprecated

- `route_prefix`, `templates.*`, `web_ui.css_framework` and `security.<action>_roles` (see UPGRADING); a pre-1.10 layout without the UiKit canonical blocks is wrapped in a deprecated bridge.

### Removed

- `layout.html.twig`, `base.html.twig`, `manage/_page_header.html.twig`, `manage/_pagination.html.twig`, `manage/_flashes.html.twig`, `manage/_ai_ask_modal.html.twig`, `manage/_ai_ask_form_fields.html.twig`, `manage/_page_tree_list.html.twig`, the 1.9 page blocks and the `--nowo-wiki-*` tokens (Template contract in UPGRADING).

[1.10.0]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.10.0

## [1.9.0] - 2026-10-30

Wiki: space-aware access (404 / 403), tenant paths, restore / unarchive, events, page links to host objects, attachments and image upload, themable templates See [UPGRADING](UPGRADING.md#to-190).

### Added

- **Space- and page-aware access** (`WikiSpaceAccessCheckerInterface::isGranted(user, WikiAction, space, ?page)`, `WikiAction` enum: `view`, `create`, `edit`, `archive`, `history`, `restore`, `import`, `export`, `manage`, `ask_ai`). Every manage route asks it with the resolved space and page: a space or page the user may not view answers **404** (indistinguishable from a missing one), a denied write on a readable space answers **403**. Templates receive `permissions` (every action's decision). The default `LegacyWikiSpaceAccessChecker` keeps the 1.x user-level decision; override the alias to decide per tenant.
- **`WikiSpaceLocatorInterface`**: finds (or provisions on the first visit) the space a route slug names; the default keeps the 1.x lookup among the user's spaces.
- **Tenant paths** (`nowo_wiki.tenant_paths`, `tenant_only`): copies of every space-scoped route under host paths such as `/projects/{spaceSlug}/wiki`, each with a name prefix and a slug requirement, and a router decorator (`WikiTenantUrlGenerator`) that generates the tenant copy for a matching `spaceSlug`, so templates and redirects keep the bundle route names.
- **Restore a revision** (POST `page_restore`, CSRF `wiki_restore_<page id>`): appends a new revision with the old body; history stays append-only. **Unarchive** (POST `page_unarchive`) and an *Archived pages* list on the space page for users allowed to archive.
- **Events** `nowo_wiki.page.created`, `.unarchived`, `.restored`, `.linked`, `.unlinked`, `nowo_wiki.attachment.uploaded` / `.deleted` and `nowo_wiki.editor.options` (add TiptapEditorType options per space / page); `WikiPageSavedEvent` carries the previous revision and the actor, `WikiPageArchivedEvent` the actor.
- **Links from pages to host objects** (`WikiPageLink`: page, object type, object id, created by / at, unique triple; table `<prefix>_page_links`), `WikiPageLinkRepositoryInterface` (`findByHostObject`, batch `findByHostObjects`, `findByPage`), `WikiObjectLinkResolverInterface` (tag `nowo_wiki.object_link_resolver`, autoconfigured: one `resolveVisible()` call per type with every id) and `WikiPageLinkService` (`link`, `unlink`, `usedIn`). The page view shows **Used in** (only the objects the viewer may open) in a new `page_aside` block.
- **Attachments and image upload** (`nowo_wiki.attachments`, off by default): `WikiAttachment` (table `<prefix>_attachments`), `WikiAttachmentStorageInterface` (default: a private local directory), `WikiAttachmentScannerInterface` (default: accepts every file), `WikiAttachmentLimitsProviderInterface` (10 MiB per file, 500 MiB per space, an extension + sniffed MIME allowlist without SVG / HTML / XML / scripts), `WikiAttachmentService` (upload, delete, orphan and stray-file cleanup) and `WikiAttachmentController`: upload (POST, CSRF header `X-CSRF-Token`, asks `edit` on the page, 422 with a reason and nothing stored on refusal) and download (asks `view` on the attachment's own page, 404 for every denial, `Cache-Control: private, no-store`, `nosniff`, a sandboxing CSP, inline only for raster images). The page editor gets the TiptapEditorBundle upload hook when attachments are enabled.
- **`WikiAttachmentReferenceFilter`**: on save, an image of another space's (or an unknown) attachment is removed and a link to one loses its `href`, so a page never embeds a file its readers may not open.
- **Themeable manage templates** (FR-006): every structural element carries a semantic BEM class next to its Tabler / Bootstrap ones (`nowo-wiki`, `nowo-wiki--<view>`, `nowo-wiki-header`, `nowo-wiki-layout`, `nowo-wiki-sidebar`, `nowo-wiki-tree`, `nowo-wiki-page`, `nowo-wiki-content`, `nowo-wiki-actions`, `nowo-wiki-btn`, `nowo-wiki-panel`, `nowo-wiki-history`, `nowo-wiki-diff`, `nowo-wiki-search-form`, `nowo-wiki-results`, `nowo-wiki-form`, `nowo-wiki-pagination`, `nowo-wiki-flash`, `nowo-wiki-ai-modal`, `nowo-wiki-used-in`, `nowo-wiki-archived`, …). New overridable blocks: `wiki_header` (every page), `wiki_flashes`, `space_sidebar`, `space_sidebar_header`, `page_tree`, `page_main`, `page_header`, `page_actions`, `page_body` (page view), `space_main`, `space_search`, `space_import_export`, `space_archived` (space), `history_list`, `history_pagination`, `history_footer`, `diff_body`, `diff_footer`, `search_form`, `search_results`, `page_form`, `page_form_fields`, `page_form_actions`, `wiki_search_highlight`, `wiki_ai_highlight`, `wiki_ai_modal`, `space_list`, `ai_error`, `ai_answer`, `ai_sources`, `ai_follow_up`, `ai_form`; one tree item is the partial `manage/_page_tree_item.html.twig`.
- **Design tokens** in `wiki.css` (`--nowo-wiki-surface`, `-surface-muted`, `-border`, `-ink`, `-muted`, `-accent`, `-accent-ink`, `-accent-soft`, `-ai`, `-radius`, `-radius-sm`, `-gap`, `-gap-sm`, `-sidebar-width`, `-tree-indent`, `-font-mono`, `-line-height`, `-code-bg`, `-diff-*`, `-danger*`, `-warning*`, `-success*`, `-info-soft`, container widths) with a dark variant (`prefers-color-scheme: dark` unless `[data-theme="light"]`, and `[data-theme="dark"]`). When `web_ui.css_framework` is not `tabler` / `bootstrap*` the page root gets `data-nowo-wiki-skin="tokens"` and `wiki.css` draws the layout (sidebar + content, one column on phones), panels, buttons, forms and lists from the tokens; skin rules use `:where()`, so any host rule wins.
- **CSP nonce**: every `<script>` of the bundle templates (`wiki.js`, the Tiptap editor, the demo Bootstrap bundle) carries `nonce="…"` from the request attribute `csp_nonce` when the host sets it.

### Changed

- `WikiPageFormType` has an `editor_options` option (extra TiptapEditorType options); requires `nowo-tech/tiptap-editor-bundle` ^1.6 (upload and suggestion hooks).
- **Templates without inline styles or handlers**: `base.html.twig` no longer prints an inline `<style>` block (its rules moved to `wiki.css`; the body background now applies only to the bundle's own `layout.html.twig`), and the page tree indents with nested lists and capped depth classes (`nowo-wiki-tree__item--depth-0` … `-6`, `data-wiki-depth`) instead of `style="--depth: N"`. The current page is marked `aria-current="page"` in the tree. The index space cards and the search results are lists (`<ul>` / `<li>`), the page header is a `<header>`, the sidebars `<aside>` and the page an `<article>`. A test fails on any `style=` / `on*=` attribute, `<style>` tag or `<script>` without the nonce in the bundle views.

[1.9.0]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.9.0

## [1.8.1] - 2026-10-29

Wiki: routes declared with #[Route] attributes (configurable paths/names kept) See [UPGRADING](UPGRADING.md#to-181).

### Changed

- Routes are declared with `#[Route]` on `WikiManageController` (REQ-SF-004); `WikiRouteLoader` reads them and still applies the configured `nowo_wiki.routes.<key>` path / name and `route_prefix`. Methods and requirements are unchanged.

[1.8.1]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.8.1

## [1.8.0] - 2026-10-29

Wiki: pagination, PSR-3 logging, PHPStan level 8, tree export keeps grandchildren, AI timeout hierarchy See [UPGRADING](UPGRADING.md#to-180).

### Added

- `nowo_wiki.pagination` (`spaces_per_page` 24, `revisions_per_page` 25, `max_tree_pages` 500): the wiki index and the page history are paginated (`?page=N`, new `manage/_pagination.html.twig`), and the sidebar tree loads at most `max_tree_pages` pages with a notice linking to search (REQ-PERF-001).
- PSR-3 logging on the `nowo_wiki` channel: start / finish / failure of imports and exports, page archiving and every AI agent call — slugs, counters and lengths only, never content, questions, answers or secrets (REQ-OBS-001). `psr/log` is now a direct requirement.
- `make validate-translations` also checks the seven required locales and key parity against `en` (`scripts/check-translation-parity.php`, REQ-MAKE-004).
- Demo Caddyfiles set the REQ-RUNTIME-001 timeout hierarchy (PHP 60 s, Caddy write 75 s, FrankenPHP `max_wait_time` 30 s above the 30 s AI HTTP timeout); documented in CONFIGURATION / DEMO-FRANKENPHP.
- Translation keys `wiki.space.tree_truncated` and `wiki.pagination.*` in all seven locales.

### Changed

- `WikiPageRepositoryInterface::findActiveBySpace()` takes an optional `?int $limit`, `WikiPageRevisionRepositoryInterface::findByPage()` optional `?int $limit` / `int $offset` (see UPGRADING).
- `SymfonyAiWikiAssistant::ask()` throws `UnexpectedValueException` when the agent returns a non-text result instead of a `TypeError`.
- PHPStan raised from level 6 to level 8 (`ignoreErrors: []`); the last inline `@phpstan-ignore` was removed (`WikiAuthorResolver` now checks that `user_class` exists).
- `make help` lists every standard target in the REQ-MAKE-001 order; the demo `up` prints `Demo started at: http://localhost:<PORT>`.
- TypeScript coverage fails below 100% (Vitest `coverage.thresholds`); PHPDoc on every public class and method.
- Docs: Twig override subpath table and recovery procedure (REQ-TWIG-001), translation override procedure (REQ-I18N-001), SECURITY secrets / logging / limits sections and a complete 12.4.1 release checklist, PSR evaluation (PSR-3 adopted), `docs/COVERAGE.md`.

### Fixed

- The page tree (sidebar and Outline / Notion exports) dropped grandchildren and deeper pages; `WikiPageTreeBuilder` now builds the tree recursively.
- Space imports reject ZIP entries with absolute or `..` paths before extracting.

### Removed

- The legacy array-mapping branch of `WikiMetadataListener` (doctrine/orm ^3 only produces `AssociationMapping` objects) and coverage-ignored dead branches in the interchange iterators.

[1.8.0]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.8.0

## [1.7.2] - 2026-10-28

Wiki: the page editor loads its Tiptap script; the search results count is pluralised. See [UPGRADING](UPGRADING.md#to-172).

### Fixed

- **Page editor script.** `manage/page_edit.html.twig` resolved the Tiptap script without the `nowo_tiptap_editor`
  asset package, so it requested `/tiptap-editor.js` (404) and the editor never loaded.
- **Search results count.** `wiki.search.results_count` was an ICU message in non-ICU catalogues, printed raw
  (`{count, plural, …}`); all seven locales use Symfony's plural syntax now.

[1.7.2]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.7.2

## [1.7.1] - 2026-10-28

Security: import / export temp directories are 0700 and the export ZIP 0600. See [UPGRADING](UPGRADING.md#to-171).

### Security

- **Import / export temp files are owner-only.** Import and export working directories were created `0777` in the
  shared temp dir and the export ZIP with the default umask (usually world-readable): other local users could read
  exported private spaces until the download finished. Directories are now `0700` and the ZIP `0600`.

[1.7.1]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.7.1

## [1.7.0] - 2026-10-26

Forms in the bundle views are Symfony Form Types rendered with form_start / child loop / form_end, no raw form controls (REQ-TWIG-005). See [UPGRADING](UPGRADING.md#to-170).

### Changed

- Manage views render every form through Symfony Form Types (REQ-TWIG-005): search filters (`WikiSearchType`, GET, same `space` / `q` query names), the space ZIP import (`WikiImportType`, same field names and token id) and the AI question forms (`WikiAiAskType`, same field names and token id `wiki_ai_ask`); import and AI POSTs are validated through the Form. The page editor drops the hand-rolled top-level `_token`: `WikiPageFormType` is created with `csrf_token_id` `wiki_edit_<page id>` / `wiki_new_<space id>` and its own CSRF field is checked, so an invalid token now re-renders the form with an error (HTTP 422) instead of a 403. Template overrides must render the new form variables (see UPGRADING).

### Added

- `make check-no-raw-html-form` (REQ-TWIG-005) fails on raw `<form>` / `<input>` / `<textarea>` / `<select>` in bundle and demo Twig; it runs in `release-check`.

### Changed

- Developer tooling: `make release-check` runs the full REQ-MAKE-002 chain in order (`ensure-up` first, then `check-no-cursor-coauthor`, `check-open-prs`, `check-twig-extra`, `composer-sync`, `cs-fix`, `cs-check`, `rector-dry`, `phpstan`, `igor`, `validate-translations`, `test-coverage-100`, demo release-check, `test-ts`); `check-open-prs` also fails on conflicted pull requests (REQ-REL-003). `check-no-raw-html-form` (REQ-TWIG-005) is wired once the remaining raw form markup is migrated.
- Internal: applied the pending Rector suggestions (`readonly` on the final classes `WikiArchiveHelper`, `WikiHtmlSanitizer`, `EmbedIframeSrcAttributeSanitizer`; `instanceof` null check in `WikiTokenGuard`; `??=` in `WikiDocumentTreeReader`); no behaviour change. `make rector-dry` is clean again.
- Developer tooling: new `make rector` target applies the Rector refactorings (`make rector-dry` only reports them).

[1.7.0]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.7.0

## [1.6.4] - 2026-10-24

Bundle views render form bodies through the child loop (REQ-TWIG-003). See [UPGRADING](UPGRADING.md#to-164).

### Changed

- Dependencies refreshed (Composer and pnpm/npm lock files of the bundle and its demos, campaign 2026-10-19).
- `.php-cs-fixer.dist.php`: `new ParallelConfig(2)` (REQ-CS-001).
- Views: `manage/page_edit.html.twig` keeps the custom markup of the title and content fields (rule 3) and renders
  any remaining child through the child loop (`{% for child in form %}` + `not child.rendered` + `form_row(child)`),
  closing with `form_end(form)` instead of `render_rest: false`, per REQ-TWIG-003.
- Documentation: wave-1 REQ compliance — README preamble order, stars badge image and documentation list order, CONTRIBUTING Cursor Agent settings and post-release-commit trailer check, canonical bug-report template, demo `.env.example` / `.env.test` comments, DEMO-FRANKENPHP troubleshooting linked from `demo/README.md`, doc TOCs, Spec Kit inventory refresh (92/92).

[1.6.4]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.6.4

## [1.6.3] - 2026-10-18

Allows nowo-tech/ui-kit-bundle 2.x (^1.4 || ^2.2) so hosts can move to UiKit 2.2. See [UPGRADING](UPGRADING.md#to-163).

### Changed

- Allows nowo-tech/ui-kit-bundle 2.x (`^1.4 || ^2.2`); the dev lock and the demo lock use 2.2.0. Templates are unchanged (compatibility release). With UiKit 2.x `nowo-ui.css` ships in `@layer nowo-ui`, so unlayered host/framework CSS wins equal-specificity conflicts with kit rules in the wiki pages; to keep the 1.x cascade, override the `stylesheets` block of `base.html.twig` / `layout.html.twig` and link `css/nowo-ui.unlayered.css` (UiKit 2.x only).

[1.6.3]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.6.3

## [1.6.2] - 2026-10-13

### Changed

- `WikiExtension::load()` now loads `services.yaml` before its explicit definitions, so `NullWikiTeamMembershipResolver`, `WikiHtmlSanitizer` and the access checkers keep the definitions registered by the extension instead of being overwritten by the `Security/` resource scan. No runtime behaviour change (both services have no required arguments); a DI test asserts the final definitions and aliases.

[1.6.2]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.6.2

## [1.6.1] - 2026-10-13

### Fixed

- Space export (`GET /tools/wiki/spaces/{slug}/export`) no longer fails with a 500 "You cannot guess the mime type as the Mime component is not installed": the ZIP download now sets `Content-Type: application/zip` explicitly instead of relying on `symfony/mime` (not a dependency of the bundle) to guess it.

[1.6.1]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.6.1

## [1.6.0] - 2026-10-13

### Fixed

- Flash messages are now rendered by the bundle templates (new `manage/_flashes.html.twig`, included from `_page_header.html.twig` and `page_view.html.twig`): import errors, the import report and creation errors were previously added to the session but never shown.
- Search: LIKE wildcards in the query (`%`, `_`) now match literally on every platform (`ESCAPE '!'` clause with proper escaping); before, `50%` or `snake_case` found nothing on SQLite/PostgreSQL.
- Search excerpts are centred on the match when the text before it contains multibyte characters (`mb_stripos` instead of a byte offset).
- Templates use the `include()` function instead of the `{% include %}` tag so `composer twig:lint` passes.

### Deprecated

- `nowo_wiki.database.driver`, `nowo_wiki.database.platform` and `nowo_wiki.firewall` were never read; they now trigger a deprecation and will be removed in 2.0.

### Changed

- Development: vitest and `@vitest/coverage-v8` upgraded to ^4.1.11.
- Docs: `UPGRADING.md` no longer repeats the "From 1.3.3 to 1.3.4" section or a stray `# Upgrading` heading; the 1.3.0 Twig notes are merged into the "To 1.3.0" section.

[1.6.0]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.6.0

## [1.5.0] - 2026-10-13

### Security

- `WikiHtmlSanitizer` is now built on `symfony/html-sanitizer` (DOM parsing, explicit element/attribute allowlist). The former regex/`strip_tags` implementation could be bypassed (stored XSS) with unquoted event handlers (`<img src=x onerror=…>`), unclosed `<iframe>`, `<iframe srcdoc>`, unquoted or entity-encoded `javascript:` URLs and `style` attributes. Links accept only http/https/mailto (or relative) URLs, media only http/https (or relative), `style` only a plain `text-align`, and iframes only an https `src` on the embed host allowlist.
- Page bodies are re-sanitized at render time: `manage/page_view.html.twig` uses the new `nowo_wiki_safe_html` Twig filter instead of `|raw`, so revisions stored before this fix are cleaned when displayed.
- ZIP imports are rejected before extraction when they contain more than 10 000 entries or more than 512 MiB of declared uncompressed data (ZIP bomb protection; limits are `WikiArchiveHelper` constructor arguments).

### Added

- `symfony/html-sanitizer` is now a runtime dependency.
- `nowo_wiki_safe_html` Twig filter for rendering stored wiki HTML in custom templates.

[1.5.0]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.5.0

## [1.4.1] - 2026-10-13

### Changed

- Development: `composer.json` pins `config.platform.php` to 8.2.0 so the committed lock stays installable on the minimum PHP; CI overrides the platform per matrix cell.
- Repository: `.twig-cs-fixer.cache` is now git-ignored.

### Dependencies

- `nowo-tech/form-kit-bundle` 2.6.1 → 2.7.1 (development lock; #54).
- `nowo-tech/ui-kit-bundle` 1.9.2 → 1.11.0 (development lock; #55).
- `doctrine/orm` 3.7.4 → 3.7.5 (development lock; #56).
- `jsdom` 30.1.1 → 30.1.2 (frontend tooling; #57).
- `vite` 8.3.2 → 8.3.4 (frontend tooling; #58).

[1.4.1]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.4.1

## [1.4.0] - 2026-10-09

### Changed

- **Doctrine ORM SortDirection:** the Doctrine ORM repositories (`DoctrineOrmWikiPageRepository`, `DoctrineOrmWikiPageRevisionRepository`, `DoctrineOrmWikiSpaceRepository`) pass `\SortDirection::Ascending`/`Descending` to QueryBuilder `orderBy()`/`addOrderBy()` instead of `'ASC'`/`'DESC'` (doctrine/orm deprecation, https://github.com/doctrine/orm/issues/11313).
- **Requirement:** `doctrine/orm` is now `^3.7` (was `^2.15 || ^3.0`).

### Fixed

- Tests: `WikiRepositoryWorkerTest` enables Doctrine native lazy objects only on PHP 8.4+, keeping the worker integration test green across the CI matrix.

### Dependencies

- Runtime lock: `doctrine/orm` 3.7.4, `doctrine/dbal` 4.5.0, `nowo-tech/form-kit-bundle` 2.6.1, `nowo-tech/ui-kit-bundle` 1.9.2, `nowo-tech/tiptap-editor-bundle` 1.3.10, `league/commonmark` 2.10.3, `league/html-to-markdown` 5.1.3 (Dependabot #42–#52 + update).
- Dev tooling: `igor-php/igor-php` ^0.10 (v0.10.1), `nowo-tech/phpstan-frankenphp` v1.2.3, `phpstan/phpstan` 2.3.1, `phpstan/phpstan-phpunit` 2.1.1, `phpstan/phpstan-symfony` 2.1.0, `rector/rector` 2.7.0, `phpunit/phpunit` 10.5.66, `friendsofphp/php-cs-fixer` 3.95.27.
- JS dev: Vite 8.3.2, jsdom 30.1.1.
- Demo (Symfony 8): same runtime bumps plus `doctrine/migrations` 3.9.8, `twig/twig` v3.30.0, `twig/extra-bundle` v3.29.0, `nowo-tech/hot-reload-bundle` v1.5.5, `nowo-tech/twig-inspector-bundle` v1.1.7.

[1.4.0]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.4.0

## [1.3.5] - 2026-09-27

### Fixed

- **FrankenPHP worker (no kernel reset):** repositories and `WikiSearchService` resolve the entity manager per call through `WikiEntityManagerProvider`, resetting a manager closed by a failed flush in an earlier request; page/space reads refresh from the database so changes by other workers are not served stale.
- **Security (worker):** new `WikiTokenGuard`; the controller, `ConfigurableWikiAccessChecker` and `WikiKnowledgeSearchTool` ignore a token left by a previous request when the current request is not behind a secured firewall.
- **Export:** a failed space export removes the temporary ZIP before rethrowing.

### Added

- **REQ-CS-008:** `igor-php/igor-php` (require-dev only), root `igor.json`, Composer/`Makefile` `igor` target, and `release-check` wiring for FrankenPHP worker-state audit.
- `ai.http_timeout` (default 30 s) and the `nowo_wiki.ai.http_client` service (timeout + max duration) to set as `http_client` of the AI platform.

### Changed

- **Worker safety (Igor):** justified `// @igor-ignore` annotations and/or `ResetInterface` / request-scoped fixes so `make igor` passes on package `src/`.
- Repository and `WikiSearchService` constructors accept `EntityManagerInterface|WikiEntityManagerProvider`; controller, access checker and AI tool accept an optional `WikiTokenGuard`. See [UPGRADING](UPGRADING.md) and [FRANKENPHP-WORKER-AUDIT](FRANKENPHP-WORKER-AUDIT.md).

[1.3.5]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.3.5

## [1.3.4] - 2026-08-24

### Changed

- **Demos:** MySQL env policy in FrankenPHP stack (REQ-DEMO-011).
- **QA:** add `phpstan-frankenphp` extension (REQ-CS-005).
- **Assets:** English JSDoc on TypeScript sources (REQ-ASSETS-002).
- **Docs:** PHP-FIG PSR evaluation (REQ-CS-007).
- **Style:** PHP CS Fixer alignment.

### Notes

- **No API or configuration changes** for integrators unless noted above.

[1.3.4]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.3.4

## [1.3.3] - 2026-08-19

### Changed

- Code style only (PHP CS Fixer). **No application upgrade steps.**

[1.3.3]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.3.3

## [1.3.2] - 2026-08-18

### Changed

- **Demos:** pin `nowo-tech/hot-reload-bundle` to `^1.4` with FrankenPHP Mercure/`hot_reload` (`dev`/`test` only).

[1.3.2]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.3.2

## [1.3.1] - 2026-08-07

### Fixed

- **CI / FormKit:** `WikiControllerContainerBuilder` injects `FormOptionsMerger` via `FormKitTestSupport::withMerger()` so controller unit tests no longer hit uninitialized `$formOptionsMerger` on `WikiPageFormType`.

[1.3.1]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.3.1

## [1.3.0] - 2026-08-04

### Changed

- **FormKitBundle:** depend on [`nowo-tech/form-kit-bundle`](https://github.com/nowo-tech/FormKitBundle) ^2.0. Admin form types use `FormOptionsTrait` + profile `wiki` (`#[FormKitConfig]`). Extension prepends that profile when missing; form types are tagged `form.type` so `FormOptionsMerger` is injected.

### Added
- **REQ-TWIG-004:** require `twig/extra-bundle` + `twig/string-extra`; `make check-twig-extra` in `release-check`; demos register `TwigExtraBundle`.
- **Twig-CS-Fixer:** `vincentlanglet/twig-cs-fixer`, `.twig-cs-fixer.php`, `composer twig:lint` / `twig:fix`.

### Changed

- **REQ-UI-001-kit:** Requires `nowo-tech/ui-kit-bundle` `^1.4`. Layout/base load `asset('css/nowo-ui.css', 'nowo_ui_kit')` and import `@NowoUiKitBundle/macros/ui.html.twig`. Extension seeds `nowo_ui_kit` from `web_ui.css_framework` when the host has not configured UiKit. Demo registers `NowoUiKitBundle` + `nowo_ui_kit.yaml`.

[1.3.0]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.3.0

## [1.2.0] - 2026-08-03

### Added

- **REQ-UI-002:** `security.allow_unauthenticated` (default `false`) with `AllowAllWikiAccessChecker`, compile-time SecurityBundle guard when the manage Web UI is enabled, and controller soft-gate (no hard `#[IsGranted('IS_AUTHENTICATED')]`).
- Docs: CONFIGURATION / SECURITY host firewall example; Flex recipe comments for `access_roles` / `allow_unauthenticated`.

### Changed

- Demo: resolve TipTap from Packagist (no sibling Docker mount); FrankenPHP docs/README aligned.
- Dev deps: `actions/stale` v11, jsdom 30.0.1, vite 8.2.0; TipTap / Rector lock refresh.

### Compatibility

- PHP `>=8.2 <8.6`; Symfony `^7.4 || ^8.0` (see `composer.json`).
- Manage UI with default security settings requires **SecurityBundle** (or set `allow_unauthenticated: true` for trusted local demos only).

### Upgrade

```bash
composer require nowo-tech/wiki-bundle:^1.2
```

See [UPGRADING.md](UPGRADING.md).

## [1.1.1] - 2026-07-31

### Fixed

- **CI / Vitest** — TypeScript job uses Node.js **22** so `jsdom@30` / `undici@8` can load (`markAsUncloneable`); Node 20 failed `pnpm run test:coverage`.
- **Demo** — `release-check` / `update-bundle` start the FrankenPHP container via `ensure-up` before Composer/exec.

### Tests

- Unit coverage for `web_ui.layout_template` → `nowo_wiki.templates.layout` and Twig `nowo_wiki_web_ui` prepend (restores **100%** PHP line coverage).

### Upgrade

```bash
composer require nowo-tech/wiki-bundle:^1.1.1
```

No application or config API changes. See [UPGRADING.md](UPGRADING.md).

## [1.1.0] - 2026-07-30

### Added

- Intermediate shell **`base.html.twig`**: manage pages extend it; stacks `wiki.css` / `wiki.js` with **`{{ parent() }}`** so host layouts keep their assets (REQ-UI-001).

### Changed

- Manage templates extend **`@NowoWikiBundle/base.html.twig`** instead of `layout` directly.
- Default **`layout.html.twig`**: package assets moved into `base`; CDN gated by `web_ui.css_framework` (Tabler/Bootstrap-compatible).

### Documentation

- **[USAGE](USAGE.md)** / **[CONFIGURATION](CONFIGURATION.md):** host layout preference, freeze rule, `base` shell.
- **[UPGRADING](UPGRADING.md)** section **To 1.1.0**.

## [1.0.5] - 2026-07-29

### Added

- FrankenPHP Friendly Worker Mode banner (REQ-DOCS-017); `make check-open-prs` / `demo-smoke`.
- `SYMFONY_DEPRECATIONS_HELPER=max[direct]=0` (REQ-SF-005).
- `nowo_wiki.web_ui` (`css_framework`, `layout_template`) + `nowo-ui-*` layout hooks (REQ-UI-001).
- GitHub About topics/homepage; optional GH workflows (stale, pr-lint, copilot-instructions).

### Changed

- Demo FrankenPHP image **PHP 8.5**; PHPStan level **8** with empty `ignoreErrors` (REQ-CS-005/006).
- README Documentation order and badge order (REQ-DOCS-002/004).
- Packagist keywords include `php` and `frankenphp`.

## [1.0.4] - 2026-07-22

### Added

- **Demo** — `FRANKENPHP_MODE` (`worker` default, or `classic`) selects FrankenPHP Caddyfile at container start; documented in [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md).

### Changed

- **Dependencies** — `nowo-tech/tiptap-editor-bundle` lockfile bump to `1.2.0` (constraint remains `^1.0`); dev `jsdom` to `29.1.1`.
- **REQ-GIT-001** — Co-author check uses `git --no-replace-objects`; strip script refuses dirty working trees; [GITHUB_CI.md](GITHUB_CI.md) expanded as canonical adoption doc.
- **Tooling** — PHP-CS-Fixer `fully_qualified_strict_types.import_symbols` enabled (import + short class names).

## [1.0.3] - 2026-07-16

### Fixed

- **CI / Doctrine** — `WikiMetadataListenerDoctrineTest` loads attribute metadata via `AttributeDriver` (no `EntityManager` / LazyGhost), fixing PHPUnit failures on PHP 8.4+ in GitHub Actions.
- **CI matrix** — Drop Symfony 7.0 (unsupported; `composer.json` requires `^7.4 || ^8.0`); coverage jobs use Symfony 7.4.

### Added

- **`symfony/var-exporter`** (`require-dev`) — available for Doctrine ORM proxy / LazyGhost scenarios in local and CI installs.

### Changed

- **CI** — Full `composer update --with-all-dependencies` in the test matrix; Dependabot bumps (`actions/checkout` v7, `actions/setup-node` v7, Vite, TypeScript).

## [1.0.2] - 2026-07-16

### Changed

- **Docs** — Rename `docs/GITLAB_CI.md` to `docs/GITHUB_CI.md` (GitHub Actions wording); update README and CONTRIBUTING links.

## [1.0.1] - 2026-07-16

### Fixed

- **i18n** — Complete missing `wiki.editor.help` and `wiki.ai.follow_up` keys in `de`, `fr`, `it`, `nl`, and `pt` translation files (parity with English/Spanish).

### Added

- **Code of Conduct** — Contributor Covenant (`CODE_OF_CONDUCT.md`).
- **Git hygiene (REQ-GIT-001)** — `.githooks/commit-msg`, `make setup-hooks`, `make check-no-cursor-coauthor`, CI job to reject Cursor co-author trailers.
- **Docs** — `docs/GITLAB_CI.md`; links from README and CONTRIBUTING; release checklist note to re-check co-author trailers before push.

### Changed

- **`make release-check`** — Runs `check-no-cursor-coauthor` first.

## [1.0.0] - 2026-07-13

### Added

- **Core wiki** — spaces (team/user scope), page tree, immutable revisions, diff, search.
- **Tiptap integration** — `WikiPageFormType` + `nowo-tech/tiptap-editor-bundle` (Notion variant).
- **Import / export** — Outline and Notion Markdown interchange (UI + `wiki:import` / `wiki:export` console commands, ZIP support).
- **Symfony AI (optional)** — `/tools/wiki/ask`, `WikiKnowledgeSearchTool`, context injection via `symfony/ai-bundle`.
- **Security** — `WikiAccessCheckerInterface`, `WikiSpaceAccessResolver`, CSRF on POST, `WikiHtmlSanitizer`, role-based import/export ACL.
- **Symfony** — dynamic routes, Twig overrides, Flex recipe `1.0.0`, Doctrine metadata listener.
- **i18n** — `NowoWikiBundle` translations (`en`, `es`, `fr`, `de`, `it`, `pt`, `nl`).
- **Tests** — 230 PHPUnit tests, 100% PHP coverage; Vitest for `wiki.ts`.
- **CI** — GitHub Actions (PHPUnit, PHPStan, Vitest, `composer audit`).
- **Demo** — Symfony 8 + FrankenPHP on port **8025** with auto-login fixtures.

### Security

- HTML sanitized on every revision save; see [SECURITY.md](SECURITY.md).

[Unreleased]: https://github.com/nowo-tech/WikiBundle/compare/v1.10.0...HEAD
[1.2.0]: https://github.com/nowo-tech/WikiBundle/compare/v1.1.1...v1.2.0
[1.1.1]: https://github.com/nowo-tech/WikiBundle/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/nowo-tech/WikiBundle/compare/v1.0.5...v1.1.0
[1.0.5]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.0.5
[1.0.4]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.0.4
[1.0.3]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.0.3
[1.0.2]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.0.2
[1.0.1]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.0.1
[1.0.0]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.0.0
