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
**Configuration root**: `wiki`


Symfony bundle for a **versionable team wiki**: spaces, page tree, Tiptap rich-text editor, revision history/diff, search, import/export, optional Symfony AI assistant, and pluggable ACL.

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

---

## Requirements

### Core wiki

- **FR-BUNDLE-001 / FR-CFG-001 / FR-CFG-002**: Bundle, config (routes, ACL, AI), extension.
- **FR-ENTITY-001 / FR-REPO-001 / FR-REPO-002**: Spaces, pages, revisions; ORM repositories.
- **FR-PAGE-001 / FR-SPACE-001 / FR-TREE-001 / FR-REV-001 / FR-SEARCH-001**: Page/space CRUD, tree, diff, search services.
- **FR-SEC-001 / FR-SEC-005 / FR-SEC-006**: Access checker, HTML sanitizer, team membership resolver.
- **FR-SEC-007**: Attribute-level sanitizers keep only safe attributes: `EmbedIframeSrcAttributeSanitizer` (`<iframe src>` only for https hosts on the embed allowlist) and `TextAlignStyleAttributeSanitizer` (`style` only when it is exactly a Tiptap TextAlign declaration).
- **FR-SEC-009** (1.13.2): one read rule for a page — `View` on the page and on every ancestor (`WikiPageVisibility`, `WikiSpaceGuard::isPageReadable()` / `requireReadablePage()`), applied to the page view and every page route, attachments, co-editing, search and AI hits, as in the tree, the export and the page-link resolver; a parent cycle reads as hidden.
- **FR-SEC-008**: `WikiHtmlTwigExtension` (`nowo_wiki_safe_html`) re-sanitizes stored page HTML at render time, so revisions saved under an older policy are rendered with the current one.
- **FR-WORKER-001**: Safe without kernel / `services_resetter` reset (FrankenPHP worker): `WikiEntityManagerProvider` resolves the configured entity manager on every call and resets a closed one; `WikiTokenGuard` refuses to trust a token left in storage by a previous request. `AllowAllWikiAccessChecker` (FR-SEC-001) is only used when `security.allow_unauthenticated` is true (demo/dev).
- **FR-FORM-001**: Page form with slug and content fields.
- **FR-EVT-001 / FR-EVT-002**: Domain events for list/access and page lifecycle.

### Interchange & AI

- **FR-IX-001**: Archive import/export pipeline (front matter, markdown, format detection).
- **FR-MD-001** (1.11): Markdown storage — `content_markdown` + `format`, the extensible league/commonmark renderer (`WikiMarkdownRenderer`, `WikiMarkdownExtensionInterface`), GFM task-list normalisation, page references (`WikiMarkdownReferenceExtractor`), `wiki:convert-markdown`.
- **FR-MD-002** (1.13): Milkdown ↔ wiki bridge (`editor.type: milkdown`) — `MilkdownWikiMarkdownRenderer` (the Milkdown dialect under the `nowo_milkdown_editor.markdown` limits; viewer-neutral cache, per-viewer page view), `WikiMilkdownPageResolver`, icon placeholder + hydration after sanitizing, read-side rich content (`WikiMilkdownRichContent`, `nowo_wiki_rich_content_assets()`, `wiki.js`), references from the renderer's parser (pages, mentions), dialect HTML → Markdown converters, portable page links in exports, `MilkdownBridgePass` (fail closed).
- **FR-MD-004** (1.13): co-editing of Milkdown pages over Mercure — `WikiCollabController` (token, state, update, awareness, compact; edit permission on every request, same-origin JSON, size and rate limits), `WikiCollabService`, the Yjs log store (`WikiCollabStateStoreInterface`, Doctrine default, client compaction), Mercure publisher / subscriber cookie, seeding and the "replacement wins" reset (`WikiCollabSaveListener`).
- **FR-TREE-001** (1.14, `tree.enabled`, opt-in): Outline-style page tree — the sidebar renders the UiKit `tree` partial (WAI-ARIA treeview, current branch unfolded, fold state per user and space from `WikiTreeStateStoreInterface`, session default), moves by drag, Alt+arrows (`wiki.js` glue) and the Move form (`WikiPageMoveType`); `WikiTreeController` (`move`, `state`: same-origin JSON, CSRF per space, bounded bodies), `WikiPageMover` (same space, no cycles, `tree.max_depth`, edit rights on the page and both parents, one space-locked transaction through `WikiPageTreeWriterInterface`, sparse positions) and `nowo_wiki.page.moved` (`WikiPageMovedEvent`). Without the tree no route is registered and output is unchanged. Decisions (user, 2026-11-03): moves stay within one space (D-W1), depth 10 (D-W2), sparse integer `position` ordering (D-W27), fold state in the session with `tree.state_store` as the per-user option (D-W28).
- **FR-TRASH-001** (1.14, `trash.enabled`, opt-in): the trash — `WikiTrashController` (Delete behind a confirm, the trash list, Restore, Purge after a confirmation page and a second confirm), `WikiTrashService` (a deletion archives the page and its sub-tree in one batch under the space lock; restore together; purge deletes pages, revisions, links and attachments; retention via `wiki:trash:purge`), `WikiTrashEntry` / `WikiTrashRepositoryInterface` (`<prefix>_page_trash`, left out of the schema when off), `WikiAction::Delete` / `Purge` (never in `grants()`), `nowo_wiki.page.trashed` / `trash.restored` / `trash.purged`. Decisions (user, 2026-11-03): a delete takes the whole sub-tree and a restore brings it back together (D-W29); pages archived before stay archived (D-W30); a restored page whose parent is gone comes back at the top level (D-W31).
- **FR-MD-003** (1.13): `wiki:convert-markdown` scope (`--page`) and fidelity report (`WikiConversionFidelityAnalyzer`, JSON `nowo-wiki-conversion-report` v1, `--skip-lossy`, `--fail-on-loss`); history is never rewritten and the command is idempotent.
- **FR-AI-001–005**: AI assistant interface, Symfony AI impl, knowledge tool, context retriever, unavailable handling.

### UI & CLI

- **FR-VIEW-005 / FR-UI-010–011**: Manage UI + Tiptap editor assets.
- **FR-CLI-001**: Import/export commands.
- **FR-ROUTE-001**: Configurable route loader.
- **FR-I18N-004**: Seven locale files.

---

## Success Criteria

- **SC-001**: 100% of production files in `src/` appear in [`code-inventory.md`](code-inventory.md) with requirement IDs (92/92 mapped; the co-located Vitest file `wiki.test.ts` is excluded).
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
