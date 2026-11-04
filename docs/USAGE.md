# Usage — WikiBundle

## Table of contents

- [Manage UI routes (defaults)](#manage-ui-routes-defaults)
- [Import / export](#import--export)
- [Symfony AI](#symfony-ai)
- [Search](#search)
- [Application integration](#application-integration)
- [Pagination and list limits (REQ-PERF-001)](#pagination-and-list-limits-req-perf-001)
- [Twig overrides (REQ-TWIG-001)](#twig-overrides-req-twig-001)
- [Theming: classes, tokens and blocks (REQ-UI-003)](#theming-classes-tokens-and-blocks-req-ui-003)
- [Translations (REQ-I18N-001)](#translations-req-i18n-001)
- [Events](#events)
- [Links to host objects (1.9)](#links-to-host-objects-19)
- [Attachments (1.9)](#attachments-19)
- [Markdown pages (1.11)](#markdown-pages-111)
- [Milkdown editor (1.13)](#milkdown-editor-113)
  - [Co-editing (1.13)](#co-editing-113)
  - [Conversion report (1.13)](#conversion-report-113)

## Manage UI routes (defaults)

| Route name | Path |
|------------|------|
| `nowo_wiki_index` | `/tools/wiki` |
| `nowo_wiki_space` | `/tools/wiki/spaces/{spaceSlug}` |
| `nowo_wiki_page_view` | `/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}` |
| `nowo_wiki_page_edit` | `…/edit` |
| `nowo_wiki_page_history` | `…/history` |
| `nowo_wiki_page_diff` | `…/diff?from=N&to=M` |
| `nowo_wiki_search` | `/tools/wiki/search?q=…&space={slug}` (space optional — searches all accessible spaces) |
| `nowo_wiki_ai_ask` | `/tools/wiki/ask` (requires `nowo_wiki.ai.enabled`) |
| `nowo_wiki_space_export` | `/tools/wiki/spaces/{spaceSlug}/export` |
| `nowo_wiki_space_import` | `/tools/wiki/spaces/{spaceSlug}/import` (POST, multipart upload) |
| `nowo_wiki_page_delete` / `nowo_wiki_space_trash` / `nowo_wiki_trash_restore` / `nowo_wiki_trash_purge` | `…/pages/{pageSlug}/delete`, `…/spaces/{spaceSlug}/trash`, `…/trash/{batchId}/restore`, `…/trash/{batchId}/purge` (1.14, only with `trash.enabled`) |
| `nowo_wiki_page_move` | `…/pages/{pageSlug}/move` (1.14, only with `tree.enabled`) |
| `nowo_wiki_page_version_save` / `nowo_wiki_page_version_copy` | `…/pages/{pageSlug}/versions/new`, `…/pages/{pageSlug}/revisions/{revisionNumber}/copy` (1.15, only with `history.timeline`) |
| `nowo_wiki_space_tree` | `/tools/wiki/spaces/{spaceSlug}/tree/{move\|state}` (1.14, POST JSON, only with `tree.enabled`) |

## Import / export

Import Outline or Notion Markdown exports into a space (directory or ZIP). Export a space back to Markdown (directory or ZIP).

**Console commands:**

```bash
bin/console wiki:import {spaceSlug} /path/to/export [--format=auto|outline|notion] [--update-existing] [--dry-run]
bin/console wiki:export {spaceSlug} /path/to/output [--format=outline|notion] [--zip]
```

Every export holds a `README.md` index at its root (space title, export date, links to the exported pages); imports skip it.

Configure limits and ACL in `nowo_wiki.import_export` and `nowo_wiki.security.roles.{import,export}`.

## Symfony AI

When `nowo_wiki.ai.enabled` is `true` and `symfony/ai-bundle` is installed, the manage UI exposes `/tools/wiki/ask`. Answers use wiki search hits injected into the agent prompt (and optionally `WikiKnowledgeSearchTool` in the agent toolbox).

## Search

The manage UI includes a **full-text search** over:

- Page **titles**
- **Current revision** body: the Markdown source of a Markdown revision, else its HTML (tags stripped for excerpts)

Search respects ACL: only spaces returned by `WikiSpaceAccessResolver` are queried. Leave `space` empty to search **across all spaces** you can access.

Results show a **snippet** centered on the first match in the body (or a title fallback), plus the space badge when searching globally.

Example: `/tools/wiki/search?q=deploy&space=engineering`

## Application integration

1. Implement `WikiAccessCheckerInterface` (or use role-based defaults).
2. For team spaces, register `WikiTeamMembershipResolverInterface`.
3. Set **`nowo_ui_kit.layouts.admin`** (once for every UiKit bundle) to your project layout or a one-file bridge that renders the UiKit canonical blocks — every wiki page extends a UiKit scaffold that extends it and stacks `wiki.css` with **`{{ parent() }}`** (REQ-UI-001 / REQ-UI-003). `nowo_wiki.web_ui.layout_template` overrides it for the wiki only. Do not fork wiki pages; see [TEMPLATES.md](TEMPLATES.md).
4. Seed `WikiSpace` rows per team (or expose a create UI in your app).

## Pagination and list limits (REQ-PERF-001)

List pages of the manage UI are bounded by default (`nowo_wiki.pagination`, see [CONFIGURATION.md](CONFIGURATION.md#pagination-req-perf-001)):

| Page | Limit | Navigation |
| ---- | ----- | ---------- |
| Wiki index (spaces) | `spaces_per_page` (24) | `?page=N`, UiKit pagination |
| Page history (revisions) | `revisions_per_page` (25), one extra row is read to link the last "Compare" button; the total is the current revision number (history is append-only) | `?page=N`, UiKit pagination |
| Sidebar page tree (space + page view) | `max_tree_pages` (500) pages loaded with one query | a notice links to search when more pages exist |
| Search results | 25 hits | — |

Each list is one query (no N+1): spaces through `WikiSpaceRepositoryInterface::findAccessible()`, the tree through `findActiveBySpace($space, $max + 1)`, history through `findByPage($page, $limit, $offset)`. Exports (`/export`, `wiki:export`) are synchronous, write to a temp directory and stream the ZIP; they are bounded by PHP `max_execution_time` — use the `wiki:export` console command for very large spaces.

## Twig overrides (REQ-TWIG-001)

The bundle registers **`@NowoWikiBundle/`**. Application files under **`templates/bundles/NowoWikiBundle/`** always win over vendor: `TwigPathsPass` prepends that directory (when it exists) and appends the bundle views after it. Registration is unconditional; the routes follow `web_ui.enabled` (see [CONFIGURATION.md](CONFIGURATION.md)). Controllers render the logical names of `nowo_wiki.web_ui.templates.<page>` (default: the bundle page), so you can also point one page at another template from configuration.

**Freeze rule:** an application file at an override path always wins and will **not** pick up vendor changes for that path until you remove or merge it. Prefer the UiKit theme (`nowo_ui_kit.layouts`, `nowo_ui_kit.theme`, UiKit scaffold overrides) and `web_ui.templates.<page>` with a thin `{% extends '@NowoWikiBundle/…' %}` over copying wiki pages, so `composer update` keeps shipping UI fixes.

**Procedure:**

1. Pick the `<subpath>` from the table below.
2. Create `templates/bundles/NowoWikiBundle/<subpath>` in your application (same relative path and filename).
3. Clear the cache in dev if needed: `php bin/console cache:clear`.
4. Treat the file as a fork: it no longer follows bundle upgrades for that `<subpath>`.

Example: `templates/bundles/NowoWikiBundle/manage/_page_tree_item.html.twig` replaces one item of the sidebar tree only.

| Prefer | Notes |
|--------|--------|
| `nowo_ui_kit.layouts.admin` | Host chrome for every UiKit bundle (wiki pages included) |
| `nowo_ui_kit.css_framework` / `nowo_ui_kit.theme` | CSS stack, class / attribute / partial maps and `--nowo-ui-*` tokens, once |
| `nowo_wiki.web_ui.templates.<page>` | One wiki page: a thin template that extends `@NowoWikiBundle/manage/<page>.html.twig` and overrides one data block |
| Override one wiki partial | Surgical freeze when config is not enough |

```yaml
nowo_ui_kit:
    css_framework: bootstrap5
    layouts:
        admin: 'layouts/admin.html.twig'
```

### Overridable templates

Paths are relative to `src/Resources/views` (`@NowoWikiBundle/<subpath>`):

| `<subpath>` | Purpose |
| ----------- | ------- |
| `manage/index.html.twig` | Wiki index (UiKit list scaffold): spaces, global search filters, AI dialog |
| `manage/space.html.twig` | Space page (UiKit show scaffold): page tree, in-space search, import, archived pages |
| `manage/page_view.html.twig` | Page view (UiKit show scaffold): sanitized body (`nowo_wiki_safe_html`), tree, "Used in" |
| `manage/page_edit.html.twig` | Create / edit (UiKit form scaffold, `WikiPageFormType`, Tiptap editor script) |
| `manage/page_history.html.twig` | Paginated revisions (UiKit list scaffold) with Compare / Restore |
| `manage/page_diff.html.twig` | Diff of two revisions (UiKit show scaffold) |
| `manage/search.html.twig` | Search (UiKit list scaffold: filters + results) |
| `manage/ai_ask.html.twig` | AI question (UiKit form scaffold) with the answer and sources |
| `manage/_sidebar.html.twig` | Sidebar card with the page tree and the "tree truncated" notice |
| `manage/_page_tree.html.twig` | One level of the tree (`<ul class="nowo-wiki-tree">`) |
| `manage/_page_tree_item.html.twig` | One page of the tree: link, depth class, `aria-current`, its children |
| `manage/_ai_dialog.html.twig` | AI question `<dialog>` of the index (UiKit `_dialog`) |
| `manage/_legacy_layout.html.twig` | Deprecated bridge around a pre-1.10 layout (removed in 2.0) |

Page header, pagination, flashes, empty states, confirm dialogs and row actions are UiKit partials: re-skin them in UiKit (`templates/bundles/NowoUiKitBundle/…` or `nowo_ui_kit.theme.partials`), once for every bundle.

Renaming or removing one of these `<subpath>` values (or a stable `{% block %}`) is a breaking change and is announced in [UPGRADING.md](UPGRADING.md).

### Recovering upstream after a freeze

Delete (or rename aside) the application override, clear the Twig cache, then re-apply only the surgical changes you still need. Diff your copy against the new vendor file under `vendor/nowo-tech/wiki-bundle/src/Resources/views/<subpath>` before re-copying.

### Forms in templates (REQ-TWIG-005)

Every form in the manage views is a PHP Symfony Form Type (Form Kit `wiki` profile) built by `WikiManageController` and rendered with `form_start` / the child loop / `form_end`:

| View | Variable | Form Type |
| ---- | -------- | --------- |
| `manage/page_edit.html.twig` | `page.form` | `WikiPageFormType` — its own CSRF field (token id `wiki_edit_<page id>` / `wiki_new_<space id>`) replaces the former top-level `_token` |
| `manage/index.html.twig`, `manage/search.html.twig` (`page.filters`), `manage/space.html.twig` (`search_form`) | `page.filters` / `search_form` | `WikiSearchType` (GET, flat `space` / `q`) |
| `manage/space.html.twig` | `import_form` | `WikiImportType` (multipart, flat `format` / `archive` / `update_existing` / `_token`) |
| `manage/ai_ask.html.twig` (`page.form`), `manage/_ai_dialog.html.twig` (`ai_form`) | `page.form` / `ai_form` | `WikiAiAskType` (flat `space` / `q` / `_token`, token id `wiki_ai_ask`) |

Render custom children first, then the canonical loop so remaining children (CSRF, hidden fields) still appear:

```twig
{{ form_start(search_form, {attr: {class: nowo_ui_class('form')}}) }}
    {% for child in search_form %}
        {% if not child.rendered %}
            {{ form_row(child) }}
        {% endif %}
    {% endfor %}
    <button type="submit" class="{{ nowo_ui_class('btn_primary') }}">…</button>
{{ form_end(search_form) }}
```

Template overrides and demos **must not** reintroduce raw `<form>`, `<input>`, `<textarea>` or `<select>` markup; `make check-no-raw-html-form` (part of `release-check`) fails when they do.

## Theming: classes, tokens and blocks (REQ-UI-003)

Since 1.10 the wiki pages are UiKit pages: the host themes them through UiKit (`nowo_ui_kit.css_framework`,
`nowo_ui_kit.theme` classes / attributes / partials / `--nowo-ui-*` tokens, scaffold overrides) once for every bundle.
The page → scaffold map, the view models, the data blocks and the value-free page data are in
[TEMPLATES.md](TEMPLATES.md).

The wiki-specific regions carry semantic hooks styled by `wiki.css` with the UiKit tokens (no `--nowo-wiki-*` token
tree any more):

| Block | Elements and modifiers |
|-------|------------------------|
| `nowo-wiki-layout` | `__aside`, `__main` (sidebar + content grid, one column under 62 rem) |
| `nowo-wiki-sidebar` | `__header`, `__body` (`data-wiki-sidebar`) |
| `nowo-wiki-tree` | `--nested`, `__item`, `__item--depth-0` … `__item--depth-6` (capped; `data-wiki-depth` has the real depth), `__item--parent`, `__link`, `__link--current` (`aria-current="page"`), `__truncated`; root `data-wiki-tree` |
| `nowo-wiki-content` | the sanitized page body (`data-testid="wiki-content"`) |
| `nowo-wiki-diff` | `__lines`, `__line`, `__line--add`, `__line--remove`, `__line--same` (`data-wiki-diff`) |
| `nowo-wiki-archived`, `nowo-wiki-used-in` | `__list`, `__item`, `__title` / `__link` |
| `nowo-wiki-ai` | `__answer`, `__text`, `__sources`; `nowo-wiki-sources`, `__item` |
| others | `nowo-wiki-region-title` (card titles of these regions), `nowo-wiki-link`, `nowo-wiki-results__excerpt` |

**Blocks.** Use `nowo_wiki.web_ui.templates.<page>` with a template that extends the bundle page and overrides one
UiKit data block (`nowo_ui_list_row`, `nowo_ui_show_sections`, `nowo_ui_show_after`, `nowo_ui_form_body`,
`nowo_ui_form_aside`, `nowo_ui_modals`, …):

```twig
{# templates/wiki/page_view.html.twig, set as nowo_wiki.web_ui.templates.page_view #}
{% extends '@NowoWikiBundle/manage/page_view.html.twig' %}

{% block nowo_ui_show_after %}
    {{ include('wiki/_linked_issues.html.twig') }}
{% endblock %}
```

Templates carry no `style="…"`, no `on*="…"` handlers, no `<style>` block, no CDN asset and no `confirm()`; the editor
`<script>` prints the request attribute `csp_nonce` as `nonce="…"` when the host sets it (see
[SECURITY](SECURITY.md#content-safety)). `tests/Integration/Contract/PageContractTest.php` (UiKit `PageContractTestCase`)
enforces it for every bundle page.

## Translations (REQ-I18N-001)

Every UI string uses the **`NowoWikiBundle`** domain (`src/Resources/translations/NowoWikiBundle.<locale>.yaml`). Shipped locales: `en` (reference), `es`, `it`, `fr`, `pt`, `de`, `nl`; `make validate-translations` checks YAML syntax and key parity across them.

Application catalogues take precedence over the bundle, which only provides fallbacks for keys you do not define:

1. Use the same domain: `NowoWikiBundle`.
2. Create `translations/NowoWikiBundle.<locale>.yaml` (or `.xlf`) in your application.
3. Override only the keys you need; missing keys fall back to the bundle value.
4. Clear the cache in dev if needed: `php bin/console cache:clear`.

Example `translations/NowoWikiBundle.en.yaml`:

```yaml
wiki:
  index:
    title: Knowledge base
  space:
    new_page: Add article
```

To add a locale the bundle does not ship (e.g. `ca`), create `translations/NowoWikiBundle.ca.yaml` with the keys of `vendor/nowo-tech/wiki-bundle/src/Resources/translations/NowoWikiBundle.en.yaml`.

## Events

Dispatched after the change is flushed.

- `nowo_wiki.page.saved` — `WikiPageSavedEvent` (`page`, `revision`, `previousRevision`, `actor`; also after create and restore)
- `nowo_wiki.page.archived` — `WikiPageArchivedEvent` (`page`, `actor`)
- `nowo_wiki.page.created` / `.unarchived` — `WikiPageCreatedEvent` / `WikiPageUnarchivedEvent` (1.9)
- `nowo_wiki.page.restored` — `WikiPageRestoredEvent` (`restoredFrom`, `revision`, 1.9)
- `nowo_wiki.page.linked` / `.unlinked` — `WikiPageLinkEvent` (`objectType`, `objectId`, 1.9)
- `nowo_wiki.attachment.uploaded` / `.deleted` — `WikiAttachmentEvent` (1.9)
- `nowo_wiki.page.trashed` / `nowo_wiki.trash.restored` / `nowo_wiki.trash.purged` — `WikiTrashEvent` (`space`, `batchId`, `rootTitle`, `pageIds`, `actor`; 1.14, `trash.enabled`; after a purge the pages no longer exist)
- `nowo_wiki.page.moved` — `WikiPageMovedEvent` (`page`, `fromParent`, `toParent`, `index`, `actor`, `movedAt`; 1.14, `tree.enabled`): the audit entry of a move
- `nowo_wiki.editor.options` — `WikiEditorOptionsEvent`: add TiptapEditorType options (e.g. the suggestion hook's candidate URLs) for the space and page being edited (1.9)

## Links to host objects (1.9)

```php
final class IssueLinkResolver implements WikiObjectLinkResolverInterface   // autoconfigured
{
    public function supports(string $objectType): bool { return $objectType === 'issue'; }

    // One query for every id; leave out what the viewer may not open.
    public function resolveVisible(UserInterface $user, string $objectType, array $objectIds): array
    {
        $objects = [];
        foreach ($this->issues->findVisible($user, $objectIds) as $issue) {
            $objects[(string) $issue->getId()] = new WikiLinkedObject('issue', (string) $issue->getId(), $issue->getTitle(), $this->url($issue), $issue->getKey());
        }

        return $objects;
    }
}

$linkService->link($page, 'issue', '42', $user);   // authorize first: the bundle does not know who may link
$linkService->usedIn($page, $viewer);              // ['issue' => [WikiLinkedObject, …]]
```

## Attachments (1.9)

With `attachments.enabled`, the editor uploads dropped / pasted files to `attachment_upload` (POST multipart `file`,
header `X-CSRF-Token`, token id `WikiAttachmentController::csrfTokenId($page)`); the answer is
`{"id", "url", "name", "mime", "size", "image"}` (201) or `{"error": "too_large|quota_exceeded|type_not_allowed|type_mismatch|scanner_rejected|empty"}` (422).
Downloads (`attachment_download`) stream the file to users who may view its page and answer 404 otherwise.

Nightly cleanup (attachments never referenced by a revision 24 h after upload, files without a row):

```php
$attachments->purgeOrphans(new DateTimeImmutable('-24 hours'));
$attachments->purgeStrayFiles();
```

## Markdown pages (1.11)

With `nowo_wiki.editor.type: markdown` ([CONFIGURATION](CONFIGURATION.md#markdown-pages-111)) pages are written in Markdown (GFM: tables, task lists, strikethrough, autolinks). Raw HTML is shown as text and `javascript:` / `data:` links are dropped.

**Convert existing HTML pages** (one new revision per page, archived pages included, history untouched; pages already in Markdown are skipped, so it can run again):

```bash
bin/console wiki:convert-markdown {spaceSlug} [{spaceSlug}...] --author-id={user id or email} [--dry-run] [-v]
    [--page={pageSlug}]... [--report=conversion.json] [--skip-lossy] [--fail-on-loss]
```

1.13: `--page` limits the run to those pages of the given spaces (an unknown slug fails before anything is written). Every HTML page gets a fidelity entry, printed as a summary (pages with losses or approximations) and written with `--report` as JSON — see [Conversion report](#conversion-report-113). **Without `--dry-run` the command writes.** Every page is analysed before anything is written. `--skip-lossy` leaves pages whose conversion loses something (a lost construct or a changed word) in HTML; `--fail-on-loss` exits with 1 when any page loses something and then **writes nothing**. A page whose current revision changed after the analysis is skipped (`skipped_changed`); run the command again for it.

**Add Markdown syntax** (an editor bundle's dialect: page links, mentions, icons, directives) with a league/commonmark extension:

```php
final class WikiDialectExtension implements WikiMarkdownExtensionInterface   // autoconfigured
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addInlineParser(new PageLinkParser());   // [[wiki:<uuid>]] → link
    }
}
```

The output still goes through `WikiHtmlSanitizerInterface` before it is stored: declare the extra markup the extension emits in `nowo_wiki.sanitizer` ([CONFIGURATION](CONFIGURATION.md#sanitizer-allowlist-111)).

**Per-viewer rendering stays on the host:** the stored `content_html` is the same for every reader. Resolve page links, mentions and live blocks when the page is shown (current title and URL for a page the viewer may read, a neutral "not visible" placeholder otherwise).

**Conversion events:** `wiki:convert-markdown` dispatches `nowo_wiki.page.saved` with `$event->conversion === true`. Keep indexing (search, backlinks) on it, but do not notify watchers or log it as an edit:

```php
if ($event->conversion) {
    return;   // bulk HTML → Markdown conversion, not an edit
}
```

**Page references and backlinks:** the bundle's `<prefix>_page_links` table holds host objects linked by hand, so page-to-page references are not written there. A host keeping a backlink index reads them on save:

```php
#[AsEventListener(WikiEvents::PAGE_SAVED)]
public function onSaved(WikiPageSavedEvent $event): void
{
    $ids = $this->extractor->pageIds($event->revision->getContentMarkdown());   // WikiMarkdownReferenceExtractor
    $this->backlinks->replace($event->page, $event->revision, $ids);
}
```

`[[wiki:<uuid>]]` and `[[wiki:<uuid>#anchor]]` are found outside code spans and code blocks; `pageReferences()` also returns the anchors.

**Search, export and import** use the Markdown when a revision has it: search matches the source, export writes it unchanged, and imports store the Markdown bodies as Markdown revisions when `editor.type` is `markdown`.

## Milkdown editor (1.13)

With `nowo_wiki.editor.type: milkdown` ([CONFIGURATION](CONFIGURATION.md#milkdown-editor-113)) the page body is `MilkdownEditorType` and pages are written in the [Milkdown dialect](https://github.com/nowo-tech/MilkdownEditorBundle/blob/main/docs/USAGE.md#markdown-dialect): `[[wiki:<uuid>]]` / `[[wiki:<uuid>#anchor]]` page links, `@handle` mentions, `:tabler-<name>:` icons, `:::callout`, `:::details`, `::: {align=…}`, `:mark[…]`, `::name{…}` host blocks, `$…$` / `$$` math and Mermaid fences. Everything of [Markdown pages](#markdown-pages-111) applies, and the **bridge** makes every wiki surface understand the dialect — no raw token is ever shown:

| Surface | What it gets |
|---------|--------------|
| Page view | The Markdown rendered **for the viewer** on each request (`WikiRevisionBodyRenderer`): page links show the current title and URL of pages the viewer may read and *Content not visible* otherwise, mentions link through your `UserResolverInterface`, host blocks render through your `DirectiveRendererInterface` services; then sanitized, foreign attachment URLs dropped, icons drawn from the allowlisted sprite. `nowo_wiki_rich_content_assets()` loads the Milkdown read-side script and `wiki.js` draws Mermaid (strict) and math (bounded KaTeX) in `[data-wiki-rich-content]`. |
| `content_html` cache, search excerpts, AI context | A **viewer-neutral** render: no page, user or host-block resolution (page links read *Content not visible*, mentions stay `@handle`), so the cache shared by every reader never holds a title or a block of another viewer. |
| Link index | `WikiMarkdownReferenceExtractor::pageReferences()` / `pageIds()` / `mentions()` read the document with the renderer's own parser: exactly the links and mentions a reader sees (never inside code, never an e-mail address), at most `max_references` of each. |
| Export | Page links to pages in the export become ordinary Markdown links to the exported files (`[Title](setup.md#step-1)`, Outline and Notion layouts); links to other pages stay `[[wiki:<uuid>]]` (no title of a page outside the export is written). Icons stay shortcodes, emoji stay Unicode. |
| Editing an HTML page / `wiki:convert-markdown` | HTML is converted to the dialect: `[[wiki:…]]` and `@handle` typed in Tiptap stay tokens, `<mark>` → `:mark[…]`, centred / right-aligned blocks → `::: {align=…}`, embeds → links. |

**Limits** are Milkdown's (`nowo_milkdown_editor.markdown`, `%env()%` values are read at runtime): a document over `max_input_length` bytes or `max_inline_markers` inline delimiters is shown as escaped plain text, unparsed, and `MilkdownEditorType` refuses to save it; at most `max_references` distinct page ids and handles reach the resolvers.

**Page links in wiki pages** are resolved by `WikiMilkdownPageResolver` (one query per document, each space decided once): not archived, the general list gate, and `WikiAction::View` on the space, the page and each ancestor. Point `editor.milkdown.page_resolver` at your own service to change it (e.g. Beacon's resolver that also links issues and tasks).

### Co-editing (1.13)

Several people edit one Milkdown page at once (FR-076): Yjs in the browser (`@milkdown/plugin-collab` in nowo-tech/milkdown-editor-bundle ^1.2), Mercure for the relay, the wiki for permissions and the stored Yjs log.

```yaml
nowo_wiki:
    editor: { type: milkdown }
    collaboration:
        enabled: true
        hub_url: '%env(MERCURE_PUBLIC_URL)%'      # the browser subscribes here
        topic_prefix: 'https://wiki.example.com/pages/'
        rate_limiter: limiter.wiki_collab         # framework.rate_limiter.wiki_collab (required)
```

The page edit form passes MilkdownEditorType the `collaboration` option (base URL, hub URL, topic, user name and colour) — only on the edit form, so readers never get it. The browser then calls, all under `routes.page_collab` (`…/pages/{pageSlug}/collab/{action}`):

| Request | Answer |
|---------|--------|
| `POST …/collab/token` | 204; sets the `mercureAuthorization` cookie (subscriber JWT for this page's topic only); drops a stale log and claims the seeding of an empty one for this user |
| `GET …/collab/state` | `{"state": "<base64 snapshot>", "updates": ["<base64>", …], "seq": 12, "compact": false, "seed": true}` — read only |
| `POST …/collab/update` `{c, d}` | 204; the update is appended to the log and published (`{"t":"u","c":…,"d":…}`, private) |
| `POST …/collab/awareness` `{c, d}` | 204; presence published (`{"t":"a",…}`), never stored |
| `POST …/collab/compact` `{c, d, s}` | 204; the log up to `s` is replaced with the snapshot `d` |
| `POST …/collab/corrupt` `{c, s}` | 204; the log `s` could not be applied: dropped (capped), editors reload |
| `POST …/collab/reset` (form, `_token`) | 302 to the edit form; the editor's explicit reset |

Every request checks a firewall-trusted user, `canEdit()` and `WikiAction::View` + `WikiAction::Edit` on the space and the page (404 when the page is not readable, 403 when not editable); POSTs must be same-origin and JSON. Every request is rate-limited per user and page. Errors answer `{"error": "<reason>"}`: 400 `invalid_payload`, 409 `log_reset` (the log went over `max_log_bytes` and was dropped; editors reload), 413 `too_large`, 415 `json_required`, 429 `rate_limited` / `log_full`.

**Seeding:** the token request of the first editor of an empty log claims the seeding for that client (user + the tab's Yjs client id, header `X-Nowo-Collab-Client`: one tab seeds even when the same user opened two); `GET state` then answers `seed: true` to that client, which fills the document from the field's Markdown. A claim not fulfilled within `seed_timeout` seconds may be taken again.

**Reset only when needed:** a save made from the live session — the Milkdown client adds `_nowo_milkdown_collab` (the page topic) to the form, and the log's base is the revision being replaced — keeps the log and records the new revision; nobody reloads. The log is dropped and `{"t": "r"}` published (live editors show a notice and reload the saved content) only for:

- a save outside the live session (no session field, e.g. an editor whose collaboration is off or offline) or with a stale base;
- a restore, an import, a conversion or any other save (console, host API);
- a client that cannot apply the log: it posts `POST …/collab/corrupt` `{c, s}` with the sequence number it got; idempotent (a report about a log already reset or grown since changes nothing) and capped at `corrupt_resets` per page per `corrupt_window` seconds (429 `reset_capped` beyond);
- an editor's **Reset collaboration** button on the edit form (POST + CSRF behind a confirmation, edit permission; rate-limited and counted in the same `corrupt_resets` cap; a log that is already empty is not broadcast again);
- a log over `max_log_bytes` (409 `log_reset`).

**Footprint and purge:** per page the log holds at most `max_log_entries` updates (each ≤ `max_update_bytes` decoded) and `max_log_bytes` in total (default 16 MiB), plus one snapshot (≤ `max_snapshot_bytes`); one editor typing produces small updates (tens of bytes each), presence is never stored. Logs of idle pages are deleted by `bin/console wiki:collab:purge --older-than=7` (days; schedule it daily) — nothing is lost, the next editor seeds a new log from the saved Markdown.

As a safety net, a log whose base revision is not the current one is dropped on the next token when it is empty or idle for `reset_after_idle` seconds. **Names:** co-editors see an opaque "Editor xxxx" label by default (1.13.1). Set `collaboration.show_names: true` to show each other's display name (`getDisplayName()` / `getName()` of your user class); a value equal to the login identifier or shaped like an e-mail address still falls back to the opaque label.

Your own endpoints (e.g. Beacon routes) can call `WikiCollabService` directly — check the edit permission first, as `WikiCollabController` does. Replace the storage, the publisher or the cookie with `collaboration.store`, `publisher`, `subscriber_authorizer` (`WikiCollabStateStoreInterface`, `WikiCollabPublisherInterface`, `WikiCollabSubscriberAuthorizerInterface`).

### Conversion report (1.13)

`--report=<file>` writes:

```json
{
    "format": "nowo-wiki-conversion-report",
    "version": 1,
    "generated_at": "2026-11-02T20:00:00+00:00",
    "dry_run": true,
    "dialect": "milkdown",
    "totals": {"pages": 3, "converted": 2, "skipped_markdown": 1, "skipped_empty": 0, "skipped_lossy": 0, "skipped_changed": 0, "lossless": 1, "with_approximations": 0, "with_losses": 1},
    "pages": [
        {
            "space": "eng", "page": "runbook", "page_id": "0b6f4f0e-…", "title": "Runbook",
            "outcome": "would_convert", "from_revision": 3, "to_revision": null,
            "lost": {"underline": 2}, "approximated": {"table_cell_span": 1},
            "text": {"preserved": true, "similarity": 1.0, "words_missing": 0, "words_added": 0}
        }
    ]
}
```

`outcome`: `converted` (`would_convert` in a dry run or when `--fail-on-loss` blocked the writes; then `dry_run` is true), `skipped_markdown`, `skipped_empty`, `skipped_lossy`, `skipped_changed` (edited during the run). `lost` / `approximated` / `text` are present for HTML pages only. `text` compares the words of the HTML with the words of the converted Markdown once rendered (page links and icons left out on both sides).

| Lost | Meaning |
|------|---------|
| `underline`, `subscript`, `superscript` | The text stays, the formatting is dropped |
| `highlight` | `<mark>` without the dialect (`markdown` editor) |
| `alignment` | Justified text; centred / right-aligned text without the dialect |
| `image_size`, `column_width` | Image width / height, table column widths |
| `list_style` | Letter or Roman numbering (`<ol type="a">`) |
| `embed` | An embedded video without the dialect |
| `element:<tag>` | An element the converter drops (its text is kept) |
| `render_limit` | The Markdown is over the renderer's limits and would show as plain text |

| Approximated | Meaning |
|--------------|---------|
| `table_cell_span` | `colspan` / `rowspan` (GFM cells do not span) |
| `table_cell_blocks` | Paragraphs, lists or code inside a cell (flattened to one line) |
| `table_without_header` | GFM tables need a header row: the first row becomes it |
| `nested_table` | A table inside a table |
| `table_caption`, `figure_caption` | The caption becomes text |
| `embed_as_link` | An embedded video becomes a link to it (dialect) |

