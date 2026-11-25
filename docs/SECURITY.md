# Security

## Table of contents

- [Attack surface](#attack-surface)
- [Threats and mitigations](#threats-and-mitigations)
- [Access control](#access-control)
- [Look-alike owner ids (1.31.1)](#look-alike-owner-ids-1311)
- [Content safety](#content-safety)
- [Co-editing threat model (1.13)](#co-editing-threat-model-113)
- [Comments threat model (1.16)](#comments-threat-model-116)
- [Page layout (1.29)](#page-layout-129)
- [REST API (1.24, spec 218)](#rest-api-124-spec-218)
- [SearchKit provider (1.44)](#searchkit-provider-144)
- [Secrets and cryptography](#secrets-and-cryptography)
- [Logging](#logging)
- [Limits and DoS](#limits-and-dos)
- [Dependencies](#dependencies)
- [REQ-SEC-004](#req-sec-004)
- [Reporting](#reporting)
- [Release security checklist (12.4.1)](#release-security-checklist-1241)

## Attack surface

| Input | Description |
|-------|-------------|
| **Manage routes** | Authenticated CRUD for spaces, pages, revisions, search (`WikiManageController`). |
| **Import / export** | Multipart ZIP or Markdown tree upload; file export download (`WikiManageController`, console commands). |
| **AI ask** | POST question to `/tools/wiki/ask` when Symfony AI is enabled. |
| **Page HTML** | Rich text from TiptapEditorBundle stored as HTML in `wiki_page_revisions.content_html`. |
| **Search query** | GET `q` parameter; LIKE search on title and content. |
| **Configuration** | `nowo_wiki` YAML (routes, ACL, table prefix, space scope). |

## Threats and mitigations

| Threat | Risk | Mitigation |
|--------|------|------------|
| **Stored XSS** | Malicious HTML/JS in page body. | `WikiHtmlSanitizer` (symfony/html-sanitizer, per-element attribute allowlist) on every save **and** at render time (`nowo_wiki_safe_html` Twig filter); drops `script`, event handlers, `srcdoc`, `style` (except `text-align`), non http/https/mailto URLs and iframes outside the https embed host allowlist. |
| **IDOR on spaces/pages** | User accesses another team's wiki. | `WikiSpaceAccessResolver` filters by team/user scope; controller resolves space via membership. |
| **Look-alike owner ids** (fixed in 1.31.1) | `Alice`, `álice` or `alice ` reach the spaces of `alice` through a folding or PAD SPACE collation. | See [Look-alike owner ids](#look-alike-owner-ids-1311): `===` re-check after every owner lookup, padded / control / format ids refused, binary NO PAD collation on `owner_scope_id`. |
| **Unauthorized edit** | Viewer modifies pages. | The access policy (`WikiAccessPolicyInterface`, or the role-based default) on all manage actions (`canEdit`, `canCreate`, etc.). |
| **CSRF on POST** | Forged save/archive requests. | `WikiCsrfTrait` validates tokens on edit, create, archive. |
| **Slug injection** | Path traversal or invalid URLs. | Route requirements `[a-z0-9]+(?:-[a-z0-9]+)*`; `WikiSlugger` validation. |
| **Revision tampering** | Delete or overwrite history. | Revisions are append-only; pages are soft-archived only (`BR-WIKI-002`). |
| **Search abuse** | Expensive LIKE queries. | `setMaxResults(25)` default; scope limited to accessible space. |
| **Malicious import** | ZIP bomb or oversized upload. | `import_export.max_upload_bytes` (default 50 MB); `WikiArchiveHelper` rejects archives with more than 10 000 entries, more than 10 000 directories, more than 512 MiB uncompressed, a Markdown document over `import_export.max_document_bytes` (default 8 MiB) or Markdown that cannot be read within `memory_limit`, first from the sizes the archive declares and again from the bytes really written, one chunk at a time. Only `.md` files are extracted, to a `0700` temp dir removed as soon as the documents are read, or by a shutdown function after a fatal error; the same covers the upload's copy, and leftovers of a killed process are removed by a later import. Front matter over 64 KiB is refused. Import requires `import_roles`. Memory use during the import is not bounded by these limits: it keeps every document in memory, so a sum of documents can still exceed `memory_limit` (measured with `editor.type: milkdown` under 256M: 25 MB finished, 50 MB did not). That ends the request with a fatal error and may leave the pages written so far, and nothing in the temp dir. |
| **AI prompt injection** | User steers agent via page content. | Context limited by `max_context_pages` / `max_context_chars`; restrict `ai_roles`; review agent system prompt. |
| **SSRF / outbound HTTP** | The only outbound call is the host-configured AI platform. | Endpoint comes from host config (`config/packages/ai.yaml`), never from user input; bounded by `nowo_wiki.ai.http_client` (`ai.http_timeout`). |
| **Path traversal on import** | ZIP entries such as `../../etc`. | `WikiArchiveHelper` rejects absolute / `..` entries before extracting into a fresh `0700` temp directory, removed afterwards. |
| **Resource exhaustion** | Slow LLM, huge spaces, long histories. | See [Limits and DoS](#limits-and-dos). |
| **XSS through a quota reason** (1.21) | A `nowo_wiki.quota.check` listener puts user data in `deny(reason)`. | The reason is plain text: control and format characters (bidi overrides included) removed, invalid UTF-8 scrubbed, at most 500 characters, escaped in the flash and the form error, JSON-encoded in the upload answer. |
| **Quota bypass** (1.21) | A metered write skips `nowo_wiki.quota.check`, or two requests race it. | Dispatched in the controllers of the five web write paths, after the access and CSRF checks and before the write. An import reports every Markdown document of its archive, and a refused import writes nothing. CLI commands and direct service calls do not dispatch it, page edits, revision restore, comments and co-editing updates are not metered, and the check and the write are not one transaction: a plan limit is not a security boundary unless the host also enforces it where it counts. |
| **Space enumeration through search** (1.21) | `search.scope: space` answers on a slug in the query. | The listener only checks the slug's format (404 otherwise); the controller still resolves the space through the access checks (404 for a hidden or unknown space). It is a navigation scope, not an access rule. |
| **Moderation list** (1.21) | Thread quotes are user content; the *Delete thread* action is drawn in the browser. | `comments.moderation_panel` is a literal boolean and renders only with `ModerateComments`; every text is set with `textContent`; the `DELETE` endpoint checks the right, the origin and the CSRF token again, whatever the page draws. |
| **Host templates** (1.21) | `web_ui.slots.*` templates print the page variables. | Config only, never request data; the slots are trusted host code under Twig autoescaping. |

## Access control

Replace defaults via `nowo_wiki.security.access_checker` and `nowo_wiki.team_membership_resolver`.

- **Team scope:** spaces visible when `owner_scope_id` is in resolver team ids.
- **User scope:** spaces owned by the current user id.
- **Roles:** `ConfigurableWikiAccessChecker` grants everything to the admin roles (`security.roles.admin`); every other permission needs one of `security.access_roles` (the general gate, enforced since 1.10) **and** one of `security.roles.<action>`. An empty list grants nothing, an explicit `~` too; when every list is empty without an `access_checker` the container refuses to compile.
- **Attachments:** downloads also need `canList()` and uploads `canEdit()` of the role checker (1.10), on top of the space / page checker.
- **Demo bypass:** `security.allow_unauthenticated: true` wires `AllowAllWikiAccessChecker`. It must be a literal boolean (an env placeholder is refused) and the container refuses it in the `prod` environment and in non-debug kernels (`test*` environments excepted), since 1.10.
- **CSRF fails closed:** with the Web UI enabled, the container refuses to compile without form CSRF protection or the CSRF token manager (`CsrfProtectionPass`, 1.10).

Host firewall example:

```yaml
security:
    access_control:
        - { path: ^/tools/wiki, roles: ROLE_USER }
```

See [docs/examples/AccessControl.md](examples/AccessControl.md) and [USAGE.md](USAGE.md).

### Space- and page-aware access (1.9)

The space access policy (`WikiSpaceAccessPolicyInterface`, or the role-based default) is asked on every route with the resolved space and page. A space or page the user
may not view answers 404 (never 403), so its existence does not leak; a denied write on a readable space answers 403.
Attachment downloads ask `view` on the attachment's **own** page and answer 404 to every denial (missing, other space,
forbidden, archived page without `manage`).

Since 1.10 a page the checker hides is hidden **everywhere**, not only on its own routes: the sidebar tree (with its
sub-pages, and the "tree truncated" notice counts readable pages only), the archived list, search results, the space
export ZIP, the AI sources, the AI context and the `wiki_knowledge_search` tool. Space lists (index, search and AI space
choices) only show spaces the checker lets the user `view`; a question across spaces only uses the spaces where `ask_ai`
is granted too. In **1.9.0** these lists, the export and the AI context ignored the per-page `view` decision of a host
checker (titles, excerpts and bodies of hidden pages were disclosed; the default checker is not affected) — upgrade to
1.10.0.

Since **1.13.2** one rule decides whether a page is readable, on every route and list: `view` on the page **and on
every ancestor** (`WikiPageVisibility::isReadable()`, `WikiSpaceGuard::isPageReadable()` / `requireReadablePage()`).
A page under a hidden parent answers 404 on the page view, history, diff, edit, archive / restore, attachments and
co-editing, and is left out of search and AI hits, as it already was from the tree, the export and page links. The
import never matches, updates or attaches under a page the importing user may not read: its slug is "not available"
(no duplicate slug is created).

**Trash (1.14, `trash.enabled`).** A trashed page is archived, so every read path treats it as gone, and its
attachments are only served to `manage`. The trash list and the purge page apply the same read rule (titles of pages
the user may not read are counted, not shown). Delete, Restore and Purge run under the space lock of the page tree
and read the deletion again inside it, so a purge only deletes pages that are still in the trash (a restore that ran
first keeps its pages; audit DO-M1). Purging deletes the pages, their revisions, host-object links and attachment rows
in one transaction; the attachment files are unlinked **after** the commit (a failed unlink is logged and leaves an
orphan file for the attachment maintenance; DO-L1). The co-editing log of a purged page is not served any more (the
co-editing endpoints need the page) and is removed by `wiki:collab:purge` (DO-I6). Delete and Restore ask
`WikiAction::Delete` on the deleted page, Purge `WikiAction::Purge`; a deletion that holds pages the user may not read
can only be purged with `WikiAction::Manage` on the space (DO-I1). Every write is POST + CSRF behind a confirm (two
for a purge).

**History timeline (1.15, `history.timeline`).** The history shows authors as per-page opaque labels (an HMAC of
`kernel.secret`, the co-editing rule), so the history of one page does not tell who edited other pages and a label
cannot be matched against a user directory; `history.show_names` shows only display names that say nothing about the
login. *Save version* and *Copy to new page* are POST + CSRF (a token per page and per revision), answer 404 for a page
the user may not read with every ancestor and check the edit, respectively history and create, rights; a copy is a
new top-level page in the same space, so it never lands under a parent the user may not see — and, by design, it
leaves any restricted branch: the copy is visible to whoever may read the space's top level (audit DT-I1). Version
names are stripped of bidirectional control characters (DT-I4); copy titles and slugs are cut to the column limits
(DT-I2). Labels are escaped like
every other cell. `wiki:history:thin` deletes revisions for good, but never a named version, the current revision or
the newest one.

**Host endpoints.** The endpoints your application provides to the editor — link titles of `[[` page links, the `@`
mention candidates and the page picker (`suggestions` / `link_title_url` of the Milkdown or Tiptap editor) — must
apply the same rule: call `WikiSpaceGuard::isPageReadable()` (or `WikiPageVisibility::isReadable()` with your
the space access policy) for every page they return, so a page under a hidden parent never shows up there
either (audit DJ-I2).

Since 1.40 the bundle serves the page picker itself (`editor.milkdown.link_picker`), so a host no longer needs its own
`[[` endpoint: `link_candidates` (`GET …/spaces/{spaceSlug}/link-candidates?q=`) and `link_sections`
(`GET …/spaces/{spaceSlug}/link-candidates/{pageId}/sections`), internal UI JSON routes (`WikiLinkPickerController`,
`LinkPicker\WikiLinkPickerService`):

- **Gates:** a firewall-trusted user (403), the list gate, View on the space, then View on every page and every
  ancestor; archived pages are left out. A wiki in `search.excluded_spaces` gets no candidates and no sections.
- **No oracle:** "no match", a missing wiki, a hidden wiki and "not allowed" give the same `{here: [], others: []}`;
  sections answer the same 404 for a missing, archived, unreadable or out-of-reach page. A wiki is named only when it has
  a readable hit; no counts are sent. Titles only are matched (never the content or the Markdown), `LIKE` escaped.
- **Same tenant only** (owner decision O4): other wikis come from the current wiki's tenant (`tenant_paths` row, or the
  host's `WikiLinkTenantResolverInterface`); none with `search.scope: space`, `cross_wiki: false`, or `tenant_only`
  for a wiki outside every row. At most 5 wikis × 3 pages (10 in all) and 10 pages of the current wiki.
- **Sections** come from the viewer's own render of the page (inside a render frame, sanitized), so a heading the render
  hides never shows; their text leaves out page references, mentions, icons and math.
- **Transport:** GET only, no side effects; same origin (`Sec-Fetch-Site` other than `same-origin` / `none`, or a
  foreign `Origin`: 403); `Accept: application/json` (406 otherwise); a required per-user rate limiter (429 +
  `Retry-After`); `Cache-Control: private, no-store`, `Vary: Cookie`, `X-Content-Type-Options: nosniff`. `q` is cut
  to 100 characters and stripped of control, format and bidi characters.
- **Worker mode:** the services are stateless (`final readonly`); the rate limiter is created per call; the render
  frame lives for one render; nothing is cached across requests (N-request test with two users and two tenants).
- **Timing (audit RY-L2, known):** the candidates answer is filtered after the title query, with one ancestor walk per
  hit, so its response time grows with the number of matching titles the person may **not** read. A patient caller can
  estimate that count (never a title, an id or a wiki name), within the rate limit. This is the same class as the
  search page's post-filtering. Planned for 1.41: filtering in SQL or padding to a fixed number of reads.
- **Host repositories:** without `WikiPageTitleFinderInterface` the picker reads at most `api.search_candidates` pages
  per wiki (tree order) before the title filter; the same gates apply.

**Section anchors (1.40).** Heading ids are only ever `nowo-h-` + the shared grammar (`Markdown\WikiHeadingAnchor`,
at most 1000 per page; the sanitizer pattern matches it), so page content cannot clobber host ids or globals. A hidden
page's placeholder carries no anchor and no section text; section labels (`Title › Section`) are given only for pages
already resolved for the viewer in the same render, from the viewer-neutral cache, top-level headings, text only. The
"link to this section" control is added after the sanitizer (`nowo_wiki_section_links`), with the id validated again
and escaped. The server never rewrites ids in stored Markdown.

## Look-alike owner ids (1.31.1)

**Affected:** 1.0.0–1.31.0, on MySQL and MariaDB. **Fixed:** 1.31.1 (house rule LQ).

`wiki_spaces.owner_scope_id` (the team or user that owns a space) was matched by SQL alone (`findAccessible()`,
`findBySlug()`), on the column's default collation. That result is the access source for `canAccessSpace()`, the space
list, the page routes, search, AI retrieval and the REST API. Measured on MySQL 8.4 and MariaDB 11.4 with 1.31.0
(`space_scope: user`, space owned by `alice`):

| Column collation | Principals that saw the space of `alice` |
|---|---|
| `utf8mb4_0900_ai_ci` (MySQL 8 default) | `Alice`, `álice`, `alice` + U+200B |
| `utf8mb4_uca1400_ai_ci` (MariaDB 11 default), `utf8mb4_unicode_ci` | the above plus `alice `, `ALICE  ` |
| `utf8mb4_general_ci` | `Alice`, `álice`, `alice ` |
| `utf8mb4_bin` (PAD SPACE) | `alice ` |
| `utf8mb4_0900_bin`, `utf8mb4_nopad_bin` | none |

Host conditions: owner ids that two principals can hold in look-alike forms. That is user scope with a user class
without `getId()` (the bundle then uses `getUserIdentifier()`) or with string ids, or a team resolver
(`WikiTeamMembershipSourceInterface`) returning such ids, plus a user store that lets both forms exist (another
collation, LDAP / OAuth / in-memory users, an identifier that is not normalised). Integer and UUID ids are not affected;
PostgreSQL and SQLite compare exactly.

Mitigations since 1.31.1:

- every lookup by owner re-checks owner, scope type and slug with `===` in PHP; the SQL match is only a pre-filter;
- `WikiOwnerScopeId`: an id with leading or trailing whitespace, a control or format character, invalid UTF-8 or more
  than 64 characters owns nothing (dropped by the resolver, refused by `WikiSpaceService::create()`), never normalised;
- `WikiSpaceCollationListener`: binary NO PAD collation on `owner_scope_id` (MySQL 8 `utf8mb4_0900_bin`, MariaDB
  `utf8mb4_nopad_bin`, MySQL 5.7 `utf8mb4_bin`). Existing tables: [UPGRADING › Unreleased](UPGRADING.md#to-1330).

## Content safety

- Attachments (1.9): stored under server-generated keys (`<space id>/<32 hex>`, no user input) in a private
  directory; uploads are checked against the size, the space quota and an extension + `finfo` MIME allowlist (no SVG,
  HTML, XML or scripts) and a scanner hook before anything is stored; downloads send `X-Content-Type-Options: nosniff`,
  `Cache-Control: private, no-store`, `Content-Security-Policy: default-src 'none'; img-src 'self'; sandbox` and
  `Content-Disposition: attachment` except for PNG / JPEG / GIF / WebP. `WikiAttachmentReferenceFilter` removes
  attachment URLs of other spaces from page bodies on save.

- Sanitize **on write** in `WikiPageService` and **again on read**: `manage/page_view.html.twig` renders the body with the `nowo_wiki_safe_html` filter (not `|raw`), so revisions stored before a sanitizer fix are cleaned too. If you override that template, keep the filter.
- `WikiHtmlSanitizer` is built on `symfony/html-sanitizer`: allowlisted elements only, per-element attributes, links http/https/mailto (or relative), media http/https (or relative), `style` only `text-align`, iframes only with an `https` `src` on `WikiHtmlSanitizer::DEFAULT_EMBED_HOSTS` (YouTube, youtube-nocookie, Vimeo). To allow other embed hosts, register `WikiHtmlSanitizer` with a custom `$allowedEmbedHosts` argument (or alias `WikiHtmlSanitizerInterface` to your own service).
- 1.11: `nowo_wiki.sanitizer.extra_allowed_elements` / `extra_allowed_attributes` widen the allowlist for editor dialects, but `WikiHtmlSanitizer::assertSafeAllowlist()` refuses (at configuration time and in the constructor) script-capable, embedding and form elements, `style`, URL attributes and every `on*` handler. Markdown revisions are rendered with raw HTML escaped and unsafe links dropped, then sanitized like HTML.
- 1.13, `editor.type: milkdown` (Milkdown ↔ wiki bridge): Markdown is rendered by `MilkdownWikiMarkdownRenderer` under the Milkdown renderer's own settings and limits (`nowo_milkdown_editor.markdown`: raw HTML escaped or stripped, unsafe links always dropped, remote images turned into links, bounded nesting, `max_input_length` / `max_inline_markers` / `max_references`), then sanitized by `WikiHtmlSanitizer` — the Milkdown escaping is never bypassed and the wiki sanitizer runs on top. The dialect adds `details` / `summary` and `role`, `aria-label`, `data-math`, `data-directive`, `data-icon` to the allowlist; SVG stays forbidden, so icons are placeholders drawn after sanitizing (`WikiMilkdownIconHydrator`) only for names on the icon allowlist, with the configured sprite URL (relative or http(s)).
- 1.16.2, ids and directive fallbacks: the sanitizer keeps an `id` only when it is a renderer anchor (`AnchorIdAttributeSanitizer`): `nowo-h-<slug>` on `h1`…`h6` (letters, marks, digits, single hyphens, at most 90 characters), `nowo-fn-<8 hex>-<n>` on `li`, `nowo-fn-ref-<8 hex>-<n>[__<k>]` on `sup`; any other `id` (on any element, even one a host allowed) is dropped, so page content cannot clobber globals, `Object.prototype` or host element ids. `name` is in `FORBIDDEN_ATTRIBUTES` (no host can allow it), and a host `id` in the allowlist logs a deprecation. Heading ids are de-duplicated in linear time and capped at 1 000 per document (`WikiHeadingIds`, EP-L1). Footnote ids never contain the user's label (`WikiFootnoteIds`). Table alignment is a class (`nowo-wiki-align--*`), never `align` or a new `style`. Leaf directives no host renders become escaped text, or a link only when the URL passes both `DialectRenderer::safeUrl()` and the UiKit URL policy (same-origin `/path` or `https://` without userinfo); a same-origin path with `//`, a `.` / `..` segment or `%2F` / `%5C` is never linked.
- **No title of another viewer is ever stored:** the `content_html` cache (shared by every reader, search excerpts and the AI context) is rendered viewer-neutral — no page, user or host-block resolution, page links read *Content not visible*. The page view renders the Markdown again for the viewer; `WikiMilkdownPageResolver` returns a page only when it is not archived, the viewer passes the list gate and `View` is granted on the space, the page and each ancestor (no authenticated, firewall-trusted user: nothing resolves). Exports write file links only for pages in the export (pages the exporting user may read); other links stay ids.
- Tiptap variant `notion` is layout only; it does not replace server-side sanitization.
- For high-security deployments, add a Content-Security-Policy header in the application layout override. The bundle templates are CSP-friendly (FR-006, REQ-UI-003): no inline `style="…"` attributes, no `on*="…"` event handlers, no `<style>` blocks, no CDN asset and no `confirm()` (Archive / Restore use the UiKit confirm `<dialog>`), and every bundle `<script>` (`wiki.js`, the Tiptap editor) carries `nonce="…"` from the request attribute **`csp_nonce`** when it is present (nothing is printed otherwise); the UiKit scripts follow the same attribute. Set it in your CSP listener before rendering (`$request->attributes->set('csp_nonce', $nonce)`) with the same value as `script-src 'nonce-…'`. `tests/Unit/Resources/TemplateLintTest.php` fails on any inline style, handler, `<style>`, CDN URL or un-nonced `<script>` in the bundle views, and the UiKit page contract test (`tests/Integration/Contract/PageContractTest.php`) checks the rendered pages. Page bodies may keep `style="text-align: …"` from the sanitizer allowlist: allow `style-src-attr 'unsafe-inline'` or drop `style` from the sanitizer if your policy forbids inline style attributes.

### Page files (1.20)

`page_files` reads file cards and images from Markdown that any editor writes. The list keeps relative and http(s)
URLs only (entities decoded first, protocol-relative and over-long URLs dropped), cleans names (control and invisible
format characters), and Twig escapes every value; links carry `rel="noopener noreferrer"`. The list shows the
current revision to readers of the page only. It reads the **Markdown**, not the rendered page: with Milkdown's own
`::file` cards it shows what the page shows, but a host `DirectiveRendererInterface` that renders `::file` itself
(e.g. hides a file from some readers) is not consulted — keep `page_files.enabled` off in that case.

The purge event hands **untrusted, editor-supplied URLs**, not storage keys: a page body can name any URL, including
another space's file. A host must map each URL to its own records by lookup (never by building a path: relative URLs
may contain `..`), delete only files recorded for the purged pages' space / pages at upload time, and skip files
another page still refers to. The event carries at most `page_files.max_purge_files` files (`truncated` otherwise).

## Co-editing threat model (1.13)

A page form saved outside the live session (hub down, editor standalone or offline), or a restore, while the log holds updates no version has writes nothing and answers 409, keeping the person's text (`collaboration.save_conflict`, default on). The check, the write and the drop run with the log's row locked in one transaction, with the bundled store, so an update waits until they end. The reset reaches live editors after the commit; a hub failure does not undo the save. Every reset changes the log's name (`log_epoch`): with Milkdown 1.12 an update or compaction from another log is refused under the write's lock, and a save from another log asks. "Replace for everyone" needs the edit right (for a restore, the restore right), the form's CSRF token and a deliberate click: the same right as "Reset collaboration". Residuals: an import, conversion, host call, console save or log purge replaces the document without asking; a tab that never bound (hub down, standalone), opened before a Replace, with nothing unsaved on the new log, saves with no ask: plain last-write-wins, to be closed by `base_revision` in the REST API; a client before Milkdown 1.12, or a host's own store without `WikiCollabEpochStoreInterface`, can still post an old document into a new log (audit HV-M1); a host's own store without `WikiCollabLockingStoreInterface` has no lock, so an update arriving between the check and the save is dropped; an update stored while a session save is in flight counts as saved although that save's text may not hold it, so a later save outside the session drops it, unless the client says which updates its text includes (`_nowo_milkdown_collab_seq`, Milkdown 1.11 or later) and the store implements `WikiCollabNumberedStoreInterface`, as the bundled one does. An editor can send a number of their choice: it is capped at the end of the log and never lowers what is recorded, and that editor could press Replace anyway; an update that waited for the lock lands on the emptied log, and so does one from a peer whose reset never arrived; after a replace, a peer whose reset never arrived can save its session text over it, and the history keeps both; a deadlock on the write of the current revision, outside the lock, answers 500 with the new revision in the history but not current, and the next save is fine.

| Question | Answer |
|----------|--------|
| Who can subscribe? | Only a browser that got the `mercureAuthorization` cookie from `POST …/collab/token`, which needs a firewall-trusted user, `canEdit()` and `View` + `Edit` on the space and the page. The JWT lists that one topic and grants no publish right; every message is a **private** Mercure update, so the hub drops it for anyone else. Readers never get the editor option nor the cookie. The cookie lives as long as symfony/mercure's `cookie_lifetime`: revoking edit rights stops new tokens, updates and state reads at once, but an open subscription only ends when the connection drops or the hub ends it at the subscriber JWT's expiry (the Mercure.rocks hub disconnects subscribers whose JWT expired); the client then reconnects through `POST token`, which checks the edit right again — keep `mercure.cookie_lifetime` short (e.g. 600 s). |
| Who can publish? | Nobody but the server: browsers POST to the wiki, which checks the edit permission on **every** request (state, update, awareness, compact), validates and publishes with its own publisher JWT. |
| Who can replay? | The hub keeps no history for the client; missed messages come from `GET …/collab/state`, again behind the edit check. A replayed update is harmless (Yjs updates are idempotent). Old logs are dropped on reset; deleted pages keep orphan rows until you purge them. |
| CSRF | POSTs must be same-origin (`Origin`, or `Sec-Fetch-Site` when absent) and JSON (`Content-Type: application/json`, 415 otherwise) — a cross-site form cannot send them. The token POST has no body and gets the same origin check. |
| Size | Each body ≤ ~4/3 of the decoded limit + 64 bytes, JSON depth ≤ 4, `d` strict base64; decoded `max_update_bytes` (256 KiB) for updates and presence, `max_snapshot_bytes` (4 MiB) for snapshots. Per page the log holds at most `max_log_entries` (2 000) updates — then 429 until a client compacts — and `max_log_bytes` (16 MiB) in total — then it is dropped (409, editors reload). Only the newest snapshot is kept and a compaction may not cover less than it. Budget checks use counters (`COUNT`, `SUM(LENGTH)`), never the log data. |
| Rate | `collaboration.rate_limiter` (a Symfony `RateLimiterFactory`, keyed by user identifier + page id) on **every** request — token, state, update, awareness, compact; required (the container refuses to compile without it). Optional `collaboration.user_rate_limiter` (1.13.1, CW-I9) adds a budget per user across all pages. Presence messages fan out to every subscriber of the page: size the limit with that in mind (e.g. sliding window 600 / minute). |
| Content | The server never decodes Yjs and never renders it: what is saved is the Markdown the saving client submits, through the normal form, renderer and sanitizer. A malicious editor can post updates or a snapshot that changes or empties the shared document for every live co-editor; saves from the session keep that log. **Recovery:** any editor presses *Reset collaboration* (edit right + CSRF + confirmation) or saves outside the session, which drops the log and reloads everyone from the last saved Markdown; a log a client cannot apply is reported and dropped automatically (`corrupt`, idempotent, capped per page and window so a hostile log cannot cause a reset loop); restores bring back any earlier revision. It cannot inject anything an editor could not type, and saved revisions stay in the history. |
| Seeding / GET | `GET state` has no side effect (CW-I3): the seed claim is made by the origin-checked `POST token`, per user and tab. |
| Resets | `corrupt` and `reset` are POSTs behind the same edit check, origin check and rate limiter; `reset` also needs its CSRF token. Together they reset a page at most `corrupt_resets` times per `corrupt_window` (no reload loop), and a reset of an already empty log is not broadcast. Compactions of a page are serialised (row lock) and one covering less than the stored snapshot changes nothing, so interleaved compactions never leave an empty log. The byte budget is checked again after each append. Idle logs are purged by `wiki:collab:purge` (1.13.1, CW-I10: at least 1 day, under a row lock, and the live editors of a purged page are told to reload). Appends take the same row lock first and purges also drop updates left without a document (DA-L1). `collaboration.enabled` must be a literal boolean (an env placeholder is refused at compile time, DA-M1), and the schema listener only touches the wiki's entity manager. An append to a log purged meanwhile is refused with 409 `log_reset` (the client reloads) instead of reviving it, and document rows are inserted with the platform's insert-or-ignore form, so a concurrent insert never aborts a PostgreSQL transaction. A capped manual reset says so with a flash message (CW-I7). |
| Identity | Co-editors see an opaque "Editor xxxx" label and a colour from an HMAC (`kernel.secret`) of the page id and the identifier (1.13.1 default): stable on a page, not linkable across pages, not reversible against a user directory (DA-L2). With `collaboration.show_names: true` they see each other's display name only when it is made of Latin letters (Basic, Latin-1, Extended-A / B), digits, spaces and common punctuation, has no format / bidi control and no `@`, and — lower-cased, accents transliterated, separators removed — neither equals nor contains the login or its e-mail local part; otherwise the opaque label is shown (CW-I2, DA-L3). Leet variants (`al0pez`) are not detected. The label key is a subkey of `kernel.secret` (`hash_hmac('sha256', 'nowo_wiki_collab_label', secret)`); an empty secret is refused (DA-I5). |
| Mercure claims | Never put `mercure` (or `authorization_details`) keys in the hub's default JWT claims: symfony/mercure merges them into the wiki cookie, and a default `subscribe: ['*']` would replace the page topic (CW-I12). |
| Hub | Needs private updates, cookie authorization (the hub on the app's registrable domain, `SameSite`), no anonymous subscribers for these topics, TLS. FrankenPHP's built-in Mercure works. |

## Comments threat model (1.16)

| Question | Answer |
|----------|--------|
| Who can read? | Viewers with `WikiAction::ViewComments` on a page they may read with every ancestor (1.13.2 rule): 404 otherwise, 403 without the right. The toggle, the count and the panel are not rendered for others. Default deny: the 1.x legacy mapping grants no comment right and `grants()` never asks for them. |
| Who can write? | `Comment` (start, reply, react, refresh an anchor; edit / delete own), `ResolveComment`, `ModerateComments` (edit / delete any). Every endpoint checks again; the `can` flags in the HTML and the JSON are hints. |
| CSRF | Writes must be same-origin (`Origin`, else `Sec-Fetch-Site`) and carry `X-CSRF-Token` (token id per page); the no-JavaScript form posts `_token`; the panel state endpoint has a token per space. JSON bodies need `Content-Type: application/json` (415). |
| Input | Bodies ≤ 16 KiB (413), JSON depth ≤ 16; comment Markdown 1–10 000 characters, valid UTF-8, no NUL; anchors validated with Milkdown's `CommentAnchor` (unknown keys dropped, caps, ≤ 16 KiB) and stored normalised; ids `^[A-Za-z0-9_-]{1,64}$`; reaction emoji ≤ 16 bytes of pictographs / modifiers only. |
| Output | Bodies are rendered per viewer with the page's Milkdown renderer (raw HTML escaped, unsafe links dropped, page links and mentions resolved for the viewer) and sanitized; the thread component copies an allow-list of that HTML with DOM APIs. The no-JavaScript list prints it through `nowo_wiki_safe_html`, inside the panel's `content_template` (never the side panel's raw `content`, UiKit audit DU-I1). Quotes are text. |
| Moderation panel (1.21) | Opt-in `comments.moderation_panel`, rendered only for `ModerateComments`. It adds no endpoint: it reads `GET …/threads` (same read rules, so a moderator without `ViewComments` has no panel at all) and calls the existing `DELETE …/threads/{id}` with the panel's CSRF token. Quotes are written with `textContent`. `comments.error_notices` adds static translated texts chosen by HTTP status, never server or user text. |
| Rate and size | `comments.rate_limiter` on every write (required), keyed per user across all pages; optional `comments.page_rate_limiter` per user and page. Caps (`comments.limits`): open threads per page and per user and page, also on reopen, and every thread of a page (1.16.1; resolved threads never lock a page nor hide open ones, which are listed first, and moderators delete whole threads), comments per thread, distinct emoji per comment, reactions per user and comment (422 `limit_reached`). The server-rendered list is read only when the panel is opened and shows at most 50 threads with their opening comment and latest 19 comments each (1.16.1). |
| Anchors and quotes | Viewers who may edit the page (or all, with `read_view_source`) get full anchors (the editor needs them). Others get only `quote.exact`, blanked unless every piece of it still occurs in the current revision's `content_text`, so text removed from the page does not stay readable (audit EI-L7). Any `Comment` holder may move an anchor and set its quote (contract §4); quotes come from canonical text, where atoms are U+FFFC, so they never carry Markdown, titles or per-viewer output. |
| Notifications | `renderBodyForNotification()` and (1.16.1) `visibleQuote()` (blank once the text left the page, EI-I15) render viewer-neutral (audit EI-L2): a listener runs in the actor's request, so a per-viewer render would show the actor's page titles to the recipient. `WikiCommentEvent` carries entities: never serialise it. |
| Privacy | `exportAuthor()` / (1.16.1) `exportAuthorData()` / `eraseAuthor()` and `wiki:comments:erase-user` (asks for confirmation unless `--force`; audit EI-L6, EI-I17), event `nowo_wiki.comment.author_erased`. Since 1.17 `eraseMentions()` (and the command's `--handle`) rewrites the user's mentions in other people's comments and thread quotes (not in page content); a label that could render as a mention is refused. Comment ownership follows `getUserIdentifier()`: identifiers must not be recycled. Stored quotes are page text that survives edits. |
| Names and pictures | Authors follow the history policy (opaque per-page "Editor xxxx" unless `history.show_names` and a safe name). A picture URL is resolved only for an author shown under a real name, sanitized by the UiKit avatar URL policy (https without userinfo, or a same-origin path) and rendered with `referrerpolicy="same-origin"`. |
| Live updates | With co-editing only: private Mercure topic `<collab topic>/comments`, granted with the co-editing topic in one subscriber cookie to viewers with `ViewComments`; messages carry thread ids, never bodies. |
| Page source | **By default the read view never sends the Markdown source to viewers who may not edit the page** (DN-I2): they get the server-rendered body and, in the panel, the open threads rendered by the server (inline ones with their quote, which is text the anchor already holds). Only editors get the read-only Milkdown mount (they can read the source in the editor anyway); its responses are `Cache-Control: private, no-store`. **`comments.read_view_source: true` (opt-in, default false)** gives the mount — and so the whole source — to every viewer with `ViewComments`: HTML comments, raw link targets, reference definitions, hidden or disabled blocks, front matter, page ids and directive arguments that the per-viewer render hides. Enable it only when everyone with `ViewComments` may read the source. |
| Storage | Threads, comments and reactions are deleted with their page: the trash purge deletes them explicitly (`WikiCommentRepositoryInterface::deleteForPages()`, children first) and the foreign keys cascade too on platforms that enforce them (audit EI-L5; SQLite without `foreign_keys` relies on the explicit deletes; a host deleting pages or spaces itself must call `deleteForPages()`). A user's deletion keeps their comments without an author (`SET NULL`) and drops their reactions; their page revisions stay too, shown as *Former person* (1.26, `author_id ... ON DELETE SET NULL`; until 1.25 they were deleted). Comments are not in exports, AI context or search. Every response that carries the panel or thread data is `Cache-Control: private, no-store`. |

## Page layout (1.29)

Audited in Appendix MA (2026-11-11, on `5cfe77a`): **Pass-Low** (MA-L1 table semantics, MA-I1–I3 informational), all
addressed in the follow-up commit. Re-verification of that delta (drawer state, table scroll boxes, dedupe anchors)
pending before the tag.

| Item | Behaviour |
|---|---|
| Drawer state | `wiki.js` posts the comments panel state only while the panel is a column (not at or under its `dock_min_width` breakpoint, not `layout: overlay`), so a phone / tablet toggle never overwrites the stored desktop choice. |
| Panel state endpoint | Unchanged checks (firewall-trusted user, readable space, same origin, CSRF token per space, JSON ≤ 16 KiB). It now also accepts `{panel: "tree_rail", open}`, only while `web_ui.tree_rail` is on, and `comments` only while comments are on (422 otherwise). The route exists when either feature is on. The stored value is a boolean per user and panel; nothing else is written. |
| Rail and panel markup | Every value is server-built and escaped by Twig: the rail toggle `href` is the request path + `?tree=rail|full` and goes through UiKit `nowo_ui_safe_href()`, `state_url` likewise (a refused URL renders no store attributes); `js/nowo-ui-rail.js` posts only to its own origin. The comments toggle in the compact header is bundle-rendered markup; no user input is printed raw. The dedupe title is an attribute-escaped data attribute read with `dataset` and compared as text. |
| localStorage | Only without a session and with the bundle's session store: keys `nowo-ui-panel:wiki-comments` / `nowo-ui-rail:wiki-tree` hold `open|closed` / `collapsed|expanded`, nothing per page or per user id; errors are ignored. |
| Title dedupe | A render-time removal of the leading `<h1>` of the body when it equals the title, before the usual sanitizing; the stored revision, search, export and the API are unchanged. A heading with more than text is kept; the removed heading's `id`s are re-emitted, HTML-escaped, on empty `<sup>` anchors (then sanitized as usual). |
| Rendered content | The body gets `data-nowo-ui-rendered-content`; each table is wrapped after sanitizing in a scroll box whose label is a translated, attribute-escaped string (`WikiTableScroll`; the regex runs on sanitized HTML, where `<` in text and attributes is escaped). The UiKit scroll-region script adds `tabindex` (plus `role="region"` and `aria-label` from the label) to an overflowing box or code block, and only `tabindex` to an overflowing table; the sanitizer allow-list is unchanged. |
| Worker mode | `WikiTreeRail` is a stateless readonly service; the layout options are container parameters; nothing is kept between requests (N-request test with two users in `WikiTreeRailTest`). |

## REST API (1.24, spec 218)

Off unless `api.enabled`. The host keeps the tokens; the bundle checks, on every request, the token's level (a closed table over `WikiAction`), the person's access and the token's *where*, behind a stateless secured firewall whose user must be the token's user. Known properties, for hosts and auditors:

| Item | Behaviour |
|---|---|
| Idempotent answers (IF-I12) | Kept 24 hours in `<table_prefix>_api_idempotency` (or the host's store), keyed by hashes of the token id and the key. They can hold a page's body, the source for an Edit token, and outlive the page's archive or purge until they expire. Purge them with `nowo:wiki:api:purge-idempotency`; call `forget()` on token revocation and account erasure |
| Replays (IF-I8) | A replay checks the token's reach again (the wiki, the page with its ancestors) but not its level or the person's rights: within 24 hours a token whose level was lowered gets back the bytes it already received, never anything new |
| First write of an unopened wiki (IF-I9) | The space is created through the directory hook before the person's `Create` right is asked, exactly as the person's first visit in the UI would |
| Search (IF-I13) | `truncated` says that `api.search_candidates` rows matched, counting rows the reader may not read: a coarse count, accepted (owner Q-O6). Excerpts are the viewer-neutral plain text |
| Events | In an API write, page events fire after the commit; a listener that fails is logged by class and does not refuse the write (IF-L3) |
| Creating a wiki (IF-L7, IF-L8) | The owner key the management hook names (`ownerKey()`) must be within the token's *where* before anything is created (404 otherwise). The new wiki is judged by its owner, so a restricted wiki the token just created is answered once; later requests follow the usual reach (a restricted wiki must be named). A hook that files the wiki under another owner is a host bug: the write is audited, an error names the hook class, the idempotent claim is kept so a retry does not create it again, and the answer is 500 `SERVER_001` |
| Managing a wiki (IF-L7) | When its space exists, the person must be able to open it (404 otherwise) and hold `Manage`; only a wiki nobody opened is decided on the level, the rest being the hook's |

## SearchKit provider (1.44)

Off unless `search_kit_provider` is on and SearchKitBundle >= 1.10.1 is installed (a security requirement, below) (1.46: SearchKit's provider base runs the
flow; the wiki's rules are `WikiPageSearchDefinition`). It follows SearchKit's anti-leak rule (deny by default; nothing
leaks through titles, excerpts, counts, filters or recents):

| Item | Behaviour |
|---|---|
| Viewer | Only SearchKit's user identifier is read (`IdentifierAwareSearchProviderInterface`), never the session user. The viewer comes from `viewer_resolver` or the user provider and must return exactly that identifier (`===`); a look-alike (case, padding) or unknown identifier finds nothing. With several user providers, configure `user_provider` as the firewall's own provider (not the chain `security.user_providers`): a chain answers with the first provider that knows the identifier, and two identity domains sharing one identifier cannot be told apart by `===` (audit TI-I3) |
| Role gate | `canList(viewer)` of `security.access_checker`. 1.44 (audit TI-M1): the default `ConfigurableWikiAccessChecker` decides a user other than the session's user on that user's own roles (`Security::isGrantedForUser()`), so a `searchFor()` for Bob inside Alice's request is gated on Bob's roles, never on Alice's; the session's own user is decided on the trusted token as on every wiki page. With no session user (console) it still denies. The REST API's principal matched to the firewall's user by id (a security adapter, `accepted_user_classes`) counts as the session's user and is judged on the token (audit TI-D2). `IS_AUTHENTICATED_*` / `PUBLIC_ACCESS` in a role list cannot be decided for another user and count as not granted (audit TI-D1). A host checker must likewise decide for its user argument |
| Tenant / space | The viewer's listed spaces with `View` are the only spaces in the SQL: another tenant's or space's rows are never read. An unknown and a forbidden scope id or `space:` value give the same answer (no group). 1.46: only the spaces of the **current tenant** (their `WikiLinkTenantResolverInterface` key equals `WikiSearchTenantResolverInterface::currentTenant()`), as for every quick-access list; no current tenant: no group; `tenant: all` widens it to every readable space. 1.47: several current tenants (`WikiSearchTenantsResolverInterface`): the union of exactly (`===`) those keys, `*` and empty keys dropped, at most 500 keys (more: no group), an empty list: no group; a `space:` filter, a scope or a recent outside the list finds nothing; the SearchKit tenant is the key or `tenants:` + SHA-256 of the JSON array of the sorted keys (1.47.1; keys with control characters or a `tenants:` prefix are dropped, so no two lists and no key share an id), re-derived on every call (a tenant value that is not the viewer's current one reads no space). 1.46: a scope id must match SearchKit's scope shape (`[A-Za-z0-9._:-]{1,128}`), anything else answers no group |
| Host rules (1.46) | `nowo_wiki.search_kit.spaces` (per space, before the SQL) and SearchKit's `nowo_search_kit.item_visibility` (per page, after the wiki's checks): narrowing only, a deny / exclusion wins whatever the listener order, neither event can be stopped, and both act before the window and the total, so a hidden row is never shown or counted |
| Page ACL | `View` on the page's space and on the page and every ancestor (a parent cycle is hidden), before counting and excerpting, on a fresh read (`HINT_REFRESH`) of the candidates and their ancestors, so a page renamed, moved or archived by another worker is judged on its current state; archived and trashed pages are never read (`archivedAt IS NULL`, checked again on the fresh read) |
| Totals and paging | Counted on the visible rows only. 1.46 (SearchKit's provider base): `exactTotal` unless the visible rows fill the candidate window, so hidden rows never change the total nor the exact flag. The results page slices the same visible list, so a page has no gap. Tests compare the JSON with and without hidden pages byte for byte |
| Candidate window (audit TI-L2) | 1.46: one query of at most `candidates` matching rows, title matches first, then the latest change. Everything the wiki decides itself is in that SQL: the tenant / team resolver, `View` on the space, the scope and `space:` filters, the host's spaces event, archived and trashed pages. The page rule (`View` on the page and every ancestor) is the host's PHP checker, and SearchKit's item visibility event is a host listener, so a page refused there is never shown or counted but still takes a place in the window: once the matches in the viewer's **own** readable spaces exceed the window, visible rows can be pushed out, and the number shown can then depend on how many refused pages match there (with SearchKit >= 1.10.1 a full window is never marked exact). Accepted residual risk, an existence signal inside the viewer's own spaces of the same kind as the timing below; other tenants' and spaces' pages are never in the SQL. A host that hides whole spaces should use the spaces event (in SQL), not the item event |
| Timing | The page ACL is a PHP checker, so a search reads up to `candidates` matching rows whatever the viewer may read; hidden matches cost one permission check each. The time therefore depends on how many rows match in the viewer's spaces, not on what is hidden in other tenants (they are not in the SQL). Accepted residual risk |
| Cost (audit TI-I4) | Per call one query of at most `candidates` rows (`LIKE '%q%'`, each row with its revision's HTML / text / Markdown) plus a fixed number of others (the viewer, the spaces, one fresh read of the candidates, one per two levels of ancestors), on every keystroke SearchKit sends and again for each results page. Keep the default of 200; raise it only with an indexed database and a SearchKit `min_length` / rate limit |
| Excerpts | The plain text of the wiki's own search (no markup, never the Markdown source); titles as stored. SearchKit prints both as text (DOM text nodes, Twig autoescape): a hostile title or body is inert (test with `<img onerror>` / `<script>`) |
| Links | Built by the router from `routes.page_view` (and `routes.search`): same-site paths only, never from stored data |
| Recents | Re-checked on every call for the viewer (no cache): the reference must be a `wiki` row on the page view route (or a `tenant_paths` copy); its slugs are looked up in the spaces the viewer may read now (after the spaces event), then the page is read again through every search rule and the item event and kept only when its URL is exactly the reference's. Unknown, archived, trashed, excluded and forbidden all answer null. Titles kept in the browser: with `sensitive: true` the group is off while SearchKit keeps recents without revalidation |
| Filters | Only `space:` is applied; a query with any other filter key gets no wiki group (it would otherwise widen the rows) |
| SearchKit version (audit VN-2) | **Security requirement: SearchKitBundle >= 1.10.1.** 1.9.x and 1.10.0 can mark a candidate window full of rows hidden in PHP as an exact total (a count side channel). The group registers nothing with an older or unknown version (Composer's installed version), and `composer.json` declares `conflict: nowo-tech/search-kit-bundle <1.10.1` |
| Excluded spaces (audit VN-4) | `search.excluded_spaces` only removes the wiki's own search page from those spaces; it does not hide them from the SearchKit group. Leave them out with a `nowo_wiki.search_kit.spaces` listener (`exclude()`) |
| Host entity manager (audit VN-3) | The group re-reads its candidates and their ancestors with `HINT_REFRESH`. When the wiki's entity manager is the host's own (no `database.private_entity_manager`), a search in the same request as an unflushed change the host made to a `WikiPage` overwrites that change in memory (the page and its parent chain get the database state back); flush before searching, or use the private entity manager. Nothing is written either way |
| Logging | None: no query text, no record data |
| Worker mode | Stateless services (nothing memoised; every hook resolves the viewer, the spaces and the rows again); tests reuse one container across viewers and right changes with no `kernel.reset`. Read-only: no write, flush, clear, transaction or reset of any entity manager, no route of its own |

## Secrets and cryptography

- The bundle stores no credentials and uses no cryptography of its own. CSRF tokens come from `symfony/security-csrf`; page and space ids are random UUIDs (`ValueObject\Uuid`), not secrets.
- AI provider keys (`OPENAI_API_KEY`, …) live only in the host environment and `config/packages/ai.yaml`; the bundle never reads, stores or logs them.
- Nothing secret is committed: demo `.env` files are git-ignored, `.env.example` holds placeholders only, and the demo MySQL is reachable only on the internal Docker network.
- Exported ZIPs are created with mode `0600` in the system temp dir and deleted after sending.

## Logging

Channel `nowo_wiki` (PSR-3, see [CONFIGURATION.md](CONFIGURATION.md#logging-req-obs-001)):

- **Logged:** start / finish / failure of imports and exports (space slug, format, counters), page archiving (space and page slugs), every AI agent call (space slug, question / answer **length**, number of context pages, exception class).
- **Never logged:** page HTML or Markdown, question / answer text, user ids or e-mails, passwords, session ids, API keys, exception messages (they may embed provider URLs).
- No `dump()`, `var_dump()` or `error_log()` in `src/`.

## Limits and DoS

| Resource | Limit |
| -------- | ----- |
| LLM call | `ai.http_timeout` (default 30 s, timeout + max duration); PHP / Caddy / FrankenPHP layers above it ([timeout hierarchy](CONFIGURATION.md#timeout-hierarchy-req-runtime-001)) |
| Upload | `import_export.max_upload_bytes` (default 50 MB); ZIP: ≤ 10 000 entries, ≤ 10 000 directories, ≤ 512 MiB uncompressed (declared and real), each `.md` ≤ `import_export.max_document_bytes` (default 8 MiB) |
| Lists | `pagination.*`: 24 spaces / page, 25 revisions / page, 500 pages in the tree; search capped at 25 hits |
| AI context | `max_context_pages` (8), `max_context_chars` (12 000) |
| Markdown (1.13, `milkdown`) | Milkdown's `max_input_length` (100 000 bytes), `max_inline_markers` (10 000), `max_references` (500), `max_nesting_level` (50): over the first two the page is shown as escaped text, unparsed |

## Dependencies

- `nowo-tech/tiptap-editor-bundle` (editor widget), `symfony/html-sanitizer` (stored HTML), Doctrine ORM (persistence), `league/commonmark` / `league/html-to-markdown` (interchange); `symfony/ai-bundle` is optional.
- CI runs `composer audit` on every push / PR (job **Composer audit**); Dependabot opens update PRs. Security fixes are released as patch versions as soon as they are available; advisories go through GitHub Security Advisories ([.github/SECURITY.md](../.github/SECURITY.md)).

## REQ-SEC-004

| Date | Version | Grade / residual risk | Record |
| --- | --- | --- | --- |
| 2026-11-10 | 1.28.0 (spec 212 block B, file previews) | With `file_cards: card` (opt-in) and `file_previews` (default on), `view="preview"` cards frame only the URL the host's `FilePreviewResolverInterface` approved for that card in this render, inside the `WikiRenderFrame` (under a reach, only host-reference cards that passed the reach-aware file resolution are asked). The URL passes Milkdown's `safeUrl()` and the wiki link policy (no `javascript:`, `data:`, `http:`, `//host`, ambiguous paths). The frame keeps Milkdown's sandbox (`file_preview_sandbox` through `FileCard::sandbox()`: never `allow-scripts` with `allow-same-origin` / `allow-popups-to-escape-sandbox`), `loading="lazy"`, `referrerpolicy="no-referrer"`. The sanitizer still refuses every `<iframe>` it does not allow-list: the frame passes as a placeholder with a 128-bit random token registered in the request-scoped `WikiFilePreviewFrames` (`kernel.reset`, at most 1000) and is restored by `nowo_wiki_safe_html` only if it is exactly Milkdown's frame markup; unknown placeholders are removed. Tokens are single-use; the REST reader Markdown renders without frames (audit KG, Pass-Low; KG-W-L1 / KG-W-I1 / KG-W-I4 fixed). The link policy accepts any `https://` URL a host resolver returns, so keep `frame-src` strict (KG-W-I2); comment thread JSON keeps an empty placeholder span (KG-W-I3). Residual: the host must list the preview origin in CSP `frame-src` and its endpoint must check the viewer and serve `CSP: sandbox` + `nosniff`; API HTML bodies keep an empty placeholder span. | Symfony Beacon spec 212 block B |
| 2026-11-08 | 1.25.0 (WK-8 / WK-6) | `editor.milkdown.file_cards: card` (opt-in) draws `::file` with Milkdown's `FileCard` in the per-viewer render. Its link is the URL of Milkdown's host file resolution (reach-aware, refused cards keep the placeholder without uuid, name or link) and must also pass the wiki link policy (`safeUrl()`: no `//host`, `http:`, ambiguous paths). No preview frames (no preview resolver; 1.28 adds them, see above). The neutral render is unchanged. WK-6 only removes duplicated header buttons. Appendix IK at `1e1f34c`: **Pass-Low** (IK-L1; IK-I4 to IK-I6). Fixed in docs: a host should remove a wider `file` renderer only with Milkdown ≥ 1.14 (IJ-I10 hardening: default ports, trailing dot, userinfo, dot segments) and every link host in `host_url_hosts` (IK-L1); `card` protects only while host files are resolved (IK-I4); `url: resolved` cards show the resolver's name and type (IK-I5). | Beacon requests WK-8, WK-6; Appendix IK |
| 2026-11-08 | 1.24.1 (Beacon request, Appendix IF follow-up) | The per-viewer render passes host file references to the host's resolver with the `WikiRenderFrame`. This covers Milkdown 1.13 `host_url_patterns` such as `/files/<uuid>/…`. Under the API's reach, a file outside *where* reads *Content not visible*, without uuid or alt text. A resolver that cannot see the frame resolves nothing under a reach. Open: Beacon's resolver must honour `reaches()`; file URLs written as plain text, and link text in the viewer-neutral search excerpts, are not references. | Spec 218, Appendix IF |
| 2026-11-08 | 1.24.1 (audit IJ fixes) | Appendix IJ at `6c060fa`: **Pass-Low** (IJ-L2 to IJ-L4). Fixed: leaf-directive `url` / `href` / `link` attributes that name host files go through the resolver and fail closed (IJ-L2); Beacon-ready sample with every file variant, `host_url_hosts` and `host_refused_links: hidden`, and Milkdown's URL normalisation (IJ-L3); UI changes documented (IJ-I9). **Open (IJ-L4):** search matching and excerpts, and HTML revisions' `content_text`, still use the viewer-neutral plain text, which keeps link text and `::file` labels of files outside the token's *where* (no uuid); documented in [API](API.md). `content_view: source` returns raw uuids by design (IJ-I7). Delta IJ at `12d3ef9`: **Pass-Low**; IJ-L4 accepted by the owner (2026-11-08) as a known limitation, to be fixed in 1.25. | Appendix IJ |
| 2026-11-08 | 1.24.0 (spec 218, REST API, S0–S7) | **Pass.** Independent audit IF (Appendix IF): IF-L1 to IF-L8 fixed; IF-I8, IF-I9, IF-I12, IF-I13 accepted and documented in [REST API](#rest-api-124-spec-218); IF-I4 (anchored links stay plain links) open, Informational. The API is off by default; with it on, every request checks level ∩ access ∩ *where* behind a secured stateless firewall, base-revision saves lock the page row, the reader's Markdown is derived from the reader's render, search never matches the source, and page events wait for the co-editing log's commit (IF-L2). | Spec 218 contract, APPENDIX-IF-Wiki-218 |
| 2026-11-07 | PR #103 → 1.23.0 | Pass (good) / Low at `1420705`. HV-M1 closed with Milkdown 1.12: every reset renames the log, so a tab that was away posts nothing and its save asks; run on outside save, Replace, restore, corrupt, purge and idle token in Chromium, Firefox and WebKit, on a real hub. HO-L1 window 2 closed with a numbered store: an update landing during a session save stays unsaved and the next save outside the session asks. Open: tabs with older JavaScript behave as before until reloaded; a tab that never bound saves last-write-wins when nothing is unsaved; a save right after typing can leave one false alarm, costing one Replace; PAGE_SAVED listeners run under the log's lock in a session save, so keep them fast. Add log_epoch before deploying the code. | Appendix HV |
| 2026-11-07 | PR #102 → 1.22.0 | Pass (good) / Low at `05b53d2`. HF-M1 closed: a page form saved outside the live session, or a restore, no longer drops updates no version has, unless the person confirms it or the host opts out. Open residuals: an update stored while a session save is in flight can still be lost; an import, conversion, host call, console save or log purge replaces the document without asking | `BUNDLES_SECURITY_ANALYSIS.md`, Appendix HO |
| 2026-11-07 | PR #101 → 1.21.1 | Pass (good) / Low at `eb54a9d`. GV-L3, GV-M2 and GV-L4 fixed and re-verified: extraction counts real bytes, takes only Markdown and caps directories, front matter is capped, and a fatal error leaves nothing. Open residual: many documents within the limits can exhaust `memory_limit` during the import and leave the pages written so far; a process killed outright leaves its temp files until a later import | `BUNDLES_SECURITY_ANALYSIS.md`, Appendix GV |
| 2026-11-06 | PR #100 → 1.21.0 | Pass (good) / Low at `6c32c6c`. M1, L1, L2, I1 and I3-I7 of the audit at `5009d15` fixed or documented and re-verified. Open residual: GV-L3, also in 1.20.0: an `Import` user can leave up to 512 MiB in the temp dir with a small archive when the read exceeds `memory_limit` | `BUNDLES_SECURITY_ANALYSIS.md`, Appendix GV |
| 2026-10-30 | PR #71 → 1.10.0 | Pass (conditional) / Medium at `4fad654`; M1, L1, L2, L3 and the cheap info items fixed in this PR (Pass (good) / Low once confirmed) | `BUNDLES_SECURITY_ANALYSIS.md`, Appendix BR |
| 2026-10-29 | 1.8.0 | Pass (good) / Low | Appendix BB |
| 2026-07-29 | 1.x | Pass (conditional) / Medium | AI audit + remedia |

Correction: 1.21.0 is Pass (conditional) / Medium; GV-M2 was found after its release.

Appendix BR (2026-10-30) findings and their status in 1.10.0:

| Id | Finding | Status |
| --- | --- | --- |
| M1 | The space export shipped pages hidden by a per-page `view` rule (also in 1.9.0) | Fixed: the web export keeps the readable pages and their readable ancestors only (`WikiDocumentExporter::export()` page filter); the CLI export (no user) stays complete |
| L1 | Attachment download ignored `access_roles` / the list roles | Fixed: `canList()` (404) on download, `canEdit()` on upload |
| L2 | Deprecated `<action>_roles: ~` / `roles.<action>: ~` widened to the default | Fixed: an explicit `~` means no role (`treatNullLike([])`), as in 1.9 |
| L3 | Space lists and the global AI question ignored per-space `view` / `ask_ai` of a custom checker | Fixed in the controller, `WikiContextRetriever` and `WikiKnowledgeSearchTool` |
| I1 | "Tree truncated" counted hidden pages | Fixed |
| I2 | Restore looked up the revision before the CSRF check | Fixed: CSRF first |
| I3 | Dist archives shipped demo, tests and tooling | Fixed: `.gitattributes` `export-ignore` |
| I4 | Demo loads Bootstrap from a CDN without SRI | Accepted (demo only) |
| I5 | Attachment quota is read-then-write | Accepted residual: concurrent uploads can exceed `max_space_bytes` by at most one file per concurrent request |
| BB L1 | `allow_unauthenticated` accepted in production | Fixed: literal booleans only, refused in `prod` / non-debug kernels |

Fixed in 1.10.0 for released versions: the 1.9.0 hidden-page disclosure (tree, archived list, search, export, AI context / sources / tool) and `security.access_roles` not being enforced in 1.0.0–1.9.0 (it was documented as a gate). Open residuals (BB): unbounded AI question length, wiki context in the system prompt (prompt injection), `class` allowed on sanitized elements.

## Reporting

See [.github/SECURITY.md](../.github/SECURITY.md) for coordinated disclosure.

## Release security checklist (12.4.1)

Confirm every item before tagging (note it in the release PR):

| # | Item | How to confirm |
| - | ---- | -------------- |
| 1 | `docs/SECURITY.md` up to date | Threats, limits and logging sections match the release |
| 2 | `.env` ignored | Root and `demo/symfony8/.gitignore` ignore `.env` / `.env.dev`; `git ls-files '*.env'` is empty |
| 3 | No secrets | No keys / passwords in the diff; `.env.example` holds placeholders only |
| 4 | Safe config / recipe | Defaults: `allow_unauthenticated: false`, `import_roles: [ROLE_ADMIN]`, AI disabled |
| 5 | Input / output | HTML sanitized on write and read (tests green); CSRF on every POST; slug route requirements |
| 6 | Dependencies | CI **Composer audit** green on the release SHA |
| 7 | No-secret logs | Log context reviewed against [Logging](#logging) |
| 8 | Cryptography | N/A beyond Symfony CSRF tokens and random UUIDs ([Secrets and cryptography](#secrets-and-cryptography)) |
| 9 | Permissions / exposure | Routes and role lists documented ([Access control](#access-control)); demo auto-login only for local dev |
| 10 | Limits / DoS | [Limits and DoS](#limits-and-dos) values unchanged or documented in CHANGELOG |
| 11 | AI security audit (REQ-SEC-004) | Latest grade recorded in [REQ-SEC-004](#req-sec-004) / `BUNDLES_SECURITY_ANALYSIS.md` is a pass |
