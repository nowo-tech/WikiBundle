# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[1.16.1] - 2026-11-04](#1161---2026-11-04)
- [[1.16.0] - 2026-11-04](#1160---2026-11-04)
- [[1.15.1] - 2026-11-04](#1151---2026-11-04)
- [[1.15.0] - 2026-11-04](#1150---2026-11-04)
- [[1.14.1] - 2026-11-03](#1141---2026-11-03)
- [[1.14.0] - 2026-11-03](#1140---2026-11-03)
- [[1.13.2] - 2026-11-03](#1132---2026-11-03)
- [[1.13.1] - 2026-11-03](#1131---2026-11-03)
- [[1.13.0] - 2026-11-03](#1130---2026-11-03)
- [[1.12.0] - 2026-10-31](#1120---2026-10-31)
- [[1.11.1] - 2026-10-30](#1111---2026-10-30)
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

- **Milkdown read view** (`editor.type: milkdown`; reported by Symfony Beacon with Milkdown 1.6.0):
  - **Footnotes** (`[^1]`) render instead of showing as literal text: CommonMark's `FootnoteExtension` is enabled
    (unless `nowo_milkdown_editor.markdown.footnotes: false`; `max_footnote_refs` and case-insensitive labels as in
    the Milkdown renderer, inline `^[…]` footnotes stay text). Ids never carry the user's label: they are
    `nowo-fn-<salt>-<n>` / `nowo-fn-ref-<salt>-<n>[__<k>]` (`<salt>`: 8 hex of a hash of the Markdown, so a page and
    its comments never share an id), the back-links are kept and the footnote content goes through the same
    allowlist sanitizer.
  - **Heading ids**: headings get `id="nowo-h-<slug>"` with the editor's slugs (Milkdown `HeadingIds::slug()`:
    lower-case, runs of non letters/marks/digits become `-`, 80 characters, `section` when empty, `-2`, `-3`… for
    duplicates), so anchors and in-page links written in the editor work in the read view. `WikiHeadingIds` keeps the
    next suffix per slug (linear; Milkdown's `HeadingIds::slugs()` retries from `-2` for every duplicate, so 9 999
    identical headings took ~4 s per view, audit EP-L1) and gives ids to the first 1 000 headings of a document only.
  - **Footnotes with a non-ASCII label** (`[^Ä]: Note`) keep their whole text: league/commonmark's definition parser
    advanced by bytes instead of characters and ate the first characters of the note (`ote`); `WikiFootnoteStartParser`
    replaces it (audit EP-I6).
  - **Table column alignment** (`:---:`) is kept as the class `nowo-wiki-align--left|center|right` (styled by
    `wiki.css`); no `align` attribute and no inline style (CSP-safe).
  - **Leaf directives without a renderer** (`::beacon{…}`, any `::name{…}`) fall back to readable text (the label, or
    `name · key: value…`), or to a link when a `url` / `href` / `link` attribute holds a safe URL (same-origin `/path`
    or `https://` without userinfo: the Milkdown link policy and the UI kit URL policy must both accept it). Every
    value is escaped; host directive renderers still win for the viewer. A same-origin path with `//`, a `.` / `..`
    segment or an encoded slash (`/.//host`, `/%2F%2Fhost`) is not linked (audit EP-I2).
- **Sanitizer** (`WikiHtmlSanitizer`): `id` is kept only as one of those anchors — `nowo-h-<slug>` on `h1`…`h6`
  (letters, marks, digits and single hyphens, at most 90 characters), `nowo-fn-<8 hex>-<n>` on `li`,
  `nowo-fn-ref-<8 hex>-<n>[__<k>]` on `sup` — and dropped everywhere else, even when a host allowed `id` through
  `sanitizer.extra_allowed_attributes` (DOM clobbering: `__proto__`, `location`, host ids); listing `id` there now
  logs a deprecation at container build (audit EP-I4). `name` joins `FORBIDDEN_ATTRIBUTES`: a host can no longer allow
  it (it clobbers `document.<name>`, audit EP-I3). `align` and general `style` stay refused.
- The PHPUnit configuration sets `memory_limit` to 512M (the full suite outgrew the default 128M, audit EP-I9).

## [1.16.1] - 2026-11-04

The final Info items of the 1.16.0 security audit (appendix EI).

### Added

- **Moderator thread delete** `DELETE …/comments/threads/{id}` (`WikiAction::ModerateComments`, 204): a whole thread
  with its comments and reactions, event `nowo_wiki.comment.thread_deleted` (EI-I16).
- `comments.limits.user_threads_per_page` (default 50): open threads one user may start on one page (EI-I16).
- `comments.limits.total_threads_per_page` (default 1000): every thread of a page, resolved ones included (EI2-L1).
- `WikiCommentAuthorRepositoryInterface` (per-author queries of the comment store), implemented by the Doctrine store
  (EI2-I5).
- `WikiCommentService::visibleQuote()`: the quote a notification may show, blank once the text left the page
  (EI-I15).
- `WikiCommentService::exportAuthorData()`: the user's comments, reactions and the threads they started or resolved;
  `wiki:comments:erase-user --export` prints it. Event `nowo_wiki.comment.author_erased`
  (`WikiCommentAuthorErasedEvent`) after an erasure (EI-I17).

### Changed

- `comments.limits.threads_per_page` counts **open** threads only: resolved threads no longer lock a page (EI-I16).
- The server-rendered comments list shows the opening comment and the latest 19 of each thread, with "N earlier
  comment(s) in the editor" (EI-I18, EI2-I6).
- Open threads are listed first: resolved threads can no longer push open ones out of the 2000-thread window of
  `GET /threads` and the server list, and `truncated` is set only when threads were left out (EI2-L1).
- Reopening a thread checks the open-thread caps, like starting one (EI2-I1).
- The `wiki:comments:erase-user` confirmation names the resolved account and the number of comments (EI2-I4).
- `wiki:comments:erase-user` asks for confirmation unless `--force` or `--no-interaction` (EI-I17).

### Fixed

- **iOS Safari:** the sidebar page tree (`.nowo-wiki-sidebar__body`) uses `max-height: calc(100dvh - 8rem)`, with
  `100vh` kept as the fallback, so it no longer runs under the browser bars.

## [1.16.0] - 2026-11-04

Comments panel, avatars, plain-text search.

### Added

- **Page tabs (B-Q1).** The read and edit views get a tab row under the title: *Page* / *Edit* / *History* (UiKit
  `PageHeader::tabs`, an `AreaNav` rendered by the UiKit `tabs` partial at the secondary level; the current view has
  `aria-current="page"`; Edit and History only with those rights). It is always there, comments or not.
- **Comments (opt-in, `nowo_wiki.comments.enabled`).** Inline threads anchored in the text (Milkdown 1.6 anchor v1,
  validated with `CommentAnchor`) and page-level comments, in a right-side panel toggled from the end of the tab row:
  - the toggle is the UiKit side-panel toggle as an icon button (`tabler:message-circle`) with a visible count badge
    (open threads the viewer may read) that is also part of its accessible name; optional `comments.shortcut`
    (`aria-keyshortcuts`), none by default;
  - the panel is the UiKit 2.25 side panel (an inline column >= 768 px, a full-height drawer <= 767 px with the page
    behind inert), named by its heading, Escape / close / toggle, focus in on open and back on close, polite live
    region; the Milkdown thread component mounts into it (`panel: 'container'`), with two-way highlight / thread
    focus and the `comments-count` badge;
  - editors get the page as a read-only Milkdown mount on the read view (`nowo_milkdown_view()`) and the thread
    component in the edit form; **other commenters never receive the Markdown source**: their panel lists the open
    threads rendered by the server (inline threads with their quote and a muted *Reply from the editor* line, a link
    to the editor for viewers who may edit) and the page-comment form.
    `comments.read_view_source: true` (opt-in) gives them the mount and the source. Without JavaScript the toggle is
    a link to `?comments=1`, the panel lists the page-level threads and a `WikiCommentType` form posts to
    `…/comments/new`;
  - open / closed state per user: `WikiPanelStateStoreInterface` (the session by default, `comments.state_store` for a
    host store), `POST …/spaces/{spaceSlug}/panel-state`; `?comments=1|0` overrides it for one request;
  - endpoints under the route key `page_comments` (`…/pages/{pageSlug}/comments/…`, the Milkdown 6.1 contract v1.2:
    `updatedAt` / `base` and 409, `resolvedBy` / `resolvedAt`, deleted comments, `truncated`), JSON, same origin,
    `X-CSRF-Token` per page, bodies <= 16 KiB, Markdown <= 10 000 characters, `comments.rate_limiter` (required, per
    user across pages) and optional `comments.page_rate_limiter`, caps in `comments.limits` (422 `limit_reached`);
  - viewers who may not edit get only the quote of an anchor, blanked once the text left the page;
    `renderBodyForNotification()` (viewer-neutral) for notification bodies; privacy export / erasure
    (`exportAuthor()`, `eraseAuthor()`, `wiki:comments:erase-user`); the trash purge deletes comment rows explicitly;
    every response with the panel is `Cache-Control: private, no-store`;
  - rights `WikiAction::ViewComments`, `Comment`, `ResolveComment`, `ModerateComments`: outside `grants()`, denied by
    the 1.x legacy mapping (default deny); authors edit and delete their own comments with `Comment`;
  - tables `<prefix>_comment_threads`, `<prefix>_comments`, `<prefix>_comment_reactions` (left out of the schema when
    off; threads follow their page to the trash and are deleted with it);
  - events `nowo_wiki.comment.created` / `.replied` / `.edited` / `.deleted` / `.resolved` / `.reopened`
    (`WikiCommentEvent`: page, thread, comment, actor, mentions; never a body);
  - with co-editing, live updates by thread id on the private topic `<collab topic>/comments`, granted with the
    co-editing topic in one subscriber cookie (`WikiCollabTopicsAuthorizerInterface`).
- **Avatars** (avatar contract v1.1 §10.2): `Nowo\WikiBundle\Avatar\WikiUserAvatarResolverInterface` (keyed by
  `WikiAvatarKey::of()`, size 32; `NullWikiUserAvatarResolver` by alias), batched once per response. History authors
  and comment authors (`author.avatarUrl`, `resolvedBy.avatarUrl`) get the picture through the UiKit `_avatar`
  partial and URL policy, **only beside a real name**: with `history.show_names: false` or a name that fails
  `isSafeName()` nothing is resolved nor emitted. `WikiAuthorLabel::present()` / `presentAll()`.
- **Plain-text search.** `content_text` column on the revision (public API), filled on save from the sanitized
  HTML; `Nowo\WikiBundle\Search\WikiPlainText::fromHtml()` / `fromMarkdown()`; `wiki:search:backfill-text`
  (`--all`, `--batch-size`, `--dry-run`).

### Changed

- **Search matches the plain text** of the current revision instead of its Markdown source (or HTML): directive
  markers and attribute values (`:::callout{icon="bulb" type="info"}`), Markdown punctuation and tags are no longer
  hits. Revisions not backfilled yet are still matched the 1.15 way. Excerpts come from the same text.
- Requires `nowo-tech/ui-kit-bundle` `^2.26` (side panel, avatar partial and URL policy); dev requirement
  `nowo-tech/milkdown-editor-bundle` `^1.6`.
- `WikiAuthorLabel` is always registered (comments use it too) and takes the avatar memo.
- The route loader passes `#[Route]` defaults through (the optional `{path}` of `page_comments`).

## [1.15.1] - 2026-11-04

Empty space export, export index, front matter round trip

### Fixed

- **Exporting a space with no visible pages no longer returns 500** (reported by symfony-beacon). ZipArchive writes no
  file for an archive without entries, so the web export (`nowo_wiki_space_export`) ended in
  `FileNotFoundException: ".../wiki-export-<slug>-<hex>.zip" does not exist`, and `wiki:export --zip` reported success
  without writing the archive. Every export now holds a `README.md` index at its root (Outline-style): the space
  title, the export date (UTC) and the exported pages as nested relative links, or a line saying the space has no
  pages visible to the exporter. It lists exactly the exported pages (never the title of a page the exporter may not
  read), escapes titles for Markdown link text and is translated in the `NowoWikiBundle` domain (`wiki.export.readme.*`,
  all seven locales).
- `WikiArchiveHelper::createZipFromDirectory()` throws a `RuntimeException` when the archive was not written instead of
  returning a path that does not exist.
- `wiki:export` removes its temporary directory on every path and reports a failed export (`Export failed: ...`,
  exit code 1) instead of throwing.
- Imports skip the `README.md` index of a wiki export (first line `<!-- nowo-wiki:export-index -->`), so re-importing
  an export does not create a README page (nor, for Notion, nest every top-level page under it). Only `README.md` at
  the export root is the index; the marker anywhere else is page content. The import report counts the index as
  skipped (`README.md: wiki export index, skipped.`).
- The index name is reserved (audit DV-L1 / DV-I4): an Outline page whose slug is `readme` (`readme.md`, the same file
  as `README.md` on case-insensitive filesystems) and a top-level Notion page titled `README.md` (its folder) get the
  first free `-N` suffix (`readme-1.md`, `README.md-1/`); links and the index follow, and the front matter keeps the
  slug. The index never replaces a `README.md` that is not an earlier index (the export fails before writing any
  page), and a failed index write raises a path-free `RuntimeException`.
- Index titles stay on one line even with invalid UTF-8 (audit DV-I3).
- **Exported front matter parses back** (audit DV-I8, since the first export): `WikiFrontMatterParser::serialize()` wrote
  the closing `---` right after the last value (`wiki_parent: ''---`), so `parse()` found no front matter. Re-importing
  an Outline export lost every title, slug and parent and imported the YAML as page text. The delimiter is on its own
  line again.

## [1.15.0] - 2026-11-04

Version history timeline, named versions, copy to new page, retention; Copy as Markdown

### Added

- **"Copy as Markdown" on the page view** (`editor.milkdown.copy_markdown`, opt-in, Milkdown 1.5): a button that copies
  the page's stored Markdown. It is shown only to users who may edit the page, because the raw source holds page ids,
  mention handles and directive attributes that the per-viewer render hides (Milkdown audit DN-I2). That response is
  `Cache-Control: private, no-store` (audit DR-I1).
- **History timeline** (`history.timeline`, opt-in, 1.15): the version history gains the columns *Version name*,
  *Author* and *Saves in session*. A session is consecutive saves by the same author less than
  `history.session_minutes` apart (default 30); a named version is a session of its own. Authors show as the same
  opaque, per-page "Editor xxxx" as co-editing, or as their display name with `history.show_names` (only names that
  say nothing about the login).
- **Named versions** (D-W7: every editor): *Save version* on the page view names the current revision (one name per
  revision, 120 characters; saving again renames it). Stored in `<prefix>_revision_labels`, which is left out of the
  schema when the timeline is off.
- **Copy to new page**: a row action of the history creates a top-level page with the content of that revision
  (title "<title> (vN)", a free slug, both cut to the column limits; it takes the space's top-level visibility by
  design), for users who may create pages (`canCreate()` and `WikiAction::Create` on the
  space) and see the history.
- **Revision retention** (D-W6): `wiki:history:thin [--dry-run]` thins revisions older than `history.retention_days`
  (default 30) to the newest one per session and day. Named versions, the current revision and the newest revision
  are never removed; revision numbers are never reused.

## [1.14.1] - 2026-11-03

Collaboration cost: counters on the document row, split limiters

### Fixed

- **Co-editing updates are cheap per keystroke** (Beacon audit, High). The update endpoint used to read the size of the
  page's whole log twice per request (`SUM(LENGTH(data))` over the LONGTEXT rows) around the insert. Now:
  - The document row keeps `update_count`, `log_bytes`, `last_seq` and `snapshot_seq`, written in the same transaction
    as each insert, compaction and reset.
  - The caps and the append are one locked write (`WikiCollabCountingStoreInterface::appendWithin()`: three
    statements).
  - The Mercure relay goes out after the commit.
  - A host store without the new interface keeps working as before.
  - A log reset runs in one transaction that locks the document row first, in the same order as an append or a
    compaction (audit DQ-L1): an append can no longer land between the delete and the counter reset, and the two
    cannot deadlock.
- **Separate budgets for updates and presence.** `collaboration.update_rate_limiter` and
  `collaboration.awareness_rate_limiter` (e.g. `limiter.wiki_collab_update` / `limiter.wiki_collab_awareness`).
  Without them, `rate_limiter` keeps limiting every request.

## [1.14.0] - 2026-11-03

Outline-style page tree and trash

### Added

- **Outline-style page tree** (`tree.enabled`, opt-in, T062 / T049): the sidebar renders the UiKit 2.24 `tree`
  (WAI-ARIA treeview: one tab stop, arrows, Home / End, type-ahead, `*`) with disclosures, the current page's branch
  unfolded and the fold state of each user and space applied by the server (no flash; kept in the session unless
  `tree.state_store` names your service). Users who may edit move pages by dragging (drop line, *inside* / *between*),
  with **Alt+↑ / ↓ / ← / →** (reorder, nest under the previous page, move out) or with the **Move** form of a page
  (parent + position; the single-pointer and no-JavaScript alternative). Moves are announced in a live region.
- **Moves on the server** (`WikiPageMover`): same space, no cycles, `tree.max_depth` (default 10), edit rights on the
  page, its old and its new parent; one transaction that locks the space; sparse sibling positions (one row written
  per move; siblings renumbered once when they share a position). `nowo_wiki.page.moved` (`WikiPageMovedEvent`:
  page, from, to, index, actor, time) is the audit hook. Endpoints: `POST …/tree/move` and `POST …/tree/state` (JSON,
  same origin, CSRF token per space, 4 KB bodies), `GET|POST …/pages/{pageSlug}/move`. Without the tree none of them
  is registered and every page is byte-identical to 1.13.
- **Audit DI (before the first release of the tree):** the tree endpoints and the Move form need `View` on the page
  and on every ancestor, like the sidebar (DI-L1); the index of a move and the position it answers count the siblings
  the user can see (DI-I1); a page moved by someone else between the check and the lock is refused as `stale`
  (DI-I2, compared with the very parent whose `Edit` was checked, DI-I6); a page moves with its whole sub-tree, descendants the actor cannot see included, whose readers become those
  of the new branch (DI-I3, documented).
- **Trash** (`trash.enabled`, opt-in, T065):
  - *Delete* on a page moves it to the space's trash **with its whole sub-tree**, behind a confirm dialog. The pages
    are archived, so the tree, search, page links, mentions, backlinks, the export and the AI treat them as gone.
  - The trash lists each deletion with *Restore*, which brings the page back with its sub-pages (pages archived
    before stay archived; a page whose parent is gone meanwhile goes to the top level).
  - *Delete for good* opens a confirmation page, then asks a second confirm. It removes the pages with their
    revisions, host-object links and attachments (files included).
  - `wiki:trash:purge` purges deletions older than `trash.retention_days` (default 30); `--dry-run` counts them all.
  - Restore and purge run under the space lock and read the deletion again, so a purge never deletes pages a restore
    brought back; attachment files are unlinked after the commit (audit DO-M1, DO-L1). A deletion holding pages the
    purger cannot see needs `WikiAction::Manage` to be purged (DO-I1).
  - Rights: `WikiAction::Delete` and `WikiAction::Purge`, asked from your space checker. The 1.x mapping is the
    archive roles to delete and the import / manage roles to purge.
  - Events: `nowo_wiki.page.trashed`, `nowo_wiki.trash.restored` and `nowo_wiki.trash.purged`.
  - Without the trash nothing changes: no routes, no table (`<prefix>_page_trash` is left out of the schema) and no
    action.
- Requires `nowo-tech/ui-kit-bundle` ^2.24 (tree component).

### Changed

- **`ext-iconv` is required** (audit DA-I8): `show_names` transliterates names with iconv and, without it, falls back
  to the opaque label (fail closed).
- **A compaction of a purged log is refused with 409 `log_reset`** (audit DA-I9), like an append: the store no longer
  recreates the document.

## [1.13.2] - 2026-11-03

Pages under a hidden parent stay hidden on every read path

### Security

- **A page under a hidden parent is hidden too, on every read path** (hardening; found in the 1.14 tree audit,
  DI-L1). Until now the page view, history, diff, edit, archive / unarchive / restore, attachments (upload and
  download), co-editing, search and the AI answers checked `View` on the page only, while the sidebar tree, the export
  and the page-link / mention resolver already required `View` on every ancestor. One rule now
  (`WikiPageVisibility::isReadable()`, `WikiSpaceGuard::isPageReadable()` / `requireReadablePage()`): `View` on the
  page and on every ancestor; otherwise 404, like a missing page, and the hit is left out. A parent cycle in the data
  reads as hidden. Hosts whose checker grants `View` on a page but not on its parent will see that page disappear for
  those users, as it already did from their tree.
- **Import respects the reader rule** (audit DJ-L1): pages the importing user may not read are never matched, updated
  (`update_existing`) or used as a `wiki_parent`; a document asking for such a slug or parent fails with a neutral
  "not available" message (no "skipped existing" answer, no duplicate slug). A `wiki_parent` that does not exist fails
  the same way in the web import, so hidden and missing parents read alike. The CLI import is unchanged.
- The ancestor walk is shared across the hits of one search or AI answer (DJ-I1). SECURITY.md says that host
  endpoints for link titles, `@` candidates and the page picker must use `isPageReadable()` (DJ-I2).

## [1.13.1] - 2026-11-03

Tiptap assets only for tiptap, no collab tables when collaboration is off, safe co-editor names, purge/append race, audit residuals

Patch for hosts adopting 1.13 (Beacon requests) and the audit Info residuals CW-I7, CW-I9, CW-I10, CW-I12, CV-I9. See
[UPGRADING](UPGRADING.md#to-1131).

### Fixed

- **No schema diff without co-editing:** the collaboration tables are left out of the generated schema
  (`postGenerateSchema`) while `collaboration.enabled` is false.
- **Edit page loads the Tiptap script only for `editor.type: tiptap`** (Milkdown and Markdown pages no longer load it).
- **`wiki:collab:purge`** refuses `--older-than 0`, selects stale logs under a row lock inside a transaction and tells
  the live editors of purged pages to reload (CW-I10; `WikiCollabPagePurgeInterface`, `WikiCollabService::purge()`).
- **Capped manual reset** shows a flash message instead of failing silently (CW-I7; `wiki.collab.reset_capped`,
  `wiki.collab.rate_limited` in 7 locales).
- **Membership memo** in a `WeakMap` per user object (checked against the identifier), bounded to 64 users (CV-I9).
- **`collaboration.enabled` must be a literal boolean** (DA-M1): an `%env()%` placeholder registered the
  collaboration services while the schema listener left the tables out, so a diff would have dropped live tables.
- **Appends lock the page document** before storing an update; an append to a log purged meanwhile is refused with
  409 `log_reset` (no purged session is revived), document rows use the platform's insert-or-ignore form (no aborted
  PostgreSQL transaction), and purges also drop updates left without a document (DA-L1).
- **"Editor xxxx" is an HMAC** of the page id and the login under a subkey of `kernel.secret` (an empty secret is
  refused): stable per page, not linkable across pages nor reversible against a directory (DA-L2, DA-I5).
- **`show_names` filter hardened** (DA-L3): only Latin-script names without format / bidi controls or `@` are shown;
  the login and the e-mail local part are compared after case and accent folding, as words and as substrings;
  fullwidth, mathematical, circled and small-cap letters and other scripts fall back to the opaque label.
- The per-user limiter key is prefixed (`nowo_wiki_collab:`) and the schema listener only acts on the wiki's entity
  manager.

### Changed

- **Co-editor names are opaque by default** ("Editor xxxx"); `collaboration.show_names: true` shows display names,
  never one equal to the login identifier or shaped like an e-mail address (CW-I2).

### Added

- `collaboration.user_rate_limiter`: optional budget per user across all pages (CW-I9).
- Documentation: never put `mercure` keys in the hub's default JWT claims (CW-I12).

### Documentation

- `docs/ROADMAP.md`: knowledge-base roadmap towards Notion / Outline parity (page tree, trash, version history,
  comments, templates, search, per-page permissions, public links, Notion-style databases), parity matrix, open
  decisions.

## [1.13.0] - 2026-11-03

Milkdown editor bridge (renderer, link index, exports, HTML→Markdown conversion with report) and Mercure co-editing (off by default)

Wiki: Milkdown editor (`editor.type: milkdown`) and co-editing (`collaboration`, Mercure) — the Milkdown ↔ wiki bridge renders the nowo-tech/milkdown-editor-bundle dialect (page links, mentions, Tabler icons, directives, math, Mermaid) in every wiki surface under Milkdown's limits; `wiki:convert-markdown` gains page scope and a fidelity report. Schema change only when co-editing is used (two tables); Tiptap stays the default. See [UPGRADING](UPGRADING.md#unreleased).

### Added

- **`nowo_wiki.editor.type: milkdown`** (requires `nowo-tech/milkdown-editor-bundle` ^1.2 (conflict `<1.2.0`) with `NowoMilkdownEditorBundle` registered; the container refuses to compile otherwise): Markdown storage as with `markdown`, `MilkdownEditorType` as the page body unless `editor.markdown_form_type` says otherwise, and `editor.milkdown.page_resolver` (service id; default `WikiMilkdownPageResolver`).
- **`Markdown\Milkdown\MilkdownWikiMarkdownRenderer`** (alias of `WikiMarkdownRendererInterface` with `milkdown`): CommonMark + GFM + `MilkdownDialectExtension` + every `nowo_wiki.markdown_extension`, with the `nowo_milkdown_editor.markdown` options (`html_input`, `allow_remote_images`, `math`, `max_nesting_level`, `max_input_length`, `max_inline_markers`, `max_references`, `mark_colors`) handed over by `MilkdownBridgePass`. Two environments: a viewer-neutral one for the `content_html` cache, search excerpts and AI context (no page, user or host-block resolution) and a viewer one for the page view. Unsafe links are always dropped; input over the limits renders as escaped text.
- `Markdown\WikiViewerMarkdownRendererInterface` (`renderForViewer()`), `Markdown\WikiMarkdownReferenceParserInterface` (`references()`: page links and mentions from the renderer's parser), `Markdown\WikiMarkdownLimitsInterface` (`exceedsLimits()`).
- `Markdown\WikiRevisionBodyRenderer`: the page view renders Markdown revisions for the viewer (then sanitized and filtered for attachment URLs of other spaces) when the renderer is viewer-aware; the stored HTML otherwise.
- `Markdown\Milkdown\WikiMilkdownPageResolver` (pages the viewer may read: not archived, list gate, `View` on the space, the page and its ancestors; one query through `WikiPageBatchFinderInterface`), `WikiTablerIconRenderer` + `WikiMilkdownIconHydrator` (allowlisted icons drawn from the sprite after sanitizing; the sanitizer never allows SVG), `WikiMilkdownRichContent` and the Twig function `nowo_wiki_rich_content_assets()`; `wiki.js` draws Mermaid and math in `[data-wiki-rich-content]` with the Milkdown read-side renderer and the script's CSP nonce.
- `Repository\WikiPageBatchFinderInterface` (`findByIds()`), implemented by `DoctrineOrmWikiPageRepository`.
- `WikiMarkdownReferenceExtractor::mentions()`; with `milkdown` every method reads the renderer's parser.
- `wiki:convert-markdown`: `--page` (repeatable), `--report=<file>` (JSON fidelity report, format `nowo-wiki-conversion-report` v1), `--skip-lossy`, `--fail-on-loss` (every page is analysed first; nothing is written when a page is lossy), a console fidelity summary, and pages edited during the run are skipped (`skipped_changed`). `Interchange\Conversion\WikiConversionFidelityAnalyzer` / `WikiConversionFidelity`.
- `WikiMarkdownConverter(dialect: true)` (registered so with `milkdown`): `[[wiki:…]]` and `@handle` text kept as tokens, `<mark>` → `:mark[…]`, centred / right-aligned paragraphs and headings → `::: {align=…}`, embeds → links.
- Exports with `milkdown`: page links to exported pages become relative Markdown links to the exported files (`WikiMarkdownPortableLinks`).

- **Co-editing (FR-076):** `nowo_wiki.collaboration` (off by default; needs `milkdown` and symfony/mercure-bundle ^0.6.1, or your own publisher / subscriber services).
  - `WikiCollabController` (`routes.page_collab`): `token`, `state` (read only), `update`, `awareness`, `compact`, `corrupt` and the editor's `reset` form. Every request checks a trusted user, `canEdit()` and `View` + `Edit` on space and page, and goes through the **required** `collaboration.rate_limiter` (the container refuses to compile without it). POSTs must be same-origin JSON; bodies are size-limited.
  - `Collaboration\WikiCollabService`, `WikiCollabStateStoreInterface` with the default `DoctrineWikiCollabStateStore` (tables `<prefix>_page_collab_documents` / `<prefix>_page_collab_updates`): a Yjs log the server never decodes, compacted by clients (one snapshot, compactions serialised per page), bounded by `max_log_entries` and `max_log_bytes` (checked by counters before and after each append).
  - `WikiCollabPublisherInterface` / `WikiCollabSubscriberAuthorizerInterface`, defaults on symfony/mercure ^0.8 / ^0.9: private updates and a subscriber grant for the page topic only.
  - Seeding: one tab seeds an empty log (claimed by `POST token`, per user and `X-Nowo-Collab-Client`).
  - **Reset only when needed:** saves from the live session (form field `_nowo_milkdown_collab`, current base) keep the log; saves outside it or with a stale base, restores, imports, conversions, `corrupt` reports and the editor's *Reset collaboration* button (CSRF + confirmation) drop it and publish a reset — client / editor resets are capped per page (`corrupt_resets` per `corrupt_window`). Co-editors see a display name or an opaque label, never the login.
  - `bin/console wiki:collab:purge --older-than=<days>` deletes idle logs.
  - The page edit form passes MilkdownEditorType (^1.2) the `collaboration` option.

### Changed

- `LegacyWikiSpaceAccessChecker` (the default space checker) reads a user's spaces once per request (memo keyed by user identifier, cleared on `kernel.reset`): a page with many links costs one `listSpacesForUser()` instead of one per link (audit CV-L1).
- With `milkdown` the sanitizer also keeps `details` (`open`) / `summary` and `role`, `aria-label`, `data-math`, `data-directive`, `data-icon` (`WikiExtension::MILKDOWN_SANITIZER_ELEMENTS` / `MILKDOWN_SANITIZER_ATTRIBUTES`).
- `nowo_wiki_safe_html` draws dialect icons after sanitizing when `milkdown` is on; the page view passes `rich_content` and marks its body `nowo-md` / `data-wiki-rich-content`.

## [1.12.0] - 2026-10-31

Host polish found while integrating the wiki in a Tailwind host (symfony-beacon): the original-templates Twig namespace, a search form that fits `tenant_only`, catalogue flashes, spaced search excerpts and no empty pagination. No schema change. See [UPGRADING](UPGRADING.md#to-1120).

### Added

- **`@!NowoWikiBundle` Twig namespace:** `TwigPathsPass` also registers the bundle views under `!NowoWikiBundle` (the bundle templates without the `templates/bundles/NowoWikiBundle/` overrides), so an app override can extend the original page with the conventional `{% extends '@!NowoWikiBundle/manage/page_view.html.twig' %}` instead of the Symfony bundle-name alias `@!Wiki`.
- **Catalogue keys** (7 locales): `wiki.search.query` (label of the search field), `wiki.space.pick_page_read_only`, `wiki.page.error.invalid_slug` / `slug_exists` / `save_failed`, `wiki.import.failed`, `wiki.import.report`.

### Changed

- **Flashes are catalogue messages:** the controller builds every flash as a `TranslatableMessage` in the `NowoWikiBundle` domain (stored translated when the translator is available, as before; the `TranslatableMessage` itself otherwise, so a layout printing flashes with `|trans` still translates them). The page-create errors (empty, invalid or duplicate slug) and the import failure / report are catalogue keys with parameters instead of the services' English sentences (`Page slug already exists in this space.`, `2 created, 0 updated, …`).
- **Search form:** the `q` field is labelled `wiki.search.query` ("Search for") instead of repeating the placeholder text. With `tenant_only: true` (and tenant paths) the space stays a hidden field: there is no "All spaces" choice where every page lives under a tenant path.
- **Space home:** the header intro says "Select a page from the sidebar or create one." only to users who may create pages; the others read `wiki.space.pick_page_read_only`.
- **FormKit `wiki` profile:** the seeded profile (when the host defines none) keeps the alias and the `NowoWikiBundle` translation domain only; it no longer forces Bootstrap classes (`form-control`, `form-select`, `form-check-*`, `mb-2`) on every wiki field, so the host form theme decides (Tailwind / custom hosts).
- **Pagination:** the space index and the version history pass no `Pagination` when everything fits on one page (no empty pagination region / "1 of 1").

### Fixed

- **Search excerpts glued blocks together** ("OverviewThis runbook…"): block boundaries (paragraphs, headings, list items, table cells, line breaks, …) become spaces before the tags are stripped; entities are decoded and whitespace (no-break spaces included) collapses to one space.
- **Diff markers:** the "Added:" / "Removed:" screen-reader text uses the bundle's own `nowo-wiki-sr-only` class (still with `visually-hidden`), so hosts without Bootstrap / UiKit CSS no longer print it; the `+` / `-` marker is `aria-hidden`.

## [1.11.1] - 2026-10-30

Bundle routes are opt-in again: controllers are no longer autoconfigured, so a host's routing.controllers import does not expose them (REQ-SF-004 rule 7). See [UPGRADING](UPGRADING.md#to-1111).

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

[Unreleased]: https://github.com/nowo-tech/WikiBundle/compare/v1.16.1...HEAD
[1.2.0]: https://github.com/nowo-tech/WikiBundle/compare/v1.1.1...v1.2.0
[1.1.1]: https://github.com/nowo-tech/WikiBundle/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/nowo-tech/WikiBundle/compare/v1.0.5...v1.1.0
[1.0.5]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.0.5
[1.0.4]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.0.4
[1.0.3]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.0.3
[1.0.2]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.0.2
[1.0.1]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.0.1
[1.0.0]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.0.0
