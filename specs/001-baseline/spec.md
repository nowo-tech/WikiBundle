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
- **FR-FORM-001**: Page form with slug and content fields.
- **FR-EVT-001 / FR-EVT-002**: Domain events for list/access and page lifecycle.

### Interchange & AI

- **FR-IX-001**: Archive import/export pipeline (front matter, markdown, format detection).
- **FR-AI-001–005**: AI assistant interface, Symfony AI impl, knowledge tool, context retriever, unavailable handling.

### UI & CLI

- **FR-VIEW-005 / FR-UI-010–011**: Manage UI + Tiptap editor assets.
- **FR-CLI-001**: Import/export commands.
- **FR-ROUTE-001**: Configurable route loader.
- **FR-I18N-004**: Seven locale files.

---

## Success Criteria

- **SC-001**: 100% of production files in `src/` appear in [`code-inventory.md`](code-inventory.md) with requirement IDs (84/84 mapped).
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
