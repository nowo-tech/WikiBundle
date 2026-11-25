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

As a maintainer, I import/export Markdown archives via CLI.

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
- **FR-SEC-010** (1.9; 2.0: the host implements `WikiSpaceAccessPolicyInterface` / `WikiSpaceIdLocatorInterface`, the bundle asks the `@internal` `Port\Internal\` interfaces of the 1.x names): `WikiSpaceAccessCheckerInterface` decides each `WikiAction` on a space and page; `WikiSpaceGuard` answers 404 for a missing or unreadable space and 403 for a denied action; `WikiSpaceLocatorInterface` resolves the slug.
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
- **FR-PANEL-001** (1.16): Open / closed state of side panels per user (`WikiPanelStatesInterface` since 1.36, the only host port in 2.0; session by default).
- **FR-AVATAR-001** (1.16): Pictures of authors through a host resolver, asked only for real names shown.

### Interchange & AI

- **FR-IX-001**: Archive import/export pipeline (front matter, markdown, format detection).
- **FR-MD-001** (1.11): Markdown storage — `content_markdown` + `format`, the extensible league/commonmark renderer (`WikiMarkdownRenderer`, `WikiMarkdownExtensionInterface`), GFM task-list normalisation, page references (`WikiMarkdownReferenceExtractor`), `wiki:convert-markdown`.
- **FR-MD-002** (1.13): Milkdown ↔ wiki bridge (`editor.type: milkdown`) — `MilkdownWikiMarkdownRenderer` (the Milkdown dialect under the `nowo_milkdown_editor.markdown` limits; viewer-neutral cache, per-viewer page view), `WikiMilkdownPageResolver`, icon placeholder + hydration after sanitizing, read-side rich content (`WikiMilkdownRichContent`, `nowo_wiki_rich_content_assets()`, `wiki.js`), references from the renderer's parser (pages, mentions), dialect HTML → Markdown converters, portable page links in exports, `MilkdownBridgePass` (fail closed).
- **FR-MD-004** (1.13): co-editing of Milkdown pages over Mercure — `WikiCollabController` (token, state, update, awareness, compact; edit permission on every request, same-origin JSON, size and rate limits), `WikiCollabService`, the Yjs log store (`WikiCollabStateStoreInterface`, Doctrine default, client compaction), Mercure publisher / subscriber cookie, seeding and the "replacement wins" reset (`WikiCollabSaveListener`).
- **FR-TREE-001** (1.14, `tree.enabled`, opt-in): Collapsible page tree — the sidebar renders the UiKit `tree` partial (WAI-ARIA treeview, current branch unfolded, fold state per user and space from `WikiTreeStateStoreInterface`, session default), moves by drag, Alt+arrows (`wiki.js` glue) and the Move form (`WikiPageMoveType`); `WikiTreeController` (`move`, `state`: same-origin JSON, CSRF per space, bounded bodies), `WikiPageMover` (same space, no cycles, `tree.max_depth`, edit rights on the page and both parents, one space-locked transaction through `WikiPageTreeWriterInterface`, sparse positions) and `nowo_wiki.page.moved` (`WikiPageMovedEvent`). Without the tree no route is registered and output is unchanged. Decisions (user, 2026-11-03): moves stay within one space (D-W1), depth 10 (D-W2), sparse integer `position` ordering (D-W27), fold state in the session with `tree.state_store` as the per-user option (D-W28).
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

### Releases 1.22 to 1.40

- **FR-COLLAB-002** (1.22, 1.23): Co-editing log hygiene: a save outside the shared session never loses the others' text; every log has a random epoch that changes with each reset (`update` and `compact` answer 409 on an old epoch); an update stored while a session save is in flight stays unsaved; conditional-reset, numbered, locking and epoch store interfaces, the subscription policy and the Mercure cookie.
- **FR-SAVE-001** (1.24): Saves from a known revision: `saveRevision()` and `restore()` take `$expectedRevision`, lock the page row and refuse a collision or a conflict (`WikiRevisionCollisionException`, `WikiRevisionConflictException`) with the conflict pages shown to the editor.
- **FR-RENDER-001** (1.24): Per-viewer render: `WikiRenderContext` / `WikiRenderFrame` / `WikiRenderReach` name the space and page of each render (page body, comments, reader's Markdown); `WikiViewerMarkdownView` gives a reader who may not edit the Markdown of what they see, never the source.
- **FR-RENDER-002** (1.24.1, 1.28): Host files and file previews follow the render's reach; `editor.milkdown.file_previews` (default on with Milkdown) through `WikiFilePreviewFrames`, bounded per request.
- **FR-API-001** (1.24): REST API (`nowo_wiki.api`, off by default: no route, service or listener): stateless reads of wikis, pages (metadata, filters, ancestors checked), revisions and search through the host's `authenticator` and `directory` hooks; 1.40 adds `sections: [{id, level, text}]` to the page body.
- **FR-API-002** (1.24+): REST API writes, comments and delegated calls: page create, save, move (with `position`, 1.35), archive; comments; `WikiApiWriteEvent` after each write; rights re-checked on every call.
- **FR-API-003** (1.24+): API host hooks (authenticator, directory, users, files, file store, plan gate and policy, rate limiter, wiki management) each with a denying or null default, so an unwired hook fails closed.
- **FR-API-004** (1.24+): API idempotency (`Idempotency-Key`: claim, replay, outcome in `WikiApiIdempotencyRecord`, `wiki:api:purge-idempotency`) and the request boundary (`WikiApiGuard`, request listener, render reach); `WikiApiSafetyPass` refuses a container whose API hooks are unsafe.
- **FR-USER-001** (1.27): User reference resolver and ports typed with ids and snapshots: `WikiUserReferenceResolverInterface`, scalar `actorId()` on the events, service ports (`WikiPageServiceInterface`, `WikiSpaceServiceInterface`) and the framework-free twins of the ports that used Symfony types.
- **FR-ARCH-001** (1.36, REQ-ARCH-001 rules 1 and 3): Framework-free host ports: `WikiUserRef` and the value objects (`WikiPageSnapshot`, `WikiSpaceSnapshot`, `WikiRequestContext`, ...), the `Port\*Gate` adapters between a deprecated port and its twin (`WikiPortsPass`), operations by id (`WikiPageOperationsInterface`, `WikiSpaceOperationsInterface`) and events carrying ids.
- **FR-ARCH-002** (1.36, rule 6, opt-in): `database.private_entity_manager`: the wiki on its own entity manager (connection, driver, middleware, repository factory, `PrivateEntityManagerPass`). 2.0: the only mode (`false` fails the build, `true` is a deprecated no-op until 3.0).
- **FR-ARCH-003** (1.36, rule 4, opt-in): `database.mapping_type: xml` loads the 13 `*.orm.xml` files under `Resources/config/doctrine-xml` (`WikiDoctrineMapping`, `WikiMappingManagerPass`, `WikiExcludedEntityNamespaceDriver`).
- **FR-SEC-011** (1.31.1, 1.36): Stored user and owner ids match exactly (binary collation `WikiSpaceCollationListener`, `WikiUserRefType`, `WikiStoredUserRef` refuses unusable ids); a look-alike id never opens another owner's space.
- **FR-WORKER-002** (1.29.1, 1.36): No state leaks between requests where the kernel is not reset: `WikiRequestBoundaryResetter` (request, Messenger, console), `WikiTransactionRunner` / `WikiSavepoint` / `WikiFlusher` / `WikiEventDeferral`, locking re-read of the entity before a change.
- **FR-UI-013** (1.29 to 1.38): Page layout options, all opt-in or default-preserving: compact header, `page_header.tabs: toolbar`, toolbar position, `page_meta: card`, `reading_width` and `reading_align`, `tree_rail` and `tree_collapsed_mode`, `narrow_tree_position`, `breadcrumb_current_label`, edit view in the read layout, comments dock breakpoint, `comments.layout`, `comments.thread_list`, collapsible plain tree, table scroll. 1.41: the UiKit shell's focus mode (UiKit >= 2.55): the toggle (area `wiki`) on the page view and edit form, the page tree as a drawer in focus mode; nothing with a UiKit without it.
- **FR-ICON-001** (1.32, 1.38, 1.39): Icons: `WikiSpace::icon` and `WikiPage::icon` (UX names), emoji icons stored as `emoji:<grapheme>`, and the icon picker field (`page_icon.picker` / `space_icon.picker: icon_selector`).
- **FR-MEMBER-001 / FR-PROV-001** (1.34): Member badges beside author names (`WikiMemberBadgeProviderInterface`, null by default) and write provenance of each revision (`WikiWriteProvenanceProviderInterface`, erased with the author).
- **FR-TREE-002** (1.35, 1.39, 1.41): New page as a dialog (`web_ui.new_page_dialog`), create at a place (web `place`, API `position`), and the tree context menu (`tree.context_menu`). 1.41: Open in new tab, Edit, New page above / below, Duplicate (the page only), Move up / down / to…, Archive / Unarchive, Delete (trash, confirmed with the count), keyboard and long press; each entry with the rights of the page that does it elsewhere, every write POST + CSRF + same origin through the existing services.
- **FR-NAV-001** (1.39): Previous / next page links (`web_ui.page_nav: top|bottom|both`, default `none`).
- **FR-SECTION-001** (1.40): Stable heading ids in the Milkdown editor, explicit ids (`## Title {#setup}` renders `nowo-h-setup`), "Link to this section" on every heading and section labels (`Title > Section text`).
- **FR-LINKPICK-001** (1.40, `editor.milkdown.link_picker`, opt-in): the Milkdown link picker: page and section search inside the same tenant only (`WikiLinkPickerController`, `WikiLinkPickerService`, tenant resolver port).

---

### Releases 1.42 to 1.51

- **FR-TX-001** (1.42, REQ-RUNTIME-003 rule 6): `Transaction\WikiStorageFailureConflictFormatter` shapes transaction-doctrine's lock-conflict answer for the wiki's own routes; `Transaction\WikiConflictAnswer` (1.45) answers a lock conflict of the favourite toggle with the shared policy's status, `Retry-After` and `X-Conflict-Code` (`CONFLICT_001`), and `CONFLICT_002` without `Retry-After` when part of the request committed (request commit watermark).
- **FR-QA-001** (1.45, spec [003-quick-wins](../003-quick-wins/spec.md)): `favourites.enabled` and `recents.enabled`: `QuickAccess\WikiQuickAccess` / `WikiQuickAccessStore` over the `WikiPageFavourite` and `WikiPageVisit` entities (per person, user id as a string without a foreign key, deleted with the page, at most `recents.keep` visits per person), `WikiFavouriteController` + `WikiFavouriteType` (POST + CSRF, flat form), `WikiFavouriteResult` (a lost unique-key race is `Unchanged`, never a conflict); the lists are filtered again per viewer through `WikiReadableSpaces` / `WikiViewerScope` (role gate, tenant / team resolver, `View` on the space, current tenant only); erasure by `wiki:quick-access:erase-user` (`WikiQuickAccessEraseUserCommand`, always registered) and `WikiQuickAccessErasureListener` (on `COMMENT_AUTHOR_ERASED`); `wiki:recents:purge` (`WikiRecentsPurgeCommand`, `recents.retention_days`, 90 by default) deletes old visits in bounded batches; XML mappings `WikiPageFavourite.orm.xml` and `WikiPageVisit.orm.xml`; template `manage/_quick_access.html.twig`.
- **FR-BL-001** (1.45, spec 003): `backlinks.enabled`: the "Linked from" list (`manage/_backlinks.html.twig`, `Backlink\WikiBacklinks`, only pages the viewer may read) over the backlink index `WikiPageReference` (`WikiPageReference.orm.xml`; one row per `[[wiki:<uuid>]]` link of a page's current revision, target by id only, deleted with its source page) kept current by `WikiPageReferenceIndex` (reindex in a unit of its own under the page row lock) and `WikiPageReferenceListener` (after `PAGE_SAVED` committed), filled by `wiki:backlinks:rebuild` (`WikiBacklinksRebuildCommand`, keyset batches, beside live saves).
- **FR-HIST-002** (1.45, spec 003): `history.rendered_diff` with `editor.type: milkdown`: `History\WikiRenderedRevisionDiff` renders the diff of two Markdown revisions with the editor's diff (blocks, then words) and `History\WikiViewerBlockRenderer` renders each block through the wiki's per-viewer render inside the page's render frame, the page sanitizer and the attachment filter; made per diff, never a service.
- **FR-SK-001** (1.44, 1.46, 1.47): the optional SearchKit group (`search_kit_provider`, off by default; SearchKitBundle >= 1.10.1 checked by `Search\SearchKit\WikiSearchKitAvailability`): `WikiPageSearchDefinition` (the wiki's rules on SearchKit's provider base), `WikiPageSearchKitProvider` (header search, palette, results page, recents, `space:` filters), `WikiSearchKitPass` (the generated provider's tags move to the wiki provider so the group runs once), `Search\WikiReadablePageFinder` / `WikiReadablePageHits` (the wiki's read rules as a bounded candidate query, exact total only when the scan completed), viewer resolution (`WikiSearchViewerResolverInterface`, default `UserProviderWikiSearchViewerResolver`) and current tenants (`WikiSearchTenantResolverInterface`, `WikiSearchTenantsResolverInterface`, `WikiSearchTenantsResolverAdapter`, default `WikiTenantPathsSearchTenantResolver`); `Event\WikiSearchSpacesEvent` / `WikiSearchSpace` let a host leave spaces out before the SQL.
- **FR-PEM-001** (1.49 to 1.55, removed in 2.0 with the shared-manager mode; REQ-ARCH-001 rule 6): `Doctrine\WikiHostFlushProbe`, registered only with `database.private_entity_manager: false`, lists what the shared entity manager flush wrote on the host's behalf as a deprecation migration list; the private manager needs no probe.
- **FR-SPACE-002** (1.50): space lifecycle by ids: `Space\WikiSpaceLifecycleInterface` / `WikiSpaceLifecycle` (stateless, on the wiki's runner) renames, sets the icon of (1.56, also `WikiSpaceOperationsInterface::setSpaceIcon()`), archives, unarchives and purges a space (`WikiSpaceLifecycleAction`); `WikiSpacePurger` deletes one batch of pages with everything that hangs on them, or the space row itself, in one transaction under the space lock (`WikiSpacePurgeBatch`, `WikiSpacePurgeResult`; refused inside an open transaction with `WikiSpacePurgeInTransactionException`, audit XO-1); optimistic version (`WikiSpaceVersionConflictException`, 409 without `Retry-After`); refusals `WikiSpaceAccessDeniedException` (403), `WikiSpaceNotFoundException` (404, same answer as a space of another tenant), `WikiSpaceStateException` (409); events `WikiSpaceLifecycleEvent` (renamed, archived, unarchived) and `WikiSpacePurgedEvent` after commit and `WikiSpaceAccessCheckEvent` (listener may grant or deny, a deny sticks); `Security\WikiArchivedSpaceAccessChecker` decorates the space checker: an archived space is read-only for everyone and open only to people who may manage it.
- **FR-QRY-001** (1.51, REQ-ARCH-001 rule 1, DDD-host checklist B7): read ports `Query\WikiPageQueryInterface` / `WikiPageQuery` (find, count, page contents, page stats, owner usage, search, backlinks) and `Query\WikiSpaceQueryInterface` / `WikiSpaceQuery` (find, count, usage): ids and criteria in (`WikiPageCriteria` needs at least one of space, owner and page filters, `WikiSpaceCriteria`, `WikiArchiveFilter`, `WikiPageSort` / `WikiSpaceSort` ending on the id; id lists deduplicated, at most 1,000 ids, owner ids byte for byte, `WikiQueryIds`), snapshots out (`WikiPageContent`, `WikiPageSearchHit`, `WikiPageSearchResult`, `WikiSpacePageStats`, `WikiOwnerUsage`), scalar selections only through `WikiQueryRows` so nothing enters an identity map; public services, stateless.
- **FR-ACC-001** (1.51, DDD-host checklist B8): `Security\WikiPageAccessInterface` / `WikiPageAccess` answer whether a person may read a page by ids, the id twin of `WikiSpaceGuard::isPageReadable()` (`View` on the page and every ancestor, a parent cycle reads as hidden); stateless, decisions of one call are a local memo.
- **FR-BUILD-002** (type declarations): `Resources/assets/src/raw-modules.d.ts` declares Vite `?raw` imports for the CSS token test; not shipped runtime code.

### Releases 1.52 to 2.0

- **FR-SEC-012** (1.52, REQ-SEC-006): `Doctrine\WikiExactKeyColumns`: the bundle declares its own identity and access-key columns (binary, NO PAD collation) instead of relying on the host's schema listener.
- **FR-DIALOG-001** (1.53): every dialog loads its content on demand: an empty UiKit `remote_form_dialog` whose opener links to the dialog's own page (`X-Nowo-Dialog`, `Vary`, `private, no-store`); the bodies are `manage/_new_page_form`, `_import_export_form` / `import_export`, `_ai_ask_form`, `_tree_menu_form` and `_milkdown_editor_assets`. 2.0 removes the 1.35–1.52 inline forms.
- **FR-BUILD-003** (1.54, REQ-ASSETS-005): `wiki.js` logs one `📦 [wiki] script loaded, build: …` line per page (`script-banner.ts`, once-guard, quiet switch, build time from `build-info.json`) through the house logger (`logger.ts`).
- **FR-SPACE-003** (1.55, 1.55.1): `WikiSpaceOperationsInterface::createSpaceFrom(WikiNewSpace)` creates a space by id with its settings in one `INSERT` (a taken slug is `WikiSlugTakenException`), `Util\WikiSpaceSlugRule` (tenant-path slugs), and the idempotent `WikiSpaceLifecycleInterface::eraseSpace()` with the expected owner (`WikiSpaceOwnerMismatchException`) and file counters.
- **FR-COMMENT-004** (1.55): comment read models by id (`findCommentThread()`, `findComment()`, `findThreadComments()` capped at 1000; `WikiCommentThreadSnapshot`, `WikiCommentSnapshot`, `Repository\WikiCommentByIdFinderInterface`); no access decision.
- **FR-ARCH-004** (2.0, REQ-ARCH-001 rules 1, 3 and 6): the 1.x deprecations are removed: private entity manager only, the 17 Symfony-typed host ports become `@internal` `Port\Internal\` interfaces (hosts implement the 1.36 twins; `WikiRemovedPortsPass` and `WikiPortsPass` fail the build on any trace of a removed interface), the `$user` members are private, the 1.6 / 1.10 configuration keys fail the build with their replacement, and the pre-1.10 layout bridge is gone. See `docs/UPGRADING.md` "To 2.0.0".

## Success Criteria

- **SC-001**: 100% of production files in `src/` appear in [`code-inventory.md`](code-inventory.md) with requirement IDs (506/506 mapped; the co-located Vitest files `*.test.ts` are excluded).
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
