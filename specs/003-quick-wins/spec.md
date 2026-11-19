# Feature Specification: Favourites, recents, backlinks and the rendered revision diff (WikiBundle 1.45)

**Feature Branch**: `003-quick-wins`
**Created**: 2026-11-19
**Status**: Implemented for 1.45.0
**Input**: Roadmap wave 1 (owner decision D-W32, 2026-11-18): favourites and recently viewed, backlinks panel,
rendered diff. Recommended answer of the open decision D-W3 applied (both sections in the sidebar, collapsible).

**Related docs**: [`docs/ROADMAP.md` › Next](../../docs/ROADMAP.md#next), [`docs/USAGE.md`](../../docs/USAGE.md),
[`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md), [`docs/HIGH-LOAD.md`](../../docs/HIGH-LOAD.md).

---

## Summary

Four opt-in switches (all `false` by default: pages, routes and schema are unchanged until a host turns them on):

| Switch | What it adds | Table |
|--------|--------------|-------|
| `favourites.enabled` | *Add to favourites* / *Remove from favourites* in the page actions; a **Favourites** section above the page tree | `<prefix>_page_favourites` |
| `recents.enabled` | A **Recently viewed** section above the page tree, fed by page views | `<prefix>_page_visits` |
| `backlinks.enabled` | **Linked from** after the page body; a backlink index kept current on every save | `<prefix>_page_references` |
| `history.rendered_diff` | *Compare* shows the editor's rendered diff (blocks, then words) of two Markdown revisions | none |

---

## User scenarios and testing

### US-Q1 — Star a page (P1)

As a reader, I add the page I am reading to my favourites and find it at the top of the sidebar on every page of
the wiki; I remove it with the same button.

**Acceptance**: POST + CSRF, no JavaScript needed; the button says *Add to favourites* or *Remove from favourites*;
the section lists my favourites newest first, at most `favourites.shown`; a second add (another tab) changes nothing;
past `favourites.max` the add is refused with a message.

### US-Q2 — Get back to recent pages (P1)

As a reader, the pages I opened last are listed under *Recently viewed* (newest first, the current page left out).

**Acceptance**: at most `recents.keep` rows are kept per person (each visit deletes the older ones); at most
`recents.shown` are listed.

### US-Q3 — Never a page I may not read (P1)

As a person who lost access to a page or a space (or whose page was archived, trashed or moved under a hidden
parent), I no longer see it in my favourites or recents.

**Acceptance**: every list is filtered for the viewer on every request, before it is cut to the shown size: only the
spaces the viewer may read now reach the SQL (role gate, tenant / team resolver, View on the space), only live pages
(no archived or trashed page), then View on the page and every ancestor. The row stays: the page shows again if access
comes back. Nothing is memoised between requests (worker mode).

### US-B1 — See what links here (P1)

As a reader, under the page body I see *Linked from*: the pages whose **current** revision links this page
(`[[wiki:<id>]]`, with or without a section anchor), by title, with the space name when another space.

**Acceptance**: only sources the viewer may read are listed or counted: the SQL narrows the index rows to the
viewer's readable spaces and live pages before anything is cut, then the page rule runs on a bounded keyset scan
(`backlinks.candidates`) until `backlinks.limit` readable sources are found. Links inside code are not links. The
index is written after every save (create, edit, restore, import, conversion) and filled once with
`wiki:backlinks:rebuild`.

### US-D1 — A readable diff (P1)

As a non-technical reader, *Compare* shows the two versions as rendered content with inserted and deleted words
highlighted, not Markdown source lines.

**Acceptance**: with `history.rendered_diff` and the Milkdown editor, two Markdown revisions use the editor's diff
(`MarkdownDiffRenderer`); every block is rendered for the viewer (page links titled only when readable) and sanitized
like the page body, then the whole diff is sanitized again with the page allow-list plus only the diff markup (no
stored XSS through a revision); visually hidden *Inserted* / *Deleted* labels; large or very different versions get
the editor's escaped line fallback; HTML revisions keep the line diff.

---

## Requirements

- **FR-001** All four switches default to `false`; with them off the routes, pages, events and generated schema are
  byte-identical to 1.44.
- **FR-002** Favourites and visits are per person: the user's `getId()` as a string (`user_id`, no foreign key, binary
  collation on MySQL / MariaDB, compared again with `===`); a user without an id has no lists and no button.
- **FR-003** Per-viewer filtering as in US-Q3 and US-B1, before any cut; nothing cached across requests.
- **FR-004** Recents bounded per person (`recents.keep`, at most 50 stale rows deleted per visit).
- **FR-005** New write paths (favourite toggle, visit, backlink index, rebuild) run in the wiki's runner on the
  connection (DBAL; nothing is flushed, an open host entity manager is never flushed, cleared or reset).
- **FR-006** REQ-RUNTIME-003 rule 6 on the favourite route: a lock conflict answers 409 (the shared policy's status)
  + `X-Conflict-Code: CONFLICT_001` + `Retry-After` when nothing was committed, 409 + `CONFLICT_002` without
  `Retry-After` when part of the request was (request watermark of transaction-doctrine >= 1.6). A unique violation of
  `(user_id, page_id)` is the expected "only one" race: answered as done, never a conflict.
- **FR-007** The visit is a side effect of a read: a lock conflict is logged and the page is shown (unless a caller's
  transaction was open: then it propagates). The backlink index write runs after the save committed: a lock conflict
  is logged (index stale for that source until its next save or a rebuild); inside a caller's transaction it
  propagates.
- **FR-008** The index write locks the source page row and reads its current revision under the lock: concurrent saves
  of one page always leave the latest revision's links.
- **FR-009** DDL for MySQL 8.4, MariaDB 11.4 and PostgreSQL 16 in UPGRADING; tables of disabled features stay out of
  the generated schema.
- **FR-010** Erasure: `wiki:quick-access:erase-user <id>` deletes a person's favourites and visits.

## Review VC and owner decisions (2026-11-19)

- **A.** Lists show only pages of the current tenant (the link picker's tenant port), computed once per view.
- **B.** `recents.retention_days` (90) on top of `recents.keep`; pruned on write and by `wiki:recents:purge`; visits are
  personal data, exported and erased with the person.
- VC-1 hidden favourites never count toward `max` and are removable by id; VC-2 exact ids, writes by primary key, a
  visit never fails the page; VC-3 erasure always available and part of `wiki:comments:erase-user`; VC-4 resilient,
  resumable rebuild; VC-5 FK collation in the DDL docs; VC-6 300-block cap of the rendered diff; VC-7 diff navigation
  attributes; VC-8 limits keep the index; no visit on `HEAD` / prefetch.

## Out of scope (follow-ups)

- Favourites and recents in the host's Ctrl / ⌘ + K palette (SearchKit provider) — next.
- The editor's read-only `version_view` for old versions (roadmap item of the same wave) — next.
- Reordering favourites; favourites of whole spaces.
