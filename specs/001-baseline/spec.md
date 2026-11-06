# Feature Specification: WikiBundle baseline (100% code coverage)

**Feature Branch**: `001-baseline`  
**Created**: 2026-07-07  
**Status**: Active  
**Input**: Backfill GitHub Spec Kit baseline documenting 100% of production code in `src/`.

**Related docs**: [`docs/SPEC-DRIVEN-DEVELOPMENT.md`](../../docs/SPEC-DRIVEN-DEVELOPMENT.md), [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md), [`docs/USAGE.md`](../../docs/USAGE.md)  
**Code inventory (traceability)**: [`code-inventory.md`](code-inventory.md)

---

## Summary

**Package**: `nowo-tech/wiki-bundle`  
**Configuration root**: `nowo_wiki`


Symfony bundle for a **versionable team wiki**: spaces, page tree, Tiptap or Milkdown editor (Markdown storage, optional co-editing over Mercure), revision history/diff, search, import/export, attachments, links to host objects, optional trash, comments and Symfony AI assistant, and pluggable space- and page-aware ACL. Optional features are off by default.

---

## User Scenarios & Testing

### US-01 — Browse spaces and page tree (Priority: P1)

As a team member, I navigate spaces and hierarchical pages.

**Acceptance**: `WikiSpaceService`, `WikiPageTreeBuilder`, manage index/space templates.

### US-02 — Edit pages with revisions (Priority: P1)

As an editor, I save Tiptap HTML; each save appends an immutable revision.

**Acceptance**: `WikiPageService`, `WikiPageRevision` entity, sanitized via `WikiHtmlSanitizer`.

### US-03 — History and diff (Priority: P1)

As a reader, I compare any two revisions.

**Acceptance**: `WikiRevisionDiffService`, history/diff views.

### US-04 — Search (Priority: P2)

As a user, I full-text search within a space.

**Acceptance**: `WikiSearchService`, search template.

### US-05 — Import/export interchange (Priority: P2)

As a maintainer, I import/export markdown/Obsidian-style archives via CLI.

**Acceptance**: `WikiImportCommand`, `WikiExportCommand`, `Interchange/*` services.

### US-06 — AI assistant (Priority: P3)

As a user, I ask questions grounded in wiki content when Symfony AI is configured.

**Acceptance**: `SymfonyAiWikiAssistant`, `WikiKnowledgeSearchTool`, null fallback.

### US-07 — Attach files to a page (Priority: P2)

As an editor, I upload a file to a page; as a reader, I download it.

**Acceptance**: Given `attachments.enabled`, when a file passes the limits, allowlist and scanner, then it is stored privately; when a viewer may not read the attachment's page, then the download answers 404. `WikiAttachmentService`, `WikiAttachmentController`.

### US-08 — Discuss a page (Priority: P2)

As a viewer with the comment rights, I comment in a side panel, and a moderator deletes threads.

**Acceptance**: Given `comments.enabled` and `WikiAction::ViewComments`, then the toggle and the panel appear; without the right, they do not and the endpoints answer 403. A write over a cap answers 422 and over the rate limit 429. `WikiCommentService`, `WikiCommentController`, `WikiCommentsPanel`.

### US-09 — Fit the wiki into a host product (Priority: P2)

As an integrator, I decide the plan limits, navigation, page parts and assets without forking templates.

**Acceptance**: Given a listener on `nowo_wiki.quota.check` that denies, when a user uploads, then the answer is 422 with the reason and nothing is stored. Given no option set, then pages, routes and responses are as before. `WikiQuotaGate`, `WikiSearchScopeListener`, `web_ui.*` options.

---

## Requirements

### Core wiki

- **FR-BUNDLE-001 / FR-CFG-001 / FR-CFG-002**: Bundle, config (routes, ACL, AI), extension.
- **FR-DI-001 / FR-DI-002**: Service wiring (`services.yaml`); compiler pass registering the Twig paths.
- **FR-DB-001**: Doctrine integration: table prefix and metadata listener.
- **FR-DTO-001 / FR-MDL-001 / FR-UTIL-001**: Transfer objects; domain enums; slug and UUID utilities.
- **FR-PAGE-002**: Author display name of revisions.
- **FR-ENTITY-001 / FR-REPO-001 / FR-REPO-002**: Spaces, pages, revisions; ORM repositories.
- **FR-PAGE-001 / FR-SPACE-001 / FR-TREE-001 / FR-REV-001 / FR-SEARCH-001**: Page/space CRUD, tree, diff, search services.
- **FR-SEC-001 / FR-SEC-005 / FR-SEC-006**: Access checker, HTML sanitizer, team membership resolver.
- **FR-SEC-003**: CSRF: `WikiCsrfTrait` on every mutation, and the container refuses to compile the Web UI without form CSRF protection (`CsrfProtectionPass`).
- **FR-SEC-010** (1.9): `WikiSpaceAccessCheckerInterface` decides each `WikiAction` on a space and page; `WikiSpaceGuard` answers 404 for a missing or unreadable space and 403 for a denied action; `WikiSpaceLocatorInterface` resolves the slug.
- **FR-SEC-007**: Attribute-level sanitizers keep only safe attributes: `EmbedIframeSrcAttributeSanitizer` (`<iframe src>` only for https hosts on the embed allowlist) and `TextAlignStyleAttributeSanitizer` (`style` only when it is exactly a Tiptap TextAlign declaration).
- **FR-SEC-009** (1.13.2): one read rule for a page — `View` on the page and on every ancestor (`WikiPageVisibility`, `WikiSpaceGuard::isPageReadable()` / `requireReadablePage()`), applied to the page view and every page route, attachments, co-editing, search and AI hits, as in the tree, the export and the page-link resolver; a parent cycle reads as hidden.
- **FR-SEC-008**: `WikiHtmlTwigExtension` (`nowo_wiki_safe_html`) re-sanitizes stored page HTML at render time, so revisions saved under an older policy are rendered with the current one.
- **FR-WORKER-001**: Safe without kernel / `services_resetter` reset (FrankenPHP worker): `WikiEntityManagerProvider` resolves the configured entity manager on every call and resets a closed one; `WikiTokenGuard` refuses to trust a token left in storage by a previous request. `AllowAllWikiAccessChecker` (FR-SEC-001) is only used when `security.allow_unauthenticated` is true (demo/dev).
- **FR-FORM-001**: Form types of the pages, search, AI question, import, comments and POST actions (no raw `<form>`).
- **FR-EVT-001 / FR-EVT-002**: Domain events for list/access and page lifecycle.
- **FR-SEARCH-003** (1.16): `content_text`, the plain text search matches and excerpts show (`WikiPlainText`, `wiki:search:backfill-text`).
- **FR-EVT-003**: `nowo_wiki.quota.check` (1.21) before a new page, unarchive, trash restore, import or attachment upload; a listener refuses with a plain-text reason; no listener, no change.
- **FR-SEARCH-002**: `search.scope: space` (1.21): the search page answers inside one space (404 without a valid `?space=`, request attribute `spaceSlug`); `web_ui.space_index: false` does not register the index route.
- **FR-UI-012**: Host hooks of the pages (1.21), all opt-in: `web_ui.slots` and blocks `wiki_sidebar` / `wiki_space_intro` / `wiki_page_header_extra`, `web_ui.import_export: dialog`, `web_ui.assets`, `comments.moderation_panel`, `comments.error_notices`, `--nowo-wiki-tree-*` tokens.

### Attachments, links and files

- **FR-ATT-001** (1.9): Page attachments: private storage under server-generated keys, limits per space, type allowlist, pluggable scanner; downloads authorized on the attachment's own page.
- **FR-ATT-002** (1.9): A page body keeps attachment URLs only of its own space (`WikiAttachmentReferenceFilter`).
- **FR-LINK-001** (1.9): Links from pages to host objects, described and authorized by tagged resolvers; "Used in" lists only what the viewer may open.
- **FR-FILES-001** (1.20, `page_files`, opt-in): *Files* card of the page view and `nowo_wiki.page.files_purged` on purge, carrying untrusted URLs.

### Comments

- **FR-COMMENT-001** (1.16, `comments.enabled`, opt-in): Threads, comments and reactions behind the `page_comments` endpoints: rights checked on every call, same-origin and CSRF on writes, caps (422) and rate limit (429), deleted with their page.
- **FR-COMMENT-002** (1.16): The panel: UiKit side panel, Milkdown thread component or the no-JavaScript list and form.
- **FR-COMMENT-003** (1.16.1, 1.17): Privacy: export and erasure of an author, erasure of mentions, `wiki:comments:erase-user`.
- **FR-PANEL-001** (1.16): Open / closed state of side panels per user (`WikiPanelStateStoreInterface`, session by default).
- **FR-AVATAR-001** (1.16): Pictures of authors through a host resolver, asked only for real names shown.

### Interchange & AI

- **FR-IX-001**: Archive import/export pipeline (front matter, markdown, format detection).
- **FR-MD-001** (1.11): Markdown storage — `content_markdown` + `format`, the extensible league/commonmark renderer (`WikiMarkdownRenderer`, `WikiMarkdownExtensionInterface`), GFM task-list normalisation, page references (`WikiMarkdownReferenceExtractor`), `wiki:convert-markdown`.
- **FR-MD-002** (1.13): Milkdown ↔ wiki bridge (`editor.type: milkdown`) — `MilkdownWikiMarkdownRenderer` (the Milkdown dialect under the `nowo_milkdown_editor.markdown` limits; viewer-neutral cache, per-viewer page view), `WikiMilkdownPageResolver`, icon placeholder + hydration after sanitizing, read-side rich content (`WikiMilkdownRichContent`, `nowo_wiki_rich_content_assets()`, `wiki.js`), references from the renderer's parser (pages, mentions), dialect HTML → Markdown converters, portable page links in exports, `MilkdownBridgePass` (fail closed).
- **FR-MD-004** (1.13): co-editing of Milkdown pages over Mercure — `WikiCollabController` (token, state, update, awareness, compact; edit permission on every request, same-origin JSON, size and rate limits), `WikiCollabService`, the Yjs log store (`WikiCollabStateStoreInterface`, Doctrine default, client compaction), Mercure publisher / subscriber cookie, seeding and the "replacement wins" reset (`WikiCollabSaveListener`).
- **FR-TREE-001** (1.14, `tree.enabled`, opt-in): Outline-style page tree — the sidebar renders the UiKit `tree` partial (WAI-ARIA treeview, current branch unfolded, fold state per user and space from `WikiTreeStateStoreInterface`, session default), moves by drag, Alt+arrows (`wiki.js` glue) and the Move form (`WikiPageMoveType`); `WikiTreeController` (`move`, `state`: same-origin JSON, CSRF per space, bounded bodies), `WikiPageMover` (same space, no cycles, `tree.max_depth`, edit rights on the page and both parents, one space-locked transaction through `WikiPageTreeWriterInterface`, sparse positions) and `nowo_wiki.page.moved` (`WikiPageMovedEvent`). Without the tree no route is registered and output is unchanged. Decisions (user, 2026-11-03): moves stay within one space (D-W1), depth 10 (D-W2), sparse integer `position` ordering (D-W27), fold state in the session with `tree.state_store` as the per-user option (D-W28).
- **FR-TRASH-001** (1.14, `trash.enabled`, opt-in): the trash — `WikiTrashController` (Delete behind a confirm, the trash list, Restore, Purge after a confirmation page and a second confirm), `WikiTrashService` (a deletion archives the page and its sub-tree in one batch under the space lock; restore together; purge deletes pages, revisions, links and attachments; retention via `wiki:trash:purge`), `WikiTrashEntry` / `WikiTrashRepositoryInterface` (`<prefix>_page_trash`, left out of the schema when off), `WikiAction::Delete` / `Purge` (never in `grants()`), `nowo_wiki.page.trashed` / `trash.restored` / `trash.purged`. Decisions (user, 2026-11-03): a delete takes the whole sub-tree and a restore brings it back together (D-W29); pages archived before stay archived (D-W30); a restored page whose parent is gone comes back at the top level (D-W31).
- **FR-HIST-001** (1.15, `history.timeline`, opt-in): the history timeline — `WikiHistoryTimeline` (sessions: same author, `history.session_minutes`, a named version is its own session), `WikiAuthorLabel` (per-page HMAC "Editor xxxx", safe display names with `history.show_names`), named versions (`WikiRevisionLabel`, `WikiRevisionLabelRepositoryInterface`, `<prefix>_revision_labels`, left out of the schema when off), `WikiHistoryController` (*Save version*: `canEdit()` + `WikiAction::Edit`; *Copy to new page*: `WikiAction::History` on the page, `canCreate()` + `WikiAction::Create` on the space; POST + CSRF; 404 for unreadable pages) and the retention `WikiHistoryThinner` / `wiki:history:thin` (one revision per session and day after `history.retention_days`; named, current and newest revisions kept). Decisions: retention (D-W6), named versions for every editor (D-W7). The server-rendered diff follows with Milkdown 1.6.
- **FR-MD-003** (1.13): `wiki:convert-markdown` scope (`--page`) and fidelity report (`WikiConversionFidelityAnalyzer`, JSON `nowo-wiki-conversion-report` v1, `--skip-lossy`, `--fail-on-loss`); history is never rewritten and the command is idempotent.
- **FR-AI-001–005**: AI assistant interface, Symfony AI impl, knowledge tool, context retriever, unavailable handling.

### UI & CLI

- **FR-VIEW-001 / FR-UI-001**: UiKit page plumbing (layout, per-page templates, tab row) and the manage controller.
- **FR-VIEW-005 / FR-UI-010–011**: Manage UI + Tiptap editor assets.
- **FR-BUILD-001**: `Resources/public/wiki.js` and `wiki.css` are build outputs of `Resources/assets/src/` (`make assets`).
- **FR-CLI-001**: Import/export commands.
- **FR-ROUTE-001**: Configurable route loader.
- **FR-ROUTE-002** (1.9): `tenant_paths`: copies of the space-scoped routes under host paths, and a router decorator that generates them.
- **FR-I18N-004**: Seven locale files.

---

## Success Criteria

- **SC-001**: 100% of production files in `src/` appear in [`code-inventory.md`](code-inventory.md) with requirement IDs (256/256 mapped; the co-located Vitest files `*.test.ts` are excluded).
- **SC-002**: Configuration keys in `docs/CONFIGURATION.md` match `Configuration.php`.
- **SC-003**: `composer qa` / `make release-check` pass in CI (PHPUnit, PHPStan, Vitest where applicable).
- **SC-004**: No Packagist-visible behavior change without spec, inventory, and test updates.

---

## Validation

| Check | Command |
| --- | --- |
| Full QA | `make release-check` or `composer qa` |
| Code inventory audit | `find src -type f ! -path '*/assets/dist/*' ! -name '*.test.ts' \| wc -l` |
| TS tests | `pnpm test` or `make test-ts` (when assets present) |

When changing behavior, update this spec, `code-inventory.md`, integrator docs, and tests.
