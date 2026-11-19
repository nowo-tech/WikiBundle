# Roadmap

Feature roadmap of WikiBundle after **1.44.0** (2026-11-18), refreshed on 2026-11-18 from a feature gap review of the
code (entities, controllers, REST API, Twig views, TypeScript assets, configuration tree, events) and of both
changelogs. It replaces the roadmap of 2026-11-03. It is a plan, not a commitment: every item becomes a Spec Kit
feature with its own spec, tasks and tests before it is built, and versions may move.

**Goal.** A knowledge base that offers what teams expect from a modern block-based workspace: a page tree, block
editing with handle, block menu, right-click and slash menus, inline comments, embeds, templates, presence, history,
and structured databases (schema pages, properties, table, board, calendar and timeline views, relations, rollups and
formulas). The constraints do not move: **Markdown revisions stay the source of truth**, every page, title, count and
excerpt is filtered **per viewer**, the server render and the exports read the same dialect as the editor,
accessibility (WCAG 2.2 AA) and a strict nonce CSP hold for every new view, and the host (symfony-beacon) owns users,
notifications and storage.

The editor side (blocks, marks, menus, embeds, the database block) is planned in the
[MilkdownEditorBundle roadmap](https://github.com/nowo-tech/MilkdownEditorBundle/blob/main/docs/ROADMAP.md). Items
marked **E** below need that bundle; items marked **W + E** need both. Task ids (T0xx) refer to symfony-beacon
`specs/178-wiki/tasks.md`.

## Table of contents

- [Ground rules](#ground-rules)
- [Where we are (1.44.0)](#where-we-are-1440)
- [Feature status matrix](#feature-status-matrix)
- [Next](#next)
- [Later](#later)
- [Considering](#considering)
- [Deliberate gaps](#deliberate-gaps)
- [Design notes](#design-notes)
  - [Databases](#databases)
  - [Per-page permissions and guests](#per-page-permissions-and-guests)
  - [Public links](#public-links)
  - [Search](#search)
  - [Templates](#templates)
  - [Inline and page comments (T064)](#inline-and-page-comments-t064)
- [Done since the 2026-11-03 roadmap](#done-since-the-2026-11-03-roadmap)
- [Decided](#decided)
- [Open decisions](#open-decisions)
- [Links](#links)

## Ground rules

1. **Backward compatible and opt-in.** New entities come with migrations; new UI with a configuration switch that is
   off or neutral by default, unless a release says otherwise.
2. **Per viewer first.** No title, count, excerpt, diff line, comment, row or search hit of a page the viewer may not
   read, ever. Filtering happens **before** ranking, counting and pagination; caches are viewer-neutral and the
   per-viewer pass runs on top.
3. **Markdown stays the document.** Comments, anchors, database rows, tree order and permissions live in tables, never
   inside the page body; exports and search therefore never leak them.
4. **One render.** The server render, the reader's Markdown, the REST API and every export go through the same dialect
   and the same per-viewer resolvers as the editor.
5. **UiKit for UI primitives.** Reusable widgets (tree, board, calendar, gallery, timeline) go to UiKitBundle so other
   bundles reuse them; existing UiKit output stays byte-identical and needs Beacon's OK before release.
6. **Runtime.** Every feature works in FrankenPHP worker mode with the kernel reset off, holds under concurrent writes
   (REQ-RUNTIME-003) and runs on both standard Symfony and DDD hosts (standard by default, DDD opt-in).
7. **Who builds it.** **W** = WikiBundle, **E** = MilkdownEditorBundle, **U** = UiKitBundle, **S** = StorageKitBundle,
   **SK** = SearchKitBundle, **H** = host (symfony-beacon).

## Where we are (1.44.0)

Shipped and in use, by area (each with its opt-in switch; see [CONFIGURATION](CONFIGURATION.md)):

- **Pages and hierarchy.** Team and user spaces with icons; pages with parent, position and slug; a collapsible page
  tree (UiKit tree, fold state, drag, Alt+arrow moves, *Move to…* form, depth limit, same-space moves); a tree context
  menu (rename, icon, duplicate, move, archive, delete, Shift+F10); new page as a dialog placed in the tree; page
  icons (UX icons or one emoji) with an icon picker; breadcrumbs and in-wiki path; previous / next page; a sub-pages
  chip; archive and unarchive.
- **Editor.** The Milkdown editor (Markdown storage, dialect, per-viewer render), co-editing over Mercure with a
  bounded Yjs log and epochs, link picker with section links, stable and explicit heading ids, *Link to this section*
  on read pages, file cards and sandboxed file previews, *Copy as Markdown*, focusable code blocks.
- **Comments.** Inline threads anchored in the text and page-level threads, replies, reactions, resolve / reopen,
  moderation panel, per-user and per-page limits, a side panel (dock or overlay, phones as a drawer), live updates
  while co-editing, REST endpoints, author export and erasure, mention erasure.
- **History.** Immutable revisions (Markdown or HTML), a timeline with sessions and author labels, named versions,
  line diff, restore as a new revision, *Copy to new page*, retention thinning, write provenance and member badges,
  "former person" for deleted users, saves from a known revision (409 on conflict).
- **Trash.** Delete with the sub-tree, restore by batch, twice-confirmed purge, retention and a purge command, files
  purge event for host storage.
- **Search.** Per-viewer plain-text search on titles and bodies (no Markdown source), excerpts, space scope, excluded
  spaces, a SearchKit provider for the host's Ctrl / ⌘ + K palette.
- **Import and export.** Space import from Markdown export trees (directory or ZIP, two common front-matter layouts,
  auto-detected), space export to Markdown with portable relative links and a `README.md` index, HTML → Markdown
  conversion with a fidelity report.
- **API and events.** A REST API (spaces, pages with Markdown in / out and sections, revisions, diff, restore, move,
  create at a place, archive, comments, files, idempotency keys, OpenAPI 3.1); 28 Symfony events; framework-free host
  ports; links from pages to host objects ("Used in").
- **Sharing and permissions.** Roles per action (16 `WikiAction`s), a space and page-aware access checker,
  `tenant_paths` routes; no per-page grants, guests or public links yet.
- **Layout.** UiKit page contract, page tabs or toolbar, focus mode with a page drawer, reading width options, design
  tokens for tree and page card, host Twig slots, print styles, phone layouts.
- **AI.** Optional Symfony AI questions over the pages the viewer may read, with sources.
- **Privacy and tenancy.** Per-tenant routes, look-alike id hardening, id-mode user references, a private entity
  manager, comment and provenance erasure, AI context filtered per viewer.

## Feature status matrix

**Yes** = shipped, **Partial** = some of it shipped, **No** = not started. *Owner* says who builds the missing part.
*Next / Later / Considering* point at the sections below.

| Area | Feature | Wiki | Editor | Owner | Plan |
|------|---------|------|--------|-------|------|
| Pages | Spaces, nested pages, breadcrumbs | Yes | — | — | — |
| Pages | Page tree with drag, keyboard moves, context menu | Yes | — | — | Lazy children: Later |
| Pages | Page icons (icon or emoji) | Yes | Yes (picker) | — | — |
| Pages | Cover images | No | — | W + S | Later |
| Pages | Collections (grouping between space and pages) | No | — | — | Not planned (D-W33) |
| Pages | Favourites and recently viewed | Yes (1.45, sidebar) | — | W | In the palette: **Next** |
| Pages | Drafts and publish | No | — | W | Later |
| Pages | Review dates (owner, interval, "may be out of date") | No | — | W + H | Later |
| Editor | Block handle, block menu, drag | — | Yes | — | — |
| Editor | Right-click menu | — | Yes | — | — |
| Editor | Slash menu with groups, keywords, full list | — | Partial (flat list, 10 rows) | E | **Next** (E) |
| Editor | Callouts, collapsible sections, colours, icons | — | Yes | — | — |
| Editor | Columns | — | No | E | Later (E) |
| Editor | Reusable sections (read-only transclusion) | No | No | W + E | Later |
| Editor | Table of contents block and side panel | No | No | W + E | **Next** |
| Editor | Find and replace | — | No | E | **Next** (E) |
| Tables | Tables with row / column actions | — | Yes | — | Table toolbar: Later (E) |
| Embeds | Images with caption, size, alignment | Yes | Yes | — | Lightbox: Later (E) |
| Embeds | Host files as cards and previews | Yes | Yes | — | — |
| Embeds | External embeds from an allow-list, link previews | No | No | E + H | **Next** |
| Embeds | Embed a wiki page elsewhere | No | — | W | Later |
| Links | Page links by id, per-viewer titles, section links | Yes | Yes | — | — |
| Links | Backlinks panel ("Linked from") | Yes (1.45) | — | — | — |
| Links | Broken-link and broken-anchor report | No | — | W | Later |
| Mentions | People mentions rendered per viewer | Partial | Yes | W + H | Mention notifications: **Next** |
| Comments | Inline and page comments, reactions, resolve | Yes | Yes | — | — |
| Comments | Inline highlights for readers on the server render | Partial (read-only editor swap) | Partial | W + E | Later |
| Comments | Comments on a whole block or image | No | No | E + W | Later |
| Comments | Suggested edits (reviewable proposals) | No | No | W + E | Considering |
| History | Timeline, named versions, restore, copy to new page | Yes | — | — | — |
| History | Rendered (block / word) diff | Yes (1.45, `history.rendered_diff`) | Yes (`nowo_milkdown_diff`) | — | — |
| History | Read-only view of an old version in the editor | No | Yes (`version_view`) | W | **Next** |
| Collaboration | Co-editing with cursors and names | Yes | Yes | — | — |
| Collaboration | Presence avatars on pages, follow a collaborator | No | No | W + E | **Next** |
| Search | Per-viewer search, palette provider | Yes | — | — | — |
| Search | Full-text index, ranking, filters, highlighting | No | — | W + SK | **Next** |
| Search | Search in comments and attachments | No | — | W | Later |
| Templates | Page templates with variables | No | No | W + E | **Next** |
| Databases | Database pages, properties, table and board views | No | No | W + E + U | **Next** (MVP) |
| Databases | List, gallery, calendar views; row templates | No | No | W + E + U | Later |
| Databases | Timeline view, relations, rollups, formulas | No | No | W + E + U | Later |
| Sharing | Per-page permissions with inheritance | Partial (page-aware checker) | — | W + H | **Next** |
| Sharing | Guests (people with access to some pages only) | No | — | W + H | Later |
| Sharing | Public links (expiry, password, no-index, revoke) | No | — | W + S + H | Later |
| Import / export | Markdown import and export of a space | Yes | — | — | — |
| Import / export | Export of a page or sub-tree, with attachments | No | — | W | **Next** |
| Import / export | HTML and PDF export | No | — | W + H | Later |
| Import / export | Database import from CSV | No | — | W | Later (with databases) |
| API | REST API with OpenAPI | Yes | — | — | Database endpoints with databases |
| API | Signed webhooks | No | — | W + H | Later |
| API | Agent tool surface (search, read, write under the viewer's rights) | Partial (one AI search tool) | — | W + H | Considering |
| Notifications | Watching pages and spaces, digests | No (events only) | — | W + H | **Next** |
| Trash | Trash with restore and purge | Yes | — | — | Trash search, "deleted by me": Later |
| Layout | Focus mode | Yes | — | — | — |
| Layout | Phone layouts (tree drawer, comments drawer) | Partial | Partial | W + E | Later |
| Layout | Offline reading | No | — | W | Considering |
| Accessibility | APG tree, keyboard moves, axe in e2e | Yes | Yes | — | Every new view |
| Tenancy | Per-tenant routes and spaces | Yes | — | — | — |
| Privacy | Erasure and export of a person's data | Yes | — | — | Extend to every new table |
| AI | Questions over readable pages | Yes | — | — | Summaries: Considering |

## Next

Four waves, in order (owner decision D-W32, 2026-11-18); the items of one wave may run in parallel, and wave 4 runs
the databases MVP and per-page permissions in parallel. Items marked **—** have no wave: they are picked up beside the
waves when capacity allows (external embeds wait for the open decisions D2 and D3 in the editor roadmap). One line
each: what it is and why it helps.

| Wave | Area | Item | What and why | Owner |
|------|------|------|--------------|-------|
| 1 | Pages | **Favourites and recently viewed** | Per-user starred pages and recent pages at the top of the tree and in the palette; the fastest way back to the pages people use daily. Never lists pages the viewer lost access to. **Sidebar shipped in 1.45**; the palette (SearchKit provider) is next. | W |
| 1 | Links | **Backlinks panel** | "Linked from" under the page body from the stored page references, per viewer; shows context and keeps the knowledge base connected. **Shipped in 1.45** (own reference index, block `wiki_page_backlinks`). | W |
| 1 | History | **Rendered diff and version view** | Use the editor's `nowo_milkdown_diff()` (block then word level, escaped, per viewer) instead of the line diff, and its read-only `version_view` for old versions; history becomes readable for non-technical users. **Rendered diff shipped in 1.45** (`history.rendered_diff`); the version view is next. | W (+ E shipped) |
| 2 | Templates | **Page templates** | Space and instance templates with fixed `{{variable}}` substitution, seed templates and "start from a template" in the empty page and the tree; consistent pages for meeting notes, runbooks, postmortems. See [Templates](#templates). | W + E |
| 2 | Notifications | **Watching pages and spaces** | Automatic for authors and commenters, manual for anyone; events for replies, edits (daily digest) and moves, delivered by the host. Without this, comments and mentions go unseen. | W + H |
| 2 | Mentions | **Mention notifications** | Typed events when someone is mentioned in a page or comment, only for people who can read it; the host delivers them. Viewer-aware user resolver (CV-I5) first. | W + H |
| 3 | Search | **Full-text index with filters and highlighting** | The database's own full-text index (MySQL FULLTEXT, PostgreSQL tsvector), filters (space, author, dates, status), highlighted excerpts and terms highlighted on open; today search is a literal match. See [Search](#search). | W + SK |
| 4 | Databases | **Databases MVP** | Database pages with a schema, rows as pages, the basic property types, table and board views with filters, sorts and grouping, CSV import / export, the `::database` block; structured data next to the prose it belongs to. See [Databases](#databases). | W + E + U |
| 4 | Sharing | **Per-page permissions** | Levels (full, edit, comment, view, none), inheritance down the tree, *Restrict this page*, *Who has access*, an audit trail; needed before guests and public links. See [Per-page permissions and guests](#per-page-permissions-and-guests). | W + H |
| — | Links | **Table of contents** | A side panel with the page's headings (the same `sections` the REST API returns since 1.40) plus the editor's TOC block; long pages become navigable. | W + E |
| — | Collaboration | **Presence on pages** | Avatars of who is viewing or editing a page (Mercure awareness), with *follow* in the editor; people see each other before they collide. | W + E |
| — | Import / export | **Page and sub-tree export with attachments** | Export one page or a branch, not only a space, with its files in the ZIP; covers "send me this runbook" without admin help. | W + S |
| — | Embeds | **External embeds and link previews** | Allow-listed iframes and bookmark cards rendered by the editor and the server render; the wiki side supplies the host unfurl endpoint config and CSP notes. | E + H (+ W config) |

## Later

| Area | Item | What and why | Owner |
|------|------|--------------|-------|
| Pages | **Lazy tree children and live tree updates** | Collapsed parents load on demand and moves by others appear without reload (ids only on the topic); keeps very large spaces fast and current. | W + U |
| Pages | **Cover images** | An optional cover per page from host storage, alt text or decorative, vertical offset; gives landing pages identity. | W + S |
| Pages | **Drafts and publish** | Draft revisions on top of the published one, visible to editors only, excluded from others' search; lets people work in the open without half-finished text reaching readers. | W |
| Pages | **Review dates** | Owner, interval, a "may be out of date" banner, *Reviewed* and a *Due for review* list; keeps a knowledge base trustworthy. | W + H |
| Pages | **Space home pages** | Pinned pages, description and recent changes on a space's landing page; a better first screen than a bare tree. | W |
| Editor | **Reusable sections** | A read-only `::include{page heading}` resolved per viewer, edited at the source; one procedure reused in many pages without copies. | W + E |
| Links | **Broken-link and broken-anchor report** | Per space, the links to pages or sections that no longer exist; ids are stable but anchors and archived pages still break. | W |
| Comments | **Inline highlights on the server render** | The anchored read view (canonical text on both sides, a DOM quote resolver, CSS Custom Highlight API) so every reader sees inline threads without the editor. See [Inline and page comments](#inline-and-page-comments-t064). | W + E |
| Comments | **Live comments on the read view** | A read-only Mercure topic per page for viewers who may see comments, without co-editing. | W |
| Comments | **Comments on blocks and images** | Threads anchored to a whole block (image, embed, table, database view) rather than a text quote. | E + W |
| Search | **Search in comments and attachments** | Comment bodies in a separate per-viewer index, then extracted attachment text; finds decisions made in threads. | W |
| Databases | **Databases 2** | List, gallery and calendar views, files and phone properties, row templates, bulk edit, live cell updates, row comments. | W + E + U |
| Databases | **Databases 3** | Timeline view, relations, rollups, formulas in a sandboxed grammar, per-row visibility, type migrations of complex types. | W + E + U |
| Sharing | **Guests** | People outside the space with grants on chosen pages only; they see only those pages in tree, search and palette. | W + H |
| Sharing | **Public links** | Read-only links to a page (and optionally its sub-pages) with expiry, password, no-index and revoke, served without app chrome. See [Public links](#public-links). | W + S + H |
| Sharing | **Embed a wiki page elsewhere** | The public link in an `embed` mode with `frame-ancestors` from a host allow-list. | W + H |
| Import / export | **HTML and PDF export** | Rendered per viewer, with print styles; PDF through a host-provided renderer. | W + H |
| Import / export | **Richer import** | Database CSVs, link rewriting of `other.md#id` back to `[[wiki:<uuid>#id]]` (known gap of 1.40), collections, a fidelity report in the web UI. | W |
| API | **Signed webhooks** | HMAC-signed deliveries of the wiki events with retries, host-configured endpoints, SSRF protections; integrations without polling. | W + H |
| API | **Database endpoints** | Schemas, rows and views over the REST API with the same per-viewer rules. | W |
| Trash | **Trash search and "deleted by me"** | Filter and search the trash, bulk restore; finds the one page someone deleted last week. | W |
| Layout | **Phone polish** | Touch-friendly tree moves, bottom sheets for menus, comments and database views on small screens. | W + E + U |
| AI | **Citations to headings** | AI answers link to the section that supports them (section ids exist since 1.40). | W |

## Considering

Not yet scheduled; accept, reshape or drop each one.

| Area | Item | What and why | Owner |
|------|------|--------------|-------|
| Pages | **Page analytics** | Views, viewers (opt-in), popular and stale pages; feeds review dates. Aggregates only by default (privacy). | W + H |
| Pages | **Translations of a page** | Links between the same page in several locales; multilingual wikis. | W |
| Pages | **Related-pages graph** | An accessible graph with a list fallback of links around a page. | W |
| Comments | **Suggested edits** | A suggesting mode that produces a proposed revision reviewed block by block; no character-level tracking in a live Yjs document. | W + E |
| Mentions | **Dates and reminders** | `:date[…]` chips in the editor and reminders delivered by the host; due dates for tasks in pages. | E + H |
| API | **Agent tool surface** | Search, read and write pages as tools for the host's AI agents (beyond the one Symfony AI search tool), under the viewer's rights and with write provenance. | W + H |
| Layout | **Offline reading** | A service-worker cache of recently viewed pages, purged on logout, per viewer. | W |
| AI | **Summaries** | Host-provided summaries of long pages; no keys or prompts in the browser. | W + H |
| Databases | **Host data sources** | Expose host records (for example TaskBoard tasks or issues) as read-only database sources with the host's permissions. | W + H |

## Deliberate gaps

- **Collections** (D-W33): no grouping level between space and pages; the tree plus per-page permissions cover it.
- **Moves across spaces** (D-W1): pages move within one space; a cross-space move is an export and import.
- **Synced editing of one block in many pages:** Markdown has no shared nodes and permissions differ per page; the
  read-only reusable section covers the need.
- **Character-level tracked changes** inside a live Yjs document: proposals are reviewed per block instead.
- **Data outside the Markdown body:** comments, anchors, rows and permissions never go into the page Markdown.

## Design notes

Short constraints for the large items. Each still gets a full spec before it is built.

### Databases

Owner decision (2026-11-03): **in scope**.

- **Model.** A database is a page with a schema (`WikiDatabase`: properties, views, settings). Full-page databases
  are tree pages; inline ones belong to a host page and are shown by the editor's `::database{id view}` block. Each
  **row is a page** with properties and an optional Markdown body, so rows get revisions, comments, links and
  templates for free.
- **Property types.** MVP: title, text, number, select, multi-select, status, date (range), person, checkbox, URL,
  e-mail, created / edited time and by. Then files and phone. Then relation, rollup, formula.
- **Storage (D-W18).** One JSON properties column per row plus a narrow typed index table
  (`database_id, property_id, row_id, v_text, v_num, v_date, v_bool`) so filters and sorts use real B-tree indexes on
  MySQL and PostgreSQL; multi-value types get one index row per value.
- **Schema changes.** Add, rename, delete, reorder; type changes run a per-database migration job with a preview of
  values that will not convert, a `_lost` shadow and schema versions for undo.
- **Views.** Table and board first, then list, gallery and calendar, then timeline. Each view stores filters (AND /
  OR groups), sorts, grouping, visible properties and widths, and has a shareable URL. Board, calendar, gallery and
  timeline become UiKit components with keyboard alternatives for every drag.
- **Formulas (D-W19).** A small, own expression grammar (arithmetic, comparisons, `if`, string, date and list
  functions, `prop("…")`) parsed into an AST and interpreted with step, depth and output limits; no `eval`, no PHP
  callables, no object access.
- **Per viewer.** Relations to unreadable rows render *Content not visible*; rollups count readable rows only;
  per-row visibility filters before counting.
- **Render and export.** The server renders a view per viewer as an accessible table or list; exports write CSV plus a
  link; search indexes rows as their own documents.
- **Limits** (configurable): 200 properties, 100 000 rows, 50 views, 10 filters per view, 10 KB per cell, cursor
  pagination.

### Per-page permissions and guests

- **Levels:** full access, can edit, can comment, can view, no access. **Grantees:** users, groups and roles.
- **Model (D-W22):** a WikiBundle page ACL (`wiki_page_grant`, a `restricted` flag) gated by RbacKit keys and space
  contexts; the nearest restricted ancestor decides, otherwise the space level; *no access* wins.
- **Speed:** effective levels resolved in batches per tree or result page, memoised per viewer per request (reset at
  every request, Messenger and console boundary), a per-space "has restrictions" shortcut.
- **Everywhere:** page view, tree, search (before ranking), backlinks, mentions, exports, history and diffs, comments,
  rows and relations, collaboration topics and the AI context, each with marker-string tests.
- **Moves** that change who can see a page show the delta before confirming. Guests follow members (D-W21).

### Public links

- Per page, optionally with sub-pages; on / off, expiry, optional password (rate limited), no-index by default,
  comments and export off; tokens of 128+ random bits stored hashed, revocable; an access log per the host's privacy
  policy.
- Read-only render through the same safe renderer: links to non-shared pages as plain text, mentions anonymised
  (D-W25), files through short-lived signed URLs, strict CSP, no session cookie, `Referrer-Policy: no-referrer`.
- Off instance-wide until an admin enables it per space (D-W23); an admin list of every link with bulk revoke.

### Search

- **Index (D-W13):** the database's own full-text index by default; an adapter interface for an external engine on
  large instances, with the readable ids passed into the query, never post-filtered.
- **Query:** phrases, exclusion, prefix on the last term, accent and case folding, title boost; length and term caps,
  a per-user rate limit, no stored query log unless the host opts in (D-W15).
- **Highlighting** from the per-viewer-safe plain text (`content_text`), escaped, `<mark>` only; terms highlighted on
  open with the editor's find bar.
- **Filters** in the URL: space, author, last editor, date ranges, status, tags and database properties later.

### Templates

- `WikiTemplate`: space or instance scope, title, icon, description, Markdown body, default properties for database
  rows later, its own revisions.
- A new page **copies** the body at creation; later template edits never change existing pages.
- Variables (D-W12): fixed `{{name}}` replacement over an allow-list (`date`, `time`, `author`, `space`, `title`) plus
  host providers, values Markdown-escaped; no Twig, no `eval`, no expressions; unknown variables stay as text.
- Create / edit templates needs `manage` (instance templates: admins); using one needs create (D-W11).
- Seeds (D-W10): meeting notes, postmortem, runbook, decision record, onboarding, how-to, in the 7 locales.
- Editor part: the template picker, a *Template* slash group, a repeatable insert block and variable chips (Milkdown).

### Inline and page comments (T064)

Shipped in 1.16 (spec [`specs/002-comments`](../specs/002-comments/spec.md), contract
[COMMENTS-CONTRACT](COMMENTS-CONTRACT.md)). Still open:

- **Anchored server-rendered read view.** One canonical text model on both sides (`data-nowo-block` /
  `data-nowo-atom` markers from the PHP renderer, a shared fixture corpus), a read-only DOM quote resolver painting
  with the CSS Custom Highlight API, new threads from a DOM selection, anchors refreshed only by the editor, quotes
  blanked for threads that do not resolve in the viewer's text.
- **Re-anchor or blank quotes on page save** and drop hidden-reference placeholders from the plain text.
- **Live comments on the read view** through a read-only topic.
- **Comments on blocks and images**, then **suggested edits** (Considering).

## Done since the 2026-11-03 roadmap

Moved out of the plan because they shipped (version in brackets):

- Audit residuals CW-I7, CW-I9, CW-I10, CW-I12, CV-I9 (1.13.1).
- Page tree with fold state, drag, keyboard moves, *Move to…*, depth limit, `nowo_wiki.page.moved` (1.14); tree
  context menu (1.39, 1.41); new page as a dialog placed in the tree (1.35); collapsible tree on the space home and
  tree rail (1.29, 1.38).
- Trash with sub-tree delete, batch restore, purge and retention (1.14); files purge event (1.20); duplicate (tree).
- Version history: timeline, named versions, *Copy to new page*, retention thinning (1.15); "former person" (1.26);
  write provenance and member badges (1.34).
- Comments: inline and page threads, side panel, reactions, moderation, limits, erasure (1.16–1.17, 1.21, 1.29, 1.31).
- *Copy as Markdown* (1.15); page tab icons (1.18); host hooks: quota event, navigation scope, slots, moderation
  panel, tree tokens (1.21); page files card (1.20).
- REST API with OpenAPI (1.24), create at a place (1.35), framework-free host ports and DDD host support (1.36),
  transaction runner and conflict answers (1.37, 1.42).
- Space icons (1.32), page icons (1.38), icon picker and emoji icons (1.39); previous / next page (1.39).
- Link picker, stable and explicit heading ids, section links and labels (1.40).
- Focus mode with the page drawer (1.41, 1.44); page-card tokens (1.43); SearchKit palette provider (1.44).
- Wave 1 (D-W32): favourites and recently viewed in the sidebar, backlinks panel with the bundle's own reference
  index, rendered revision diff (1.45).

## Decided

| # | Decision | Outcome (date) |
|---|----------|----------------|
| D-W1 | Allow moves **across spaces**? | No: moves stay within one space (2026-11-03) |
| D-W2 | Maximum tree **depth** | 10 levels, `tree.max_depth` (2026-11-03) |
| D-W4 | **Trash retention** period | 30 days, configurable (shipped 1.14) |
| D-W5 | Who can **purge** the trash | The manage roles in the 1.x mapping (shipped 1.14) |
| D-W6 | **Revision retention** | Keep named, current and newest revisions; thin older autosaves to one per session and day (shipped 1.15) |
| D-W7 | **Named versions** for all editors? | Yes (shipped 1.15) |
| D-W27 | Sibling **ordering** storage | Sparse integers in the existing `position` column (2026-11-03) |
| D-W28 | Where the **fold state** lives | The session by default; `tree.state_store` for a per-user store (2026-11-03) |
| D-W29 | What a **delete** takes | The page and its whole sub-tree, restored together (2026-11-03) |
| D-W30 | Pages **archived before** a delete | Stay archived after a restore (2026-11-03) |
| D-W31 | A restored page whose **parent is gone** | Comes back at the top level (2026-11-03) |
| — | Databases with schema pages, views, relations, rollups and formulas | In scope (owner, 2026-11-03) |
| D-W32 | **Order of the Next items** | Four waves: (1) favourites and recents, backlinks, rendered diff; (2) templates, watching and notifications; (3) search; (4) the databases MVP and per-page permissions in parallel (owner, 2026-11-18) |
| D-W33 | **Collections** | None: the tree plus per-page permissions cover the need (owner, 2026-11-18) |

## Open decisions

Still open on 2026-11-18, among others: the template questions D-W10, D-W11 and D-W12, and neutral aliases for the import
format names. Recommended answers are the defaults until the owner decides.

| # | Decision | Recommendation |
|---|----------|----------------|
| D-W3 | Show **favourites** and **recent** sections in the sidebar | Yes, both collapsible (1.45 is built this way) |
| D-W8 | Who can **comment**: readers or editors only? | Readers with the comment right, granted to members by default |
| D-W9 | Keep **resolved threads** forever? | Keep; purge only with the page |
| D-W10 | Which **seed templates** ship | Meeting notes, postmortem, runbook, decision record, onboarding, how-to |
| D-W11 | Can **readers** see the template gallery? | No (create right only) |
| D-W12 | **Template variables** | `date`, `time`, `author`, `space`, `title` + host providers |
| D-W13 | **Search engine** | Built-in database full-text with an adapter interface |
| D-W14 | Comments and attachments **searchable** first? | Comments yes (per viewer), attachments later |
| D-W15 | Keep **search history**? | Browser-only recents, no server log |
| D-W16 | **Notification defaults** | Mentions and replies on; page edits as a daily digest for watchers |
| D-W17 | **PDF export** renderer | Host-provided, optional |
| D-W18 | Database **storage model** | JSON properties + typed index table |
| D-W19 | **Formula language** | Own restricted grammar |
| D-W20 | **Views first** | Table + board in the MVP; calendar next |
| D-W21 | **Guests** in the first permissions release? | No; member page grants first, guests in a follow-up |
| D-W22 | Page permissions model | WikiBundle page ACL gated by RbacKit keys and space contexts |
| D-W23 | **Public links allowed by default**? | No: off until an admin enables them per space |
| D-W24 | **Password required** on public links? | Optional; required when the space says so |
| D-W25 | **Mentions** on public pages | Anonymised ("a team member") |
| D-W26 | **Search-engine indexing** of public pages | Per link by an admin only; off by default |
| — | **Neutral aliases for the import / export format names** (`--format` values, `WikiInterchangeFormat` cases, UI strings) | Open; the names stay as they are for now |

## Links

- [MilkdownEditorBundle roadmap](https://github.com/nowo-tech/MilkdownEditorBundle/blob/main/docs/ROADMAP.md)
- [Usage](USAGE.md) · [Configuration](CONFIGURATION.md) · [API](API.md) · [Security](SECURITY.md) ·
  [Changelog](CHANGELOG.md)
- symfony-beacon `specs/178-wiki/tasks.md` (T044–T069), `specs/183-images-object-storage/spec.md`
- [SearchKitBundle](https://github.com/nowo-tech/SearchKitBundle) · [UiKitBundle](https://github.com/nowo-tech/UiKitBundle)
  · [StorageKitBundle](https://github.com/nowo-tech/StorageKitBundle)
