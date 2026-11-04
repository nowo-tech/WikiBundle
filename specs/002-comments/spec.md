# Feature Specification: Comments in a side panel (WikiBundle 1.16)

**Feature Branch**: `002-comments`  
**Created**: 2026-11-03  
**Status**: Implemented for 1.16.0 (Beacon contract v1.1 OK'd on 2026-11-03, avatar and search addenda 2026-11-04)  
**Input**: Owner request via Beacon: page comments and Milkdown 1.6 inline threads in a right-side panel, toggled from
the page tabs on the read and edit views.

**Related docs**: [`docs/ROADMAP.md` › Inline and page comments](../../docs/ROADMAP.md#inline-and-page-comments-t064),
Milkdown 1.6 comments contract (anchor v1, thread component, endpoints), Beacon contract
([`docs/COMMENTS-CONTRACT.md`](../../docs/COMMENTS-CONTRACT.md)).

---

## Summary

Opt-in `comments.enabled` (default `false`: output unchanged). Needs `editor.type: milkdown` and
`nowo-tech/milkdown-editor-bundle ^1.6`. The read and edit views get a tab row (Page, Edit, History) and a
**Comments (n)** toggle that opens a right-side panel holding the Milkdown 1.6 thread component: inline threads
anchored in the text and page-level comments. The panel is UiKit markup (no host override), remembers open / closed
per user, becomes a full-height drawer on phones, and follows the default-deny comment rights of the checker.

---

## User Scenarios & Testing

### US-C1 — Open the comments panel (Priority: P1)

As a reader with the right to see comments, I open *Comments (n)* from the page tabs on the read or edit view and see
the open threads; I close it with the close button, the toggle or Escape.

**Acceptance**: focus moves to the panel heading on open and back to the toggle on close; the live region announces
"Comments opened" / "Comments closed"; on a 375 px viewport the panel is a full-height drawer and the page behind is
inert; axe has no serious violation in light and dark; no inline styles (CSP).

### US-C2 — The panel remembers its state (Priority: P1)

As a user, the panel is open on the next page if I left it open.

**Acceptance**: the state is stored per user (session by default, `comments.state_store` for the host), applied by the
server before rendering (no flash); `?comments=1|0` overrides it for one request.

### US-C3 — Comment on a selection and on the page (Priority: P1)

As a commenter, I comment on selected text or on the whole page, reply, react, and edit or delete my own comments.

**Acceptance**: inline threads from the Milkdown component (anchor v1 validated server-side), page-level threads
without an anchor; the badge count follows; clicking a highlight focuses its thread in the panel and vice versa.

### US-C4 — Resolve and moderate (Priority: P2)

As a user with the resolve right I resolve and reopen threads; as a moderator I edit or delete any comment.

**Acceptance**: resolved threads are hidden by default (*Show resolved*); moderation actions only with the moderation
right; every write re-checked server-side.

### US-C5 — Nothing leaks (Priority: P1)

As a user without comment rights, or on a page I may not read, I see no toggle, no count and no thread.

**Acceptance**: 404 for unreadable pages (1.13.2 ancestor rule), 403 without the right; comments excluded from
exports, AI context and search for users without the view right; trashed pages take their threads with them.

---

## Requirements

- **FR-COMMENT-001** (`comments.enabled`, opt-in, literal boolean): without it no toggle, panel, route or table.
  The page tab row (FR-COMMENT-002) is present in every case (Beacon B-Q1), the one default change of 1.16. Requires Milkdown ≥ 1.6 and `editor.type: milkdown` (container error otherwise).
- **FR-COMMENT-002** Page tabs: `PageHeader::tabs` (`AreaNav`, secondary level) with Page / Edit / History per
  permission, current view active, always rendered. The comments toggle (only with `comments.enabled` and
  `ViewComments`) is the UiKit side-panel toggle as an icon button (`tabler:message-circle`) with `aria-controls` /
  `aria-expanded` and a visible count badge that is also part of the accessible name (n = open threads the viewer may
  read; resolved excluded). No default shortcut; `comments.shortcut` sets one (`aria-keyshortcuts`).
- **FR-COMMENT-003** Panel: the UiKit ≥ 2.25 side panel (an interim WikiBundle partial with the same classes until
  then): `<aside>` named by its heading; ≥ 768 px an inline right column (no focus trap), ≤ 767 px an overlay
  full-height drawer; focus in on open and back to the toggle on close, Escape closes, polite live region; works
  without JavaScript through `?comments=1` (page-level comments by plain forms).
- **FR-COMMENT-004** State: `WikiPanelStateStoreInterface` (a stable public contract; the session in v1,
  `comments.state_store` for a host store such as Beacon's later DB store), same-origin JSON state endpoint with a
  CSRF token per space.
- **FR-COMMENT-005** Rights: `WikiAction::ViewComments` (a separate right), `Comment`, `ResolveComment`,
  `ModerateComments`, outside `grants()` and denied by the legacy mapping (default deny; Beacon maps them to View,
  Edit, manage, manage); authors edit / delete their own comments with
  `Comment`; the 1.13.2 read rule on every request.
- **FR-COMMENT-006** Endpoints under `page_comments` as in the Milkdown 1.6 contract 6.1 (v1.2 fields), CSRF per page,
  rate limiter required, body caps; 403 / 404 / 409 / 413 / 422 / 429.
- **FR-COMMENT-007** Storage: threads, comments, reactions tables (left out of the schema when off), page-owned (trash,
  restore, purge), resolved threads kept until purge (D-W9); author names follow the 1.15 label policy.
- **FR-COMMENT-008** Integration: the Milkdown 1.6 thread component mounted into the panel (`panel: 'container'`),
  two-way highlight / thread focus (opening from a highlight scrolls to the thread and focuses its first control),
  `comments-count` event drives the badge (others' new comments update it live, announced only while the panel is
  open); live updates by ids on a private
  Mercure topic per page.
- **FR-COMMENT-009** Events `nowo_wiki.comment.*` (`WikiCommentEvent`, ids and actors, no bodies) for host
  notifications.

## Success Criteria

- 100 % PHP and TS coverage; phpstan, cs, rector, igor, translation parity in 7 locales.
- Demo e2e: open / close (mouse, keyboard, Escape), state kept across pages, phone drawer at 375 px, inline and page
  comments, resolve, rights matrix (reader without rights sees nothing), axe light + dark, CSP.
- Beacon renders the panel with its UiKit theme and no template override (contract OK'd before the PR).

## Beacon decisions (contract section 9, 2026-11-03)

B-Q1 the tab row always exists, the toggle only with comments and `ViewComments`; B-Q2 generic UiKit side panel
(left / right, inline desktop, overlay drawer ≤ 767 px, no focus trap inline); B-Q3 `ViewComments` separate;
B-Q4 session store in v1, stable interface; B-Q5 icon toggle with a visible count, configurable shortcut, live badge.
