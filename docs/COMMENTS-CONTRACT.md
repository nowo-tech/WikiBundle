# WikiBundle 1.16 — comments aside: contract for Beacon

Status: **v1.1, 2026-11-03 — OK'd by Beacon** (answers in section 9). Owner: WikiBundle agent. Built after 1.15.
Related: `.uic-work/milkdown-1.6/CONTRACT.md` (Milkdown 1.6 comments: anchor v1, JS API, thread component,
endpoints 6.1, and the WikiBundle review in that file, which this contract builds on).

## 0. Principles

- **Opt-in.** `nowo_wiki.comments.enabled: false` by default: no toggle, no aside, no routes, no tables. The one
  default change of 1.16 is the page tab row (B-Q1, section 1), listed in UPGRADING. Comments need `editor.type: milkdown` and `nowo-tech/milkdown-editor-bundle ^1.6` (anchors and
  thread component), like co-editing; the container refuses to build otherwise.
- **UiKit markup only.** Every element below is UiKit markup and theme slots, so Beacon themes it with its UiKit theme
  and needs **no Twig override**. Wiki-specific classes are only hooks (`nowo-wiki-*`), never required for layout.
- **Default deny.** No comment right is granted unless the host's checker grants it (the 1.x legacy mapping grants
  nothing new).
- **Server is the authority.** The `can` flags in the HTML and the JSON are hints; every endpoint re-checks.

## 1. Page tabs

The read view (`page_view`) and the edit view (`page_edit`) get a tab row under the title, rendered by UiKit
(`PageHeader::tabs`, an `AreaNav` → `partials/_tabs.html.twig`, level `secondary`):

| Tab | Shown when | `href` | `data-testid` |
|-----|------------|--------|---------------|
| Page | always | `page_view` | `wiki-tab-page` |
| Edit | `can.edit` | `page_edit` | `wiki-tab-edit` |
| History | `can.history` | `page_history` | `wiki-tab-history` |

The current view is the active tab (`aria-current="page"`). Then, at the end of the same row, the **comments toggle**
— a button, not a tab (it opens a panel, it does not navigate):

The toggle is the UiKit side-panel toggle (markup in `.uic-work/uikit-2.25/CONTRACT.md`) as an **icon button**
(`tabler:message-circle`) with the count as a **visible badge**:

- Accessible name: "Comments, 3 open" (the count is part of the name); "Comments" alone when 0 (no badge).
- No default keyboard shortcut; `comments.shortcut` (e.g. `Alt+Shift+C`, null by default) sets one, exposed with
  `aria-keyshortcuts`.
- The count is the number of **open threads the viewer may read** (resolved threads excluded), computed by the server
  on render and then kept in sync by the Milkdown component (`comments-count` event, section 6).
- Shown only to viewers with `WikiAction::ViewComments` (section 4). Without it there is no toggle and no aside.
- **The tab row always exists from 1.16** (B-Q1), with or without comments: a visible change of the read and edit
  views, listed in UPGRADING (hosts that override `page_view` / `page_edit` compare their templates). The toggle
  renders only with `comments.enabled` **and** `ViewComments` for the viewer; otherwise there is no button and no
  count.

## 2. The aside

One right-side panel per page view, rendered after the main column, inside the UiKit page layout:

```html
<aside id="nowo-wiki-comments" class="nowo-ui-side-panel" data-nowo-ui-side-panel
       aria-labelledby="nowo-wiki-comments-title" hidden>
  <div class="nowo-ui-side-panel__header">
    <h2 id="nowo-wiki-comments-title" class="nowo-ui-side-panel__title" tabindex="-1">Comments</h2>
    <button type="button" class="nowo-ui-btn nowo-ui-btn--icon nowo-ui-side-panel__close"
            data-nowo-ui-side-panel-close aria-label="Close comments">×</button>
  </div>
  <div class="nowo-ui-side-panel__body" data-nowo-wiki-comments-mount>
    <!-- Milkdown 1.6 thread component (inline threads + page-level threads) mounts here -->
  </div>
</aside>
<div class="nowo-ui-visually-hidden" aria-live="polite" data-nowo-ui-side-panel-status></div>
```

- **Landmark:** `<aside>` = `complementary`, named by its heading. The Milkdown component inside does **not** add a
  second `complementary` landmark when mounted into a host container (Milkdown option `panel: 'container'`).
- **Desktop (≥ 768 px):** a column on the right of the content, the content narrows (no overlay); width from the theme
  token `--nowo-ui-side-panel-width` (default 22rem).
- **Phones (≤ 767 px):** a full-height drawer from the right edge over the content (`position: fixed; inset: 0 0 0
  auto; width: 100%`), with the page behind made `inert` while it is open, and the close button always visible.
- **Open / close:** the toggle, the close button, and **Escape** (when focus is inside the panel). Opening moves focus
  to the panel heading; closing returns focus to the toggle. The live region announces "Comments opened" / "Comments
  closed" (translated).
- **No JavaScript:** the toggle is a link to `?comments=1` (the server renders the panel open); page-level comments
  then work through plain forms (section 5, *no-JS fallback*). With JavaScript the link is upgraded to the button.
- **CSP:** no inline styles or handlers; sizes and the open state are classes and attributes.

**UiKit dependency (B-Q2, approved).** The panel is the generic UiKit side panel (≥ 2.25; markup contract in
`.uic-work/uikit-2.25/CONTRACT.md`): left or right side, an inline column on desktop and an overlay full-height drawer
at ≤ 767 px, focus in on open and back to the toggle on close, Escape closes, **no focus trap in inline mode**,
`aria-controls` / `aria-expanded` on the toggle. Until WikiBundle can require it, an interim WikiBundle partial uses
**exactly the same classes and attributes**, so switching to the UiKit partial changes no markup. The HTML above is
illustrative; the UiKit contract is the reference.

## 3. Open / closed state per user

- Remembered **per user** (not per page) like the tree's fold state: `WikiPanelStateStoreInterface` with
  `isOpen(UserInterface, string $panel): bool` / `setOpen(…)`; default store = the session; `comments.state_store`
  names a host service (Beacon: its user preferences store).
- The server renders the panel in the remembered state (no flash of the wrong state). The JS posts changes to
  `POST …/spaces/{spaceSlug}/panel-state` (same-origin JSON, CSRF per space, body `{ "panel": "comments", "open":
  bool }`, 204), the same pattern as the 1.14 tree state endpoint.
- `?comments=1` / `?comments=0` overrides the stored state for that request (deep links from notifications open it).
- **v1 store: the session** (B-Q4). `WikiPanelStateStoreInterface` is a stable public contract: Beacon will plug a
  database store in later through `comments.state_store`.

## 4. Rights (checker actions)

New `WikiAction` cases, all **excluded from `grants()`** and **denied by the 1.x legacy mapping** unless the host's
checker grants them (default deny):

| Action | Gates | Scope |
|--------|-------|-------|
| `ViewComments` | see the toggle, the aside and the threads; `GET` endpoints | page (+ the page's read rule) |
| `Comment` | create a thread (inline or page-level), reply, react, refresh an anchor (PATCH anchor) | page |
| `ResolveComment` | resolve / reopen a thread | page |
| `ModerateComments` | edit or delete **any** comment; delete a thread | page (Beacon may map it to `Manage`) |

- **Own comments:** an author may edit and delete their own comments with `Comment` alone (no moderation right).
- Every check also applies the 1.13.2 read rule (View on the page and every ancestor): an unreadable page answers 404
  everywhere; a missing right answers 403.
- D-W8 (decided): readers with a comment right may comment. **`ViewComments` is a separate right in the bundle**
  (B-Q3). Beacon maps `ViewComments` → its View permission, `Comment` → Edit, `ResolveComment` and
  `ModerateComments` → manage.

## 5. Endpoints

All under the route key `page_comments`, base `/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/comments` (that URL
is the Milkdown `comments.url`). JSON, same origin, `X-CSRF-Token` on writes (token id per page), bodies ≤ 16 KiB,
comment Markdown ≤ 10 000 characters, a host rate limiter (`comments.rate_limiter`, required when enabled) on writes.

| Method + path | Right | Answer |
|---------------|-------|--------|
| `GET /threads` | ViewComments | `{ threads, page, truncated? }` (ThreadRef lists, cap 2000) |
| `GET /threads/{id}` | ViewComments | `Thread` |
| `POST /threads` `{ anchor \| null, body }` | Comment | `201 Thread` (anchor validated with `CommentAnchor`; 422 if malformed) |
| `POST /threads/{id}/comments` `{ body }` | Comment | `201 Thread` |
| `PATCH /threads/{id}` `{ resolved }` | ResolveComment | `Thread` |
| `PATCH /threads/{id}` `{ anchor, base }` | Comment | `Thread`; 409 if `base` is stale |
| `PATCH /comments/{cid}` `{ body }` | own + Comment, or ModerateComments | `Thread` |
| `DELETE /comments/{cid}` | own + Comment, or ModerateComments | `Thread` or 204 |
| `PUT` / `DELETE /comments/{cid}/reactions/{emoji}` | Comment | `Thread` |

Shapes as in the Milkdown contract 6.1 (v1.2: `updatedAt`, `resolvedBy` / `resolvedAt`, deleted comments,
host-chosen `author.name` with the 1.15 author-label policy, `truncated`). Status codes: 403 right / CSRF, 404 page or
thread not visible, 409 stale anchor, 413 body too large, 422 malformed, 429 rate limit.

**No-JS fallback (page-level only):** `GET …/comments` (HTML list inside the aside) and `POST …/comments/new` (form
with CSRF) for page-level comments; inline threads need the editor.

**Live updates:** private Mercure topic `<topic_prefix><page id>/comments` on the co-editing hub, ids only (Milkdown
contract 7), authorised like co-editing plus `ViewComments`.

## 6. Milkdown 1.6 component in the aside

- WikiBundle mounts the thread component **into** `[data-nowo-wiki-comments-mount]` (`panel: 'container'`, `container`
  = that element), on the edit view and — for viewers with `ViewComments` — on the read view through
  `nowo_milkdown_view()` (Q4 of the Milkdown contract: no highlights on the server-rendered body).
- **Two-way sync:** clicking a highlight opens the aside (if closed) and focuses the thread; focusing a thread in the
  aside scrolls to and marks its highlight (`comments.focus(id)`).
- **Badge:** the component emits `nowo-milkdown:comments-count` `{ open: n }` on the widget root; WikiBundle's glue
  updates the badge and the hidden text. The live region does **not** announce count changes (only open / close; new
  replies are announced by the component's own polite region).
- When the aside is closed, *Comment* on a selection opens it with the composer focused.
- **Opening from a highlight** (B-Q5) opens the panel if needed, scrolls the thread into view and focuses its first
  control.
- **A new comment by someone else** (live update) updates the badge count at once; it is announced only when the panel
  is open (the component's polite region), never while it is closed.

## 7. Storage (unchanged from the WikiBundle review)

`<prefix>_comment_threads`, `<prefix>_comments`, `<prefix>_comment_reactions` (left out of the schema when off);
threads belong to the page (trash and restore with it, purge deletes them, D-W9: resolved threads kept until the page
is purged); excluded from exports, AI context and search for users without `ViewComments`.

## 8. Events (PHP)

`nowo_wiki.comment.created` / `.replied` / `.edited` / `.deleted` / `.resolved` / `.reopened` (`WikiCommentEvent`:
`page`, `thread`, `comment`, `actor`, `mentions`): Beacon builds notifications from them (in-app, e-mail); bodies are
never in the event, only ids and the per-viewer rendered text on demand.

## 9. Beacon's answers (2026-11-03, contract OK'd)

- **B-Q1 tab row:** always present from 1.16; the comments toggle only with `comments.enabled` and `ViewComments`
  (no button, no count otherwise).
- **B-Q2 side panel:** approved as a generic UiKit component (left / right, inline on desktop, overlay drawer at
  ≤ 767 px, focus in / back, Escape, no focus trap inline, `aria-controls` / `aria-expanded`). Beacon OKs the UiKit
  markup (`.uic-work/uikit-2.25/CONTRACT.md`) before that PR; the interim WikiBundle partial uses the same classes.
- **B-Q3 rights:** `ViewComments` is separate in the bundle; Beacon maps ViewComments → View, Comment → Edit,
  ResolveComment and ModerateComments → manage.
- **B-Q4 state:** the session in v1; `WikiPanelStateStoreInterface` stays stable for a later DB store.
- **B-Q5 toggle:** icon button `tabler:message-circle`, visible count badge also in the accessible name; no default
  shortcut, configurable; opening from a highlight scrolls to the thread and focuses its first control; others' new
  comments update the badge live, announced only when the panel is open.

## Changelog of this contract

- v1 draft, 2026-11-03.
- v1.1, 2026-11-03: Beacon's answers (section 9) folded in; contract OK'd.

## Addendum (2026-11-04): avatars in 1.16

Avatar contract v1.1 (`.uic-work/avatars/CONTRACT.md` §8–§12) is approved by Beacon. WikiBundle 1.16.0 adds
`WikiUserAvatarResolverInterface` (keyed by `WikiAvatarKey`, size 32; a Null default by alias). It covers the author in the
page table and history, plus comment `author` / `resolvedBy.avatarUrl`, rendered through UiKit `_avatar` (UiKit ^2.26).
**Privacy:** where the name is hidden (`history.show_names` / `collab.show_names` false, or the name fails
`isSafeName()`), there is no avatar and **no resolution**. Add tests asserting the resolver is never called in those
cases. Collaboration presence avatars wait for 1.17 (with Milkdown 1.8, same-origin paths only, with query strings
and fragments stripped).

## Addendum (2026-11-04): search must ignore directive markers

Milkdown 1.7 adds callout icons (`:::callout{icon="bulb" type="info"}`). `WikiSearchService::searchInSpaces()` matches
`r.contentMarkdown LIKE :q`, so directive markers and attribute values (`bulb`, `info`, `tabler-…`) become search hits.
1.16 must match on plain text instead: a stored plain-text column derived from the rendered HTML (`plainText()`, which
excerpts already use), with a backfill command. Add tests showing that `bulb` does not match a page whose only
occurrence is the callout attribute.
Beacon (2026-11-04) prefers a **`contentText` column on the revision**, filled on save, with a
`wiki:search:backfill-text` command, so that hosts can filter in SQL. Beacon's `WikiPageSearchProvider` will switch its
`LIKE` to that column. Also expose `WikiPlainText::fromMarkdown()` for reuse. Document the column as public API.
