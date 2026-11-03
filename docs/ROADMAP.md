# Roadmap

Feature roadmap of WikiBundle after **1.13.0** (2026-11-03), written on 2026-11-03. It is a plan, not a commitment:
every item becomes a Spec Kit feature with its own spec, tasks and tests before it is built, and versions may move.

**Goal.** A knowledge base that is as close as possible to **Notion** and **Outline**, built on what WikiBundle
already guarantees: Markdown revisions are the source of truth (with the Milkdown editor), every page, title, count
and excerpt is filtered **per viewer**, pages follow the UiKit page contract, and the host (symfony-beacon) owns
users, notifications and storage. The editor side is planned in the
[MilkdownEditorBundle roadmap](https://github.com/nowo-tech/MilkdownEditorBundle/blob/main/docs/ROADMAP.md); the
task ids (T0xx) refer to symfony-beacon `specs/178-wiki/tasks.md`.

## Table of contents

- [Ground rules](#ground-rules)
- [Where we are (1.13.0)](#where-we-are-1130)
- [Notion / Outline parity matrix](#notion--outline-parity-matrix)
  - [Parity summary](#parity-summary)
- [Milestones](#milestones)
- [Plans](#plans)
  - [Page tree sidebar (T062, T049)](#page-tree-sidebar-t062-t049)
  - [Structure: collections, icons, covers, emoji titles, tags](#structure-collections-icons-covers-emoji-titles-tags)
  - [Trash (T065)](#trash-t065)
  - [Version history](#version-history)
  - [Inline and page comments (T064)](#inline-and-page-comments-t064)
  - [Templates (T069)](#templates-t069)
  - [Search with filters and highlighting](#search-with-filters-and-highlighting)
  - [Discovery: recents, starred, backlinks, related pages](#discovery-recents-starred-backlinks-related-pages)
  - [Lifecycle: drafts, duplicate, archive, pins, review dates](#lifecycle-drafts-duplicate-archive-pins-review-dates)
  - [Notifications, subscriptions, mentions, presence](#notifications-subscriptions-mentions-presence)
  - [Per-page permissions](#per-page-permissions)
  - [Public links](#public-links)
  - [Import and export](#import-and-export)
  - [API, webhooks, command palette](#api-webhooks-command-palette)
  - [Accessibility, performance and audit residuals](#accessibility-performance-and-audit-residuals)
- [Notion-style databases](#notion-style-databases)
- [Proposed (not yet requested)](#proposed-not-yet-requested)
- [Open decisions](#open-decisions)
- [Links](#links)

## Ground rules

1. **Backward compatible and opt-in.** New entities come with migrations; new UI with configuration switches
   (`nowo_wiki.features.*` or the matching section) off or neutral by default, unless a release says otherwise.
2. **Per-viewer first.** No title, count, excerpt, diff line, comment or search hit of a page the viewer may not read,
   ever. Filtering happens **before** ranking, counting and pagination; caches are viewer-neutral and the per-viewer
   pass runs on top.
3. **Markdown stays the document.** Comments, anchors, database rows, tree order and permissions live in tables,
   never inside the page body; exports and search therefore never leak them.
4. **UiKit for UI primitives.** Reusable widgets (treeview, board, calendar, gallery) go to UiKitBundle so
   DashboardMenu, TaskBoard and others reuse them. Under symfony-beacon's UiKit-change rule a UiKit change must keep
   existing output byte-identical and needs Beacon's OK before release.
5. **Who builds it.** **W** = WikiBundle, **E** = MilkdownEditorBundle, **U** = UiKitBundle, **S** = StorageKitBundle,
   **SK** = SearchKitBundle, **H** = host (symfony-beacon). Each item says which.

## Where we are (1.13.0)

- Spaces (team / user), pages with parent / position / slug, archive and unarchive, immutable revisions
  (`<prefix>_page_revisions`, HTML or Markdown), a **line diff** between two revisions, restore as a new revision.
- Sidebar: a nested, fully expanded `<ul>` tree (`WikiPageFactory::tree()`), capped at `max_tree_pages` with a link
  to search; breadcrumbs in the UiKit page header.
- Search: literal `LIKE` on title and current revision, excerpts from the viewer-neutral `content_html` cache.
- Import / export of Outline and Notion Markdown trees; attachments (local private storage); page links index
  (`WikiPageLink`, reference extractor); events for hosts; optional Symfony AI Q&A filtered to readable pages.
- Milkdown editor bridge (`editor.type: milkdown`), per-viewer render, Mercure co-editing with a bounded Yjs log.

## Notion / Outline parity matrix

Knowledge-base features (the editor features are in the Milkdown roadmap's matrix). Status **Yes / Partial / No**.

| # | Feature | Notion | Outline | Us (1.13) | Owner | Spec 178 | Target |
|---|---------|--------|---------|-----------|-------|----------|--------|
| 1 | Spaces / teamspaces | Yes | Yes | **Yes** | — | — | — |
| 2 | Collections (documents) inside a space | Teamspaces | Yes | No | W | T048 | 1.14 |
| 3 | Outline-style page tree (disclosures, lazy, fold state, current branch) | Yes | Yes | Partial (flat expanded list, capped) | W + U | T062 | **1.14** |
| 4 | Drag and drop reorder / move, *Move to…* | Yes | Yes | No | W + U | T049 | **1.14** |
| 5 | Breadcrumbs | Yes | Yes | **Yes** | — | — | — |
| 6 | Starred and recents | Yes | Yes | No | W | T051 | 1.14 |
| 7 | Page icons, emoji titles, covers | Yes | Icon | No | W + E | T057, T063 | 1.14 |
| 8 | Tags | Properties | No | No | W | T046, T063 | 1.18 |
| 9 | Search with filters and highlighting | Yes | Yes | Partial (literal match, excerpts) | W + SK | T046 | 1.18 |
| 10 | Instant search (Ctrl / ⌘ + K) | Yes | Yes | No | SK + H | T066 | 1.18 |
| 11 | Backlinks / *Linked from* panel | Yes | Yes | Partial (link index) | W | T050 | 1.18 |
| 12 | Related-pages graph | No | No | No | W | — | 1.22 (beyond parity) |
| 13 | Drafts vs published | Partial | Yes | No | W | T055 | 1.17 |
| 14 | Templates (per space, global, variables) | Yes | Yes | No | W + E | T069 | **1.17** |
| 15 | Trash with restore and purge | Yes | Yes | Partial (archive) | W + S | T065 | **1.15** |
| 16 | Duplicate (with children) | Yes | Yes | No | W | T065 | 1.15 |
| 17 | Pins | Yes (favourites) | Yes | No | W | T065 | 1.17 |
| 18 | Review dates / *verified* | Yes (wikis) | No | No | W + H | T068 | 1.17 |
| 19 | Version history (timeline, named versions, rendered diff, restore) | Yes | Yes | Partial (revisions, line diff, restore) | W + E | — | **1.15** |
| 20 | Inline comments (anchored threads) | Yes | Yes | No | W + E + H | T064 | **1.16** |
| 21 | Page-level comments | Yes | Yes | No | W | T064 | 1.16 |
| 22 | Notifications and subscriptions | Yes | Yes | No (events only) | W + H | T064 | 1.16 |
| 23 | Mentions with notifications, per-viewer | Yes | Yes | Partial (render only) | W + H | — | 1.16 |
| 24 | Presence on pages | Yes | Yes | Partial (editor cursors) | W + E | T060 | 1.16 |
| 25 | Per-page permissions (levels, inheritance, restrict, guests) | Yes | Yes | Partial (space + page-aware checker) | W + H (RbacKit) | T056 | 1.20 |
| 26 | Public links (expiry, password, noindex, revoke) | Yes | Yes | No | W + S + H | T056 | 1.20 |
| 27 | Embed a page elsewhere | Yes | Yes | No | W | — | 1.20 |
| 28 | Import from Notion / Outline (+ Confluence) | Yes | Yes | Partial (Markdown trees) | W | T044, T052 | 1.22 |
| 29 | Export Markdown / HTML / PDF with attachments | Yes | Yes | Partial (Markdown trees) | W + H | T044, T045 | 1.22 |
| 30 | Databases (properties, views) | Yes | No | No | W + E + U | — | 1.19 → 1.23 |
| 31 | API and webhooks | Yes | Yes | No (Symfony events) | W | — | 1.22 |
| 32 | Keyboard-first navigation (treeview, palette, shortcuts) | Yes | Yes | No | W + U + SK | T066 | 1.14, 1.18 |
| 33 | AI Q&A over the wiki | Yes | Yes | **Yes** (optional) | — | — | — |

### Parity summary

**Yes = 1, Partial = ½:** 3 Yes and 10 Partial over 33 rows = **8 / 33 ≈ 24 %** today. With the milestones below:
≈ 40 % after 1.15, ≈ 55 % after 1.17, ≈ 70 % after 1.18, ≈ 85 % after 1.20 and ≈ 97 % after 1.23.

**Biggest gaps:** (1) the Outline-style page tree with drag / move; (2) trash and a real version history;
(3) inline comments with notifications; (4) templates; (5) search with filters and highlighting. Databases are the
largest single scope but Outline has none, so they matter for Notion parity only.

## Milestones

The "now" track (1.13.1–1.18) closes the most visible gaps first; two of them can run in parallel when they touch
different code (for example 1.15 and 1.16). Version numbers give the order, not dates.

| Version | Theme | Content | Owners | Effort |
|---------|-------|---------|--------|--------|
| **1.13.1** | Audit residuals | CW-I10, CW-I7, CW-I9, CW-I12, CV-I9 (see [residuals](#accessibility-performance-and-audit-residuals)) | W | S |
| **1.14** | Tree and navigation | Outline-style tree (T062), move / reorder with ranks (T049), collections (T048), icons / emoji titles / covers (T063, T057), starred + recents (T051), *Move to…* dialog, CV-I5 contract | W + U (treeview) | L |
| **1.15** | Safety net | Trash with restore / purge (T065), duplicate (T065), version history (sessions, named versions, rendered diff, restore, copy to new page, retention) | W + S + E (optional diff view) | L |
| **1.16** | Comments | Inline and page comments (T064), reactions, resolve, notifications and subscriptions, mentions filtered per viewer, presence, live updates | W + E + H | L |
| **1.17** | Templates and lifecycle | Templates (space / global, variables, seeds, row templates later), drafts (T055), pins (T065), review dates (T068) | W + E + H | L |
| **1.18** | Search and discovery | DB full-text index, filters, highlighting, SearchKit provider (Ctrl / ⌘ + K), tags (T046), backlinks panel (T050) | W + SK + H | L |
| **1.19** | Databases MVP | Database pages, rows as pages, basic property types, table + board views, filters / sorts / grouping, CSV import / export, `::database` block | W + E + U | L |
| **1.20** | Permissions and public links | Per-page permissions (levels, inheritance, restrict, guests, *Who has access*, audit) on RbacKit, public links (token, expiry, password, noindex, revoke, embed) (T056) | W + S + H | L |
| **1.21** | Databases 2 | List, gallery, calendar views; row templates; bulk edit; Notion database CSV import; live cell updates; row comments | W + E + U | L |
| **1.22** | Interchange and API | Notion (Markdown + CSV) / Outline (JSON + Markdown) / Confluence (optional) import, Markdown / HTML / PDF export with attachments, REST API, webhooks, related-pages graph | W + H | L |
| **1.23** | Databases 3 | Timeline view, relations, rollups, formulas, per-row visibility, schema migrations of complex types | W + E + U | L |
| 2.0 | Cleanup | Drop the TipTap path once hosts moved to Milkdown; remove deprecated options | W | — |

Editor releases each milestone depends on: 1.14 → Milkdown 1.3 (heading anchors for *Copy link*); 1.15 → Milkdown 1.4
(version view, optional); 1.16 → Milkdown 1.4 (comment anchors); 1.17 → Milkdown 1.5 (template picker, variable
chips); 1.19 → Milkdown 1.6 (database block).

## Plans

Each plan gives **value**, **owners**, **effort**, **dependencies**, **security / privacy** and the **version**.

### Page tree sidebar (T062, T049)

**Value.** The most visible Outline trait: a sidebar to navigate, create and reorganise. Today the sidebar is a
fully expanded, server-rendered `<ul>` capped at `max_tree_pages`.

- **Sidebar.**
  - Spaces → collections (when T048 is enabled) → nested pages to any depth, each parent with a disclosure button.
  - The current page's branch unfolded and the page marked `aria-current="page"`.
  - Fold state per user and space stored **server-side** (a `wiki_tree_state` row: user, space, expanded node ids;
    same approach as Beacon's UI preferences), applied before rendering so there is no flash.
  - **Lazy children**: the first render ships the expanded branches; collapsed parents load their children from
    `GET …/tree/children?parent=<id>` (JSON of value-free nodes, paged at 200).
  - Indicators where useful: draft badge, unresolved comment count (only counts the viewer may see), *private*
    lock; never for nodes the viewer cannot read.
  - **Starred** and **Recents** sections at the top (D-W3).
- **Actions per node.**
  - A hover / focus **"+"**: *New sub-page* (with the template picker from 1.17).
  - A **"⋯" menu**: rename, icon / emoji, duplicate, move, copy link, archive, delete (→ trash), star / pin,
    export.
  - **Drag and drop** to reorder or re-parent: drop-indicator line, *inside* vs *between* zones, auto-expand after
    hovering a collapsed parent, Escape cancels; across spaces only with D-W1.
  - **Keyboard alternative** (WCAG 2.5.7): Alt+↑ / ↓ reorders among siblings, Alt+← / → un-nests / nests, and the
    *Move to…* dialog (searchable tree, same data) for any target.
- **Accessibility.** WAI-ARIA **treeview** pattern: one tab stop (roving `tabindex`), ↑ ↓ ← →, Home / End,
  type-ahead, `*` expands all siblings, Enter opens, `aria-expanded`, `aria-level`, `aria-setsize`,
  `aria-posinset`, the actions reachable with a shortcut (Shift+F10 / the "⋯" button); moves announced in a live
  region ("*Runbooks* moved under *Operations*, position 3 of 5"); axe and keyboard-only e2e.
- **Server.**
  - **Ordering:** a fractional `rank` (string lexorank) column next to `position`; a move writes one row, never
    re-numbers siblings; a background job rebalances ranks that grow too long.
  - **Move validation:** no cycles (target not inside the moved subtree), edit right on source and target parents,
    depth limit (D-W2), same space unless D-W1; one transaction; an audit entry (who, from, to, when) and a
    `nowo_wiki.page.moved` event.
  - **Per-viewer filtering:** subtrees the viewer cannot read are dropped with their counts; a readable child of an
    unreadable parent is attached to the nearest readable ancestor or the root (no placeholder title).
  - **Cache:** the space's ordered skeleton (ids, parents, ranks, icons) cached per space and invalidated on
    page create / move / rename / archive / delete; titles and permissions applied per viewer on top.
  - **Live updates** (optional): a Mercure topic per space publishes `{moved, created, renamed, deleted}` with ids
    only; clients refetch the visible nodes (titles never travel in the update).
- **UiKit.** UiKit 2.17 has lists, cards, tabs, a nested aside, pagination and a filter bar, but **no treeview**.
  Propose a `tree` component in UiKit (Twig partial + IIFE: treeview keys, lazy loading hook, drag with keyboard
  alternative), reusable by DashboardMenu and others. Existing UiKit output must stay byte-identical, and Beacon's
  OK is needed before releasing it (UiKit-change rule).
- **Reuse.** Breadcrumbs and the *Move to…* picker read the same tree data.
- **Owners** W + U. **Effort** L. **Dependencies** UiKit tree component, Milkdown 1.3 (*Copy link* to headings).
  **Version 1.14.**

### Structure: collections, icons, covers, emoji titles, tags

- **Collections (T048).** `WikiDocument` (space, name, slug, description, icon, colour, position, archived) as the
  Outline collection level; pages belong to one. Opt-in. Effort M. **1.14.**
- **Icons and emoji titles (T063, T057).** Page and collection icon (Unicode emoji or Tabler name), picked with the
  Milkdown emoji picker API; slugs never contain emoji; shown in tree, breadcrumbs, search and exports. Effort S.
  **1.14.**
- **Covers.** Optional cover image per page through attachments / StorageKit (no remote URLs, alt text required or
  decorative), positioned by a vertical offset. Effort M. **1.14** (or later if storage is not ready).
- **Tags (T046).** Tag entity per space, editor field, filter in search, tag pages; included in import / export.
  Effort M. **1.18.**

### Trash (T065)

**Value.** Safe delete like Notion / Outline; today only archive exists.

- **Delete** moves the page **and its subtree** to the space trash (`deleted_at`, `deleted_by`, `deleted_with` = the
  root of the deletion batch, original parent and rank kept). Retention 30 days by default, configurable
  (`nowo_wiki.trash.retention_days`, D-W4).
- **Trash view** per space and *Deleted by me*, with search, restore, delete forever, **bulk restore** and *Empty
  trash* (UiKit confirm dialog).
- **Restore** puts pages back under the original parent at their rank. If the parent is gone (purged or itself in
  the trash), the page goes to the space root or the user picks a target in the *Move to…* dialog. Restoring a
  parent restores the children deleted **with** it (same batch), not ones deleted earlier on their own.
- **Nightly purge** (`wiki:trash:purge`, scheduler-friendly): removes the page, its revisions, link-index rows,
  comments, collab state and tree state; files go through **StorageKit**, deleted only when no other page or
  revision still references them.
- **While in the trash:** links to the page render *Deleted page* (per viewer, no title); search, tree, backlinks,
  AI context and exports exclude it; editing is blocked.
- **Permissions:** delete = edit right on the page; restore = edit right on the target; purge / empty trash =
  `manage` (D-W5). Every delete / restore / purge is audit-logged.
- **Privacy erasure:** an account erasure anonymises `deleted_by` and keeps content (the content belongs to the
  space); purge also removes personal data in comments of purged pages.
- **Owners** W + S. **Effort** M–L. **Version 1.15.**

### Version history

**Value.** Notion / Outline-style history: who changed what, named versions, a readable diff, a safe restore.
Builds on the existing revisions table, line diff and restore.

- **Timeline** per page: revisions grouped into **sessions** (consecutive saves by the same people within N minutes,
  or one co-editing session), each with author or **co-authors** (collected from the collab session on save),
  time and size of change. **Named versions**: *Save version* with a label (D-W7: all editors or only managers).
- **Diff between any two versions:**
  - a **rendered diff** — insertions and deletions highlighted in the rendered page, dialect-aware (page links,
    mentions, colours, tables, callouts are compared as units, not as raw characters);
  - a **raw Markdown diff** toggle;
  - computed server-side (block-level LCS, then word-level inside changed blocks), every value escaped, with size
    limits (documents over `max_input_length` fall back to the raw diff, at most N changed blocks shown).
  - Milkdown 1.4 offers an optional client diff view (`plugin-diff`) and a read-only *view version* mode; the
    server diff stays the reference (works without JavaScript).
- **Restore** creates a new revision (history is never rewritten) and triggers the 1.13 collab **reset** so live
  editors reload; *Copy to new page* creates a page from an old version.
- **Retention** (D-W6): keep every named version and the latest revision forever; thin autosaves older than N days to
  one per session / day; configurable; never thins below the last published revision.
- **Per-viewer safety:** history requires read rights (and `history`); page links in diffs resolve **per viewer**
  (an unreadable page shows *Content not visible* in both columns), so diffs never leak titles.
- **Owners** W + E. **Effort** L. **Version 1.15.**

### Inline and page comments (T064)

**Value.** The main collaboration feature of Notion and Outline after co-editing.

- **UX** (editor parts in the Milkdown roadmap):
  - start a comment from the selection toolbar, the right-click menu, the block menu or **Ctrl / ⌘ + Alt + M**;
  - the anchor is highlighted in the text, threads are listed in a margin / sidebar, with replies, @-mentions, emoji
    reactions, resolve / reopen, edit / delete own comments and a *Show resolved* filter;
  - **page-level comments** (no anchor) at the end of the page and in the sidebar;
  - the thread list is a `complementary` landmark, anchors are focusable, next / previous comment shortcuts,
    new replies announced politely.
- **Anchoring.** Comments are **not** in the Markdown body. Each thread stores, server-side:
  - Yjs relative positions (start, end) while the page is co-edited;
  - a durable **TextQuoteSelector** (exact quote, prefix and suffix of ~32 characters) plus a block fallback (heading
    slug or block index + offset) for reloads, non-collaborative edits, and the 1.13 log resets and compactions.
  - On load the editor reconciles: relative positions first, then quote + context, then the block fallback; if the
    quoted text is gone the thread becomes **orphaned** (shown in the sidebar as "Text was removed" with the
    original quote) and is never silently attached elsewhere.
- **WikiBundle.**
  - Entities `WikiCommentThread` (page, anchor, status, resolved by / at), `WikiComment` (thread, author, Markdown
    body, edited at, deleted at), `WikiCommentReaction` (comment, user, emoji).
  - JSON endpoints with CSRF and a rate limiter: list, create, reply, edit, delete, react, resolve, reopen.
  - A new `WikiAction::Comment` right (`wiki.comment` / `project.wiki.comment` in Beacon, D-W8: readers or editors).
  - Bodies rendered by the safe Milkdown renderer (no raw HTML), size limit, mentions resolved per viewer.
  - Notifications and subscriptions (below); live updates over the 1.13 Mercure infrastructure (one topic per
    page, ids only, authorised like co-editing).
  - Trash / purge: comments follow their page; resolved threads kept or purged after N days (D-W9).
  - Excluded from exports (unless an explicit *with comments* option for admins), search for users without the
    right, and AI context.
  - Privacy erasure: a user's comments are anonymised or deleted according to the host policy.
  - Counts per page in the tree (only for viewers who may read comments); an audit log of deletions and resolves.
- **Host (Beacon):** notification channels (in-app, e-mail, Web Push), the `wiki.comment` grant in roles.
- **Later:** suggestions / track changes (Notion *Suggest edits*) as reviewable proposals (Milkdown 1.8 + drafts),
  planned after 1.21.
- **Owners** W + E + H. **Effort** L. **Dependencies** Milkdown 1.4. **Version 1.16.**

### Templates (T069)

**Value.** "Start with a template" in Notion and Outline; postmortems and runbooks in Beacon.

- **Model.** `WikiTemplate`: scope (space or global = instance level), title, icon / emoji, description, body in the
  dialect (Markdown), default properties for database rows (1.21), created by, revisions (template history).
- **Use.**
  - *New page from template* in the tree "+", *New sub-page*, the command palette and the **empty-page state**
    ("Start with a template" list, as in Outline / Notion).
  - The new page **copies** the body at creation time: later template edits never change existing pages.
- **Variables.**
  - Filled at creation: `{{date}}`, `{{time}}`, `{{author}}`, `{{space}}`, `{{title}}`, plus host-provided ones
    through `WikiTemplateVariableProviderInterface` (for example Beacon's issue fields for *Write postmortem*).
  - The substitution engine is a fixed `{{name}}` token replacement over an allow-list (D-W12), with values
    Markdown-escaped. **No Twig, no `eval`, no expressions** on user input. Unknown variables stay as text.
- **Permissions:** create / edit templates = `manage` in the space (global templates: instance admins); using one =
  create right. Readers see the gallery only if D-W11 says so.
- **Interchange:** templates in import / export (a `templates/` folder with front matter).
- **Seed templates** (D-W10): meeting notes, postmortem, runbook, RFC / decision record, onboarding, how-to;
  translatable through the bundle's translation domain (seeded in the space's locale).
- **Editor (Milkdown 1.5):** template picker component, a slash *Template* item and a "template button" block that
  inserts content repeatedly at the cursor (like Notion's), and variables shown as chips while editing a template.
- **Database row templates** (1.21): see [Databases](#notion-style-databases).
- **Owners** W + E + H. **Effort** M–L. **Version 1.17.**

### Search with filters and highlighting

**Value.** Finding things is the core of a wiki; today search is a literal `LIKE` with excerpts from the
viewer-neutral HTML cache.

- **UX.**
  - **Instant search** in the Ctrl / ⌘ + K palette through **SearchKitBundle** 1.3 (header field, keyboard-first,
    grouped results, recents, `<mark>` built with DOM nodes): WikiBundle ships a `SearchProviderInterface`
    implementation (group *Wiki*, scope chip = current space through `SearchScopeResolverInterface`).
  - A **full search page** (UiKit list scaffold): results grouped by space, with title, breadcrumb, highlighted
    excerpt and date; keyboard navigation; *Search in this space* from the tree; recent searches (D-W15) and
    recently viewed pages.
- **Filters:**
  - space / collection, author, last editor, created / updated date ranges;
  - status (published, draft, archived; trash for admins), tags;
  - type (page, database row, comment, attachment — D-W14 for the first version);
  - database property filters (1.21);
  - sort by relevance / updated / created / title;
  - filter state in the URL (shareable).
- **Query features:** exact phrase `"…"`, exclusion `-word`, prefix matching (`deplo*` implicit on the last term),
  accent and case folding, per-locale stemming where the engine supports it, title boost over body.
- **Highlighting.**
  - Built server-side from a **per-viewer-safe plain text** column (`search_text`): the Markdown with dialect tokens
    reduced to neutral text (page links as their ids → no titles), never from a cache holding resolved titles.
  - Escaped, with `<mark>` as the only markup.
  - Opening a result highlights the terms in the page and scrolls to the first one, with find-next in the editor
    (Milkdown 1.7 find / replace).
- **Index** (D-W13).
  - **Default:** the database's own full-text index — MySQL 8.0+ / 9 `FULLTEXT` (InnoDB, `ngram` parser optional
    for CJK) or PostgreSQL `tsvector` + GIN with per-locale configurations. No extra service, transactional with
    the page.
  - **Optional adapter** `WikiSearchIndexInterface` for Meilisearch / OpenSearch (typo tolerance, better ranking)
    for large instances.
  - Incremental indexing on save, move, delete, restore and permission change; `wiki:search:reindex` in the
    background; attachment text extraction later.
  - Comments in a separate index, searchable only by users who may see them.
- **Security and privacy:**
  - results filtered per viewer **before** ranking, counting and pagination (with an external engine: the
    readable space / page ids go into the query as a filter, never post-filtered);
  - trash and archived only with rights;
  - rate limit per user, query length cap (e.g. 200 characters, 10 terms);
  - no stored query logs with personal data unless the host opts in.
- **Performance:** pagination (25), snippet length cap (≈ 240 characters), target p95 < 300 ms for 100 k pages on
  the default engine.
- **Owners** W + SK + H (palette placement). **Effort** L. **Version 1.18.**

### Discovery: recents, starred, backlinks, related pages

- **Recents and starred** (T051): per user, in the tree and the palette; never list pages the viewer lost access to.
  Effort S. **1.14.**
- **Backlinks / *Linked from*** (T050): panel on the page from the `WikiPageLink` index, filtered per viewer (only
  linking pages the viewer can read; no counts of hidden ones). Effort S. **1.18.**
- **Related-pages graph:** a small, accessible graph (with a list fallback) of links and backlinks around a page;
  beyond parity. Effort M. **1.22.**

### Lifecycle: drafts, duplicate, archive, pins, review dates

- **Duplicate** (T065) with or without children, attachments shared by reference. Effort S. **1.15.**
- **Drafts** (T055): `draft` state, draft revisions on top of the published one, *Publish*; drafts visible to their
  authors and editors only, excluded from search for others. Effort M. **1.17.**
- **Archive** stays (read-only, out of the tree) and is distinct from trash. **Pins** (T065) on space / collection
  homes. Effort S. **1.17.**
- **Review dates** (T068): owner, interval, *May be out of date* banner, *Reviewed* action, *Due for review* list;
  notifications are host work. Effort M. **1.17.**

### Notifications, subscriptions, mentions, presence

- **Subscriptions:** watch a page (automatic for authors and commenters), a collection or a space; unsubscribe.
- **Notifications:** WikiBundle emits typed events (`comment.created`, `mention.created`, `page.updated` digest,
  `review.due`); **Beacon** delivers them (in-app, e-mail, Web Push), per-user preferences (D-W16).
- **Mentions per viewer (CV-I5):** `UserResolverInterface` today only receives handles. Add a viewer / document
  context (`resolveUsers(handles, context)`) so a host can answer only people who may read the document, and notify
  only mentioned users who can read it; mention extraction ignores unreadable contexts.
- **Presence:** avatars of people viewing / editing a page (from the Mercure awareness of co-editing, read-only
  viewers optional). Effort M.
- **Owners** W + H. **Effort** M. **Version 1.16.**

### Per-page permissions

**Value.** Notion's *Share* dialog and Outline's collection permissions: restrict a subtree, invite people to one
page. Today: `WikiSpaceAccessCheckerInterface::isGranted(user, WikiAction, space, ?page)`, `canEdit()`, roles per
action (`security.roles`), `tenant_only` paths; Beacon maps `project.wiki.*` / `wiki.*` keys and is adopting
**RbacKit 1.0** (typed contexts `<type>:<id>`, permission sets, D-1 / D-3 / D-4).

- **Levels:** *Full access* (edit + share + delete), *Can edit*, *Can comment*, *Can view*, *No access*.
- **Grantees:** users, groups / teams and roles (RbacKit permission sets).
- **Inheritance** down the tree. *Restrict this page* breaks inheritance and copies the inherited grants as a
  starting point. The share dialog shows "Inherited from *Parent*" with a link. Moving a page that changes who can
  see it shows a warning with the delta ("3 people will lose access") before confirming.
- **Guests:** external users with grants on specific pages only (no space membership); they see only those pages in
  tree, search and palette (D-W21).
- **Model — recommended: a WikiBundle-native ACL gated by RbacKit** (D-W22).
  - RbacKit owns the **vocabulary and the space level**: permission keys (`wiki.view`, `wiki.comment`, `wiki.edit`,
    `wiki.share`, `wiki.manage`), system sets matching the five levels, and space / project grants through its
    typed contexts (`project:<id>` in Beacon, `wiki_space:<id>` standalone).
  - WikiBundle owns **page grants**: a `wiki_page_grant` table (page, grantee type + id, level, granted by / at) and
    a `restricted` flag on pages.
  - A resolver computes the effective level as: the nearest restricted ancestor's grants (or the page's own),
    otherwise the space level from RbacKit; *No access* overrides; `wiki.manage` holders can always manage.
  - **Why not one RbacKit context per page:** RbacKit has no inheritance between contexts, its `GrantProviderInterface`
    lists every context a user holds once per request (thousands of pages), and tree moves would rewrite grants.
    Tree inheritance and *restrict* are wiki semantics; RbacKit stays the source of the keys and the space grants,
    so Beacon has one permission model.
- **Speed:** effective levels resolved in batches (all pages of a tree / result page at once from the ancestor
  chains and grant rows), memoised per viewer per request (`ResetInterface`, keyed by user object), with a per-space
  "has any restriction" short-cut so unrestricted spaces cost one RbacKit check.
- **Consistency everywhere** (one resolver, tests per surface with marker strings): page view, tree, search (filter
  before ranking), backlinks, mentions, exports, history and diffs, comments, database rows and relations, collab
  endpoints, the Mercure topic authorisation and the AI context.
- **Admin:** a *Who has access* view per page and space (effective list with the source of each grant), and an
  **audit log** of every permission change (`WikiPermissionChangedEvent`, stored by the host).
- **Owners** W + H (RbacKit adoption in Beacon). **Effort** L. **Version 1.20.**

### Public links

**Value.** Notion *Publish to web* / Outline *Share link*: share a page with people without accounts.

- **Per page**, optionally including sub-pages. Settings:
  - on / off and an expiry date;
  - an optional password (hashed with the host's password hasher, rate limited, lockout after N failures);
  - *Allow search engines* **off** by default (`noindex`, `X-Robots-Tag`, robots), *Allow comments* off,
    *Allow duplicate / export* off.
- **Token:** at least 128 random bits (base64url), stored hashed; revocable and regenerable; an access log (time,
  coarse client data per the host's privacy policy).
- **Public view:** a read-only render through the same safe Milkdown renderer.
  - Page links to non-shared pages render as plain text (no titles).
  - Mentions anonymised or kept as handles (D-W25).
  - Embedded database views show only shared rows.
  - Images and files are served through **StorageKit signed, short-lived URLs**.
  - Mermaid and KaTeX are rendered as on internal pages.
  - Strict CSP, no app chrome, no session cookie, no CSRF-bearing forms; `Referrer-Policy: no-referrer` so the
    token never leaks to outbound links.
  - `Cache-Control: private, no-store` with a password (public caching only for password-less links, short TTL).
- **Later:** a custom domain or path prefix per space.
- **Admin settings:** disable public sharing instance-wide or per space; list every public link (page, creator,
  expiry, last access) with bulk revoke.
- **Security tests:** token brute-force limits; no IDOR by switching the page id under a valid token (the token
  names the page; sub-pages are checked against the shared root); expiry enforced server-side; caching headers;
  Referrer-Policy; no leaks of non-shared titles through links, mentions, embeds, breadcrumbs or the tree.
- **Embed a page elsewhere:** the public link with an `embed` mode (no header) and `frame-ancestors` from a host
  allow-list.
- **Owners** W + S + H. **Effort** M–L. **Version 1.20.**

### Import and export

- **Import:** Notion export (Markdown + CSV for databases, attachments folders), Outline export (JSON and Markdown),
  Confluence space export (optional, HTML → Markdown converter of 1.13 with a fidelity report). Collections and link
  rewriting (T052).
- **Export:** Markdown (with attachments ZIP), HTML (rendered, per viewer), PDF (host renderer, D-W17), per page,
  subtree or space (T044, T045); comments excluded; databases as CSV + Markdown index.
- **Security:** archive size / file count / path traversal limits (existing), no remote fetches during import,
  attachments sniffed and stored through StorageKit.
- **Owners** W + H. **Effort** L. **Version 1.22.**

### API, webhooks, command palette

- **REST API** (JSON, token or session auth by the host): spaces, pages (Markdown in / out), search, comments,
  databases; pagination; same per-viewer rules. **Webhooks**: signed (HMAC) deliveries of the events above with
  retries; host-configured endpoints, SSRF protections (no private ranges). Effort L. **1.22.**
- **Command palette (T066):** SearchKit's Ctrl / ⌘ + K with WikiBundle actions (new page, new from template, move,
  go to space) as quick actions; keyboard-only e2e. Effort S. **1.18.**

### Accessibility, performance and audit residuals

- **Accessibility:** every new view on the UiKit contract, axe in e2e (light / dark), keyboard-only journeys for tree,
  comments, trash, history and databases, live-region announcements for moves and saves; WCAG 2.2 *Dragging
  movements* and *Target size*.
- **Performance:**
  - large trees: lazy children, ranks, a cached skeleton;
  - per-viewer rendering: today the per-viewer body is never cached (contract decision 3); later, a **cache per role
    / permission fingerprint** for pages whose references are all role-visible (opt-in);
  - batched permission checks (the CV-L1 fix pattern) for every list.
- **Audit residuals (Info)** — planned for **1.13.1** unless noted:

| Id | Residual | Plan |
|----|----------|------|
| CV-I9 | Membership memo keyed by `getUserIdentifier()` for the whole request; stale in long-running loops | Key by user object id + identifier, implement `ResetInterface`, invalidate on membership events |
| CV-I5 | Mention resolver gets handles only; directory enumeration through `title` | Viewer / document context in the resolver contract (1.14) |
| CW-I7 | One editor can use up the shared reset cap; the manual reset then fails silently | Flash message on a capped manual reset; per-user share of the cap |
| CW-I9 | No per-user global limiter across pages | Add a per-user limiter on collab endpoints |
| CW-I10 | Purge can delete an active log (`--older-than 0`), no reset broadcast, no lock | Minimum of 1 day, broadcast `{t:"r"}` on purge, delete under a lock |
| CW-I12 | Default Mercure claims merged into the wiki cookie can widen `subscribe` | UPGRADING / CONFIGURATION line: never put `mercure` keys in default claims; optional guard that refuses them |

## Notion-style databases

User decision (2026-11-03): **in scope**. A dedicated track: MVP in **1.19**, then **1.21** and **1.23**.

### Data model

- **A database is a page with a schema** (`WikiDatabase`: page id, properties, views, settings); full-page databases
  are pages in the tree; **inline** databases belong to a host page and are shown by the `::database` block.
- **Each row is a page** (`WikiPage` with `database_id`) with **properties** and an optional body (Markdown), so rows
  get revisions, comments, links and templates for free.
- **Property types:**

| Track | Types |
|-------|-------|
| MVP (1.19) | title, text, number, select, multi-select, status, date (range), person, checkbox, URL, e-mail, created / edited time, created / edited by |
| 1.21 | files (StorageKit), phone |
| 1.23 | relation, rollup, formula |

- **Schema changes:** add / rename / delete / reorder properties; **type changes** run a migration job per database
  (text → number parses, invalid values kept in a `_lost` shadow for undo, select ← text creates options) with a
  preview count of values that will not convert; schema versions kept for undo.
- **Storage (D-W18) — recommended: JSON properties + a typed index table.**
  - Each row stores its properties as one JSON column (MySQL 9 `JSON` / PostgreSQL `jsonb`): one read per row,
    atomic updates, no DDL when a user adds a property.
  - A narrow **index table** `wiki_db_value(database_id, property_id, row_id, v_text, v_num, v_date, v_bool)` with
    composite indexes serves filters and sorts on any property with real B-tree indexes on both engines.
  - Why not pure **EAV**: N joins per row to read, slow wide tables. Why not **JSON only**: MySQL cannot index
    arbitrary JSON paths without a generated column per property (DDL per user action); PostgreSQL GIN helps
    equality and containment but not range sorts. The hybrid keeps reads simple and filters indexed on both engines.
  - Multi-value types (multi-select, person, relation) get one index row per value.
- **Limits** (configurable): 200 properties per database, 100 000 rows, 50 views, 10 filters per view, cell text 10 KB;
  pagination of 100 rows (table) / 50 cards (board), cursor-based.

### Views

- **Types:** table and board (kanban) in the MVP; list, gallery and calendar in 1.21; timeline in 1.23.
- Each view stores filters (AND / OR groups), sorts, grouping (board: group by select / status / person),
  visible properties and widths.
- Every view has a shareable URL (filter state included) and can be pinned in the tree.
- **UiKit:** UiKit has data tables, filter bars, pagination and cards, but **no board, calendar, gallery or
  timeline**; TaskBoardBundle has a kanban board. Propose UiKit components `board` (with keyboard move: grab with
  Space, arrows, drop with Space, as in the APG drag-and-drop guidance), `calendar` (grid with arrow keys) and
  `gallery`, extracted where possible from TaskBoard (beacon OK required, byte-identical existing output).

### Inline vs full-page and linked views

- **Full-page** databases are tree pages; **inline** ones are created from the editor and owned by the host page.
- **Linked views** of the same database in other pages: the Milkdown block `::database{id=<uuid> view=<uuid>}`
  (leaf directive). The editor shows the view inline (fetching rows from the Wiki API, editing cells in place); the
  PHP renderer (WikiBundle's `DirectiveRendererInterface`) renders the view **server-side per viewer**, paged, as an
  accessible table or list. Exports render a CSV + a link; search indexes rows as their own documents, not inside
  the host page.

### Rows

- **Row templates** (with default properties, 1.21), **bulk edit** (select rows → set property / delete / move,
  1.21), **CSV import / export** (MVP: column mapping, type detection, preview, errors report), **Notion database
  import** (the CSV in Notion's export, 1.21) with relation columns resolved in 1.23.

### Permissions

- Inherited from the database page and space; **per-row visibility** (1.23, optional): a row-level restriction like
  per-page permissions, filtered before counting.
- **Formulas** (1.23) evaluated **server-side** in a restricted expression language (D-W19): own small grammar
  (Notion-formula-like subset: arithmetic, comparisons, `if`, string / date / list functions, `prop("…")`), parsed
  into an AST and interpreted with step, depth and output-size limits; **no `eval`, no PHP callables, no object
  access**. Symfony ExpressionLanguage is the alternative (evaluate only, never compile), but its method-call syntax
  and providers make the sandbox harder to prove.
- **Relations and rollups** query per viewer: a rollup over rows the viewer cannot read counts only readable rows,
  and a relation to an unreadable row renders *Content not visible*.

### Collaboration

- **Live cell updates** over Mercure (reuse the 1.13 publisher / authoriser: a topic per database, ids and changed
  cells only, edit right checked on each write, optimistic concurrency with a row version). No Yjs for cells (last
  writer wins per cell, conflicts shown).
- **Comments on rows** reuse the 1.16 comment model (rows are pages).

### Beacon integration (optional)

- A `WikiDatabaseSourceInterface` could expose **TaskBoard tasks or Beacon issues** as read-only (later read-write)
  database sources, so a wiki page shows a board of issues with Beacon's own permissions. Optional, after 1.23.

### Split of the work

| Part | Owner |
|------|-------|
| Model, schema migrations, API, CSV, server rendering, permissions, formula engine, index | W |
| `::database` block, inline view, cell editing in a page, view switcher | E (Milkdown 1.6, then 1.8+) |
| Table, board, calendar, gallery, timeline components | U (TaskBoard as a source for the board) |
| Navigation, notifications, Beacon data sources | H |

### Milestones and risks

| Version | Databases content | Effort |
|---------|-------------------|--------|
| 1.19 (MVP) | Database pages, rows as pages, MVP property types, table + board views, filters / sorts / grouping, CSV import / export, `::database` block (read + cell edit) | L |
| 1.21 | List, gallery, calendar; files property; row templates; bulk edit; Notion CSV import; live cells; row comments | L |
| 1.23 | Timeline; relations, rollups, formulas; per-row visibility; complex type migrations | L |

**Risks:** scope creep (Notion's databases are a product of their own: ship the MVP and measure); performance of
filters on large databases (index table + limits); formula sandbox correctness (fuzz tests, limits); permission leaks
through rollups and relations (per-viewer tests with marker strings); UI complexity for keyboard and screen-reader
users (APG patterns, axe, keyboard-only e2e).

## Proposed (not yet requested)

Further features found while writing this roadmap. They are **proposals**: accept or drop each one.

| Proposal | Value | Effort | Milestone |
|----------|-------|--------|-----------|
| Related-pages graph | Shows neighbourhoods of linked pages; beyond Notion / Outline parity | M | 1.22 |
| Page analytics (views, viewers, popular pages, stale pages) | Outline-style insights; feeds review dates. Per-viewer and privacy-aware (aggregate counts, opt-in viewer lists) | M | 1.22 |
| Reminders on `:date[…]` and task digests | Notion `@remind`; notifications through the host | M | 1.21 |
| Read-only transclusion (`::include`) as "synced block" light | Reuse a section in many pages without copies; resolved per viewer | M | 1.22 |
| Offline reading (service worker cache of recently viewed pages) | Read on the move; must respect per-viewer data and logout purge | L | after 1.23 |
| Mobile layout of tree, comments and databases | Notion / Outline mobile web parity; drawer tree, bottom sheets | M | spread over 1.14–1.21 |
| Print / PDF styles of pages and exports | Clean print of pages (expanded details, URLs, no chrome) | S | 1.15 |
| Space home pages and collection overviews (cards, descriptions, pinned) | Outline collection home; better landing | S | 1.17 |
| Document translation links (same page in several locales) | Multilingual wikis (7 locales in the bundle) | M | after 1.23 |
| Webhook-driven integrations (Slack / Teams unfurls of wiki links) | Outline-like chat unfurls, per-viewer safe (title only for authorised workspaces) | M | 1.22 |
| AI: summaries of long pages and Q&A citations to headings | Builds on the optional Symfony AI integration; host-provided models only | M | 1.22 |

## Open decisions

| # | Decision | Recommendation |
|---|----------|----------------|
| D-W1 | Allow moves **across spaces**? | Yes for users with edit rights on both; permissions of the target apply |
| D-W2 | Maximum tree **depth** | 10 levels |
| D-W3 | Show **Starred** / **Recents** sections in the sidebar | Yes, both collapsible |
| D-W4 | **Trash retention** period | 30 days, configurable |
| D-W5 | Who can **purge** / empty the trash | Space managers (`manage`) |
| D-W6 | **Revision retention** policy | Keep named versions and the last revision forever; thin autosaves after 30 days to one per session / day |
| D-W7 | **Named versions** for all editors? | Yes |
| D-W8 | Who can **comment**: readers or editors only? | Readers with a `wiki.comment` right granted by default to members |
| D-W9 | Keep **resolved threads** forever? | Keep; purge only with the page |
| D-W10 | Which **seed templates** ship | Meeting notes, postmortem, runbook, RFC / decision record, onboarding, how-to |
| D-W11 | Can **readers** see the template gallery? | No (create right only) |
| D-W12 | **Template variables** available | `date`, `time`, `author`, `space`, `title` + host providers |
| D-W13 | **Search engine**: built-in DB full-text or external | Built-in (MySQL FULLTEXT / PostgreSQL tsvector) with an adapter interface |
| D-W14 | Comments and attachments **searchable** in the first version? | Comments yes (per viewer), attachments later |
| D-W15 | Keep **search history**? | Browser-only recents (SearchKit), no server log |
| D-W16 | **Notification defaults** | Mentions and replies on; page edits as a daily digest for watchers |
| D-W17 | **PDF export** renderer | Host-provided (headless Chromium service), optional |
| D-W18 | Database **storage model** | JSON properties + typed index table |
| D-W19 | **Formula language** | Own restricted grammar (Notion-like subset) |
| D-W20 | **Views first** | Table + board in the MVP; calendar next |
| D-W21 | **Guests** in the first permissions release? | No; page grants for members first, guests in a follow-up |
| D-W22 | Page permissions model | WikiBundle-native page ACL gated by RbacKit keys and space contexts |
| D-W23 | **Public links allowed by default**? | No: off instance-wide until an admin enables them (per space) |
| D-W24 | **Password required** on public links? | Optional; required when the space says so |
| D-W25 | **Mentions** in public pages | Anonymised ("a team member") |
| D-W26 | **Search-engine indexing** of public pages ever allowed? | Allowed per link by an admin only; off by default |

## Links

- [MilkdownEditorBundle roadmap](https://github.com/nowo-tech/MilkdownEditorBundle/blob/main/docs/ROADMAP.md)
- [Usage](USAGE.md) · [Configuration](CONFIGURATION.md) · [Security](SECURITY.md) · [Changelog](CHANGELOG.md)
- symfony-beacon `specs/178-wiki/tasks.md` (T044–T069), `specs/183-images-object-storage/spec.md`
- [SearchKitBundle](https://github.com/nowo-tech/SearchKitBundle) · [UiKitBundle](https://github.com/nowo-tech/UiKitBundle)
  · [StorageKitBundle](https://github.com/nowo-tech/StorageKitBundle)
