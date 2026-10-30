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

## Import / export

Import Outline or Notion Markdown exports into a space (directory or ZIP). Export a space back to Markdown (directory or ZIP).

**Console commands:**

```bash
bin/console wiki:import {spaceSlug} /path/to/export [--format=auto|outline|notion] [--update-existing] [--dry-run]
bin/console wiki:export {spaceSlug} /path/to/output [--format=outline|notion] [--zip]
```

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
```

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

The output still goes through `WikiHtmlSanitizerInterface` before it is stored.

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

