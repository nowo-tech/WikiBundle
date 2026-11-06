# Security

## Table of contents

- [Attack surface](#attack-surface)
- [Threats and mitigations](#threats-and-mitigations)
- [Access control](#access-control)
- [Content safety](#content-safety)
- [Comments threat model (1.16)](#comments-threat-model-116)
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
| **Unauthorized edit** | Viewer modifies pages. | `WikiAccessCheckerInterface` on all manage actions (`canEdit`, `canCreate`, etc.). |
| **CSRF on POST** | Forged save/archive requests. | `WikiCsrfTrait` validates tokens on edit, create, archive. |
| **Slug injection** | Path traversal or invalid URLs. | Route requirements `[a-z0-9]+(?:-[a-z0-9]+)*`; `WikiSlugger` validation. |
| **Revision tampering** | Delete or overwrite history. | Revisions are append-only; pages are soft-archived only (`BR-WIKI-002`). |
| **Search abuse** | Expensive LIKE queries. | `setMaxResults(25)` default; scope limited to accessible space. |
| **Malicious import** | ZIP bomb or oversized upload. | `import_export.max_upload_bytes` (default 50 MB); `WikiArchiveHelper` rejects archives with more than 10 000 entries or more than 512 MiB declared uncompressed size before extracting; archive extracted to temp dir; import requires `import_roles`. |
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

`WikiSpaceAccessCheckerInterface` is asked on every route with the resolved space and page. A space or page the user
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
`WikiSpaceAccessCheckerInterface`) for every page they return, so a page under a hidden parent never shows up there
either (audit DJ-I2).

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
| Storage | Threads, comments and reactions are deleted with their page: the trash purge deletes them explicitly (`WikiCommentRepositoryInterface::deleteForPages()`, children first) and the foreign keys cascade too on platforms that enforce them (audit EI-L5; SQLite without `foreign_keys` relies on the explicit deletes; a host deleting pages or spaces itself must call `deleteForPages()`). A user's deletion keeps their comments without an author (`SET NULL`) and drops their reactions. Comments are not in exports, AI context or search. Every response that carries the panel or thread data is `Cache-Control: private, no-store`. |

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
| Upload | `import_export.max_upload_bytes` (default 50 MB); ZIP: ≤ 10 000 entries, ≤ 512 MiB declared uncompressed size |
| Lists | `pagination.*`: 24 spaces / page, 25 revisions / page, 500 pages in the tree; search capped at 25 hits |
| AI context | `max_context_pages` (8), `max_context_chars` (12 000) |
| Markdown (1.13, `milkdown`) | Milkdown's `max_input_length` (100 000 bytes), `max_inline_markers` (10 000), `max_references` (500), `max_nesting_level` (50): over the first two the page is shown as escaped text, unparsed |

## Dependencies

- `nowo-tech/tiptap-editor-bundle` (editor widget), `symfony/html-sanitizer` (stored HTML), Doctrine ORM (persistence), `league/commonmark` / `league/html-to-markdown` (interchange); `symfony/ai-bundle` is optional.
- CI runs `composer audit` on every push / PR (job **Composer audit**); Dependabot opens update PRs. Security fixes are released as patch versions as soon as they are available; advisories go through GitHub Security Advisories ([.github/SECURITY.md](../.github/SECURITY.md)).

## REQ-SEC-004

| Date | Version | Grade / residual risk | Record |
| --- | --- | --- | --- |
| 2026-11-06 | PR #100 → 1.21.0 | Pass (good) / Low at `6c32c6c`. M1, L1, L2, I1 and I3-I7 of the audit at `5009d15` fixed or documented and re-verified. Open residual: GV-L3, also in 1.20.0: an `Import` user can leave up to 512 MiB in the temp dir with a small archive when the read exceeds `memory_limit` | `BUNDLES_SECURITY_ANALYSIS.md`, Appendix GV |
| 2026-10-30 | PR #71 → 1.10.0 | Pass (conditional) / Medium at `4fad654`; M1, L1, L2, L3 and the cheap info items fixed in this PR (Pass (good) / Low once confirmed) | `BUNDLES_SECURITY_ANALYSIS.md`, Appendix BR |
| 2026-10-29 | 1.8.0 | Pass (good) / Low | Appendix BB |
| 2026-07-29 | 1.x | Pass (conditional) / Medium | AI audit + remedia |

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
