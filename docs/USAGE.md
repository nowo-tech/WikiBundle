# Usage — WikiBundle

## Table of contents

- [Manage UI routes (defaults)](#manage-ui-routes-defaults)
- [Import / export](#import--export)
- [Symfony AI](#symfony-ai)
- [Search](#search)
- [Application integration](#application-integration)
- [Pagination and list limits (REQ-PERF-001)](#pagination-and-list-limits-req-perf-001)
- [Twig overrides (REQ-TWIG-001)](#twig-overrides-req-twig-001)
  - [Overridable templates](#overridable-templates)
  - [Recovering upstream after a freeze](#recovering-upstream-after-a-freeze)
  - [Forms in templates (REQ-TWIG-005)](#forms-in-templates-req-twig-005)
- [Translations (REQ-I18N-001)](#translations-req-i18n-001)
- [Events](#events)

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

Configure limits and ACL in `nowo_wiki.import_export` and `nowo_wiki.security.{import,export}_roles`.

## Symfony AI

When `nowo_wiki.ai.enabled` is `true` and `symfony/ai-bundle` is installed, the manage UI exposes `/tools/wiki/ask`. Answers use wiki search hits injected into the agent prompt (and optionally `WikiKnowledgeSearchTool` in the agent toolbox).

## Search

The manage UI includes a **full-text search** over:

- Page **titles**
- **Current revision** HTML (tags stripped for excerpts)

Search respects ACL: only spaces returned by `WikiSpaceAccessResolver` are queried. Leave `space` empty to search **across all spaces** you can access.

Results show a **snippet** centered on the first match in the body (or a title fallback), plus the space badge when searching globally.

Example: `/tools/wiki/search?q=deploy&space=engineering`

## Application integration

1. Implement `WikiAccessCheckerInterface` (or use role-based defaults).
2. For team spaces, register `WikiTeamMembershipResolverInterface`.
3. Prefer **`nowo_wiki.web_ui.layout_template`** (or `templates.layout`) pointing at your host layout — manage pages extend `@NowoWikiBundle/base.html.twig`, which stacks `wiki.css` / `wiki.js` with **`{{ parent() }}`** (REQ-UI-001). Avoid forking every manage page.
4. Seed `WikiSpace` rows per team (or expose a create UI in your app).

## Pagination and list limits (REQ-PERF-001)

List pages of the manage UI are bounded by default (`nowo_wiki.pagination`, see [CONFIGURATION.md](CONFIGURATION.md#pagination-req-perf-001)):

| Page | Limit | Navigation |
| ---- | ----- | ---------- |
| Wiki index (space cards) | `spaces_per_page` (24) | `?page=N`, previous / next + "Page N of M" |
| Page history (revisions) | `revisions_per_page` (25), one extra row is read to link the last "Compare" button and detect an older page | `?page=N`, previous / next |
| Sidebar page tree (space + page view) | `max_tree_pages` (500) pages loaded with one query | a notice links to search when more pages exist |
| Search results | 25 hits | — |

Each list is one query (no N+1): spaces through `WikiSpaceRepositoryInterface::findAccessible()`, the tree through `findActiveBySpace($space, $max + 1)`, history through `findByPage($page, $limit, $offset)`. Exports (`/export`, `wiki:export`) are synchronous, write to a temp directory and stream the ZIP; they are bounded by PHP `max_execution_time` — use the `wiki:export` console command for very large spaces.

## Twig overrides (REQ-TWIG-001)

The bundle registers **`@NowoWikiBundle/`**. Application files under **`templates/bundles/NowoWikiBundle/`** always win over vendor: `TwigPathsPass` prepends that directory (when it exists) and appends the bundle views after it. Registration is unconditional (the manage UI is always available; see [CONFIGURATION.md](CONFIGURATION.md)). Controllers render logical names taken from `nowo_wiki.templates.*`, so you can also point a page at another template from configuration.

**Freeze rule:** an application file at an override path always wins and will **not** pick up vendor changes for that path until you remove or merge it. Prefer `web_ui.layout_template` / `web_ui.css_framework` / one partial over copying manage pages, so `composer update` keeps shipping UI fixes.

**Procedure:**

1. Pick the `<subpath>` from the table below.
2. Create `templates/bundles/NowoWikiBundle/<subpath>` in your application (same relative path and filename).
3. Clear the cache in dev if needed: `php bin/console cache:clear`.
4. Treat the file as a fork: it no longer follows bundle upgrades for that `<subpath>`.

Example: `templates/bundles/NowoWikiBundle/manage/_page_tree.html.twig` replaces the sidebar tree only.

| Prefer | Notes |
|--------|--------|
| `web_ui.layout_template` | Host chrome; pages keep package assets via `base.html.twig` |
| `web_ui.css_framework` | Stack hint (`tabler` default); demo CDN gated for Tabler/Bootstrap |
| Override `base.html.twig` / one manage page | Surgical freeze when config is not enough |

```yaml
nowo_wiki:
    web_ui:
        css_framework: bootstrap5
        layout_template: 'layouts/app.html.twig'
```

### Overridable templates

Paths are relative to `src/Resources/views` (`@NowoWikiBundle/<subpath>`):

| `<subpath>` | Purpose |
| ----------- | ------- |
| `layout.html.twig` | Outer HTML shell of the manage UI (replaced by `web_ui.layout_template` when set) |
| `base.html.twig` | Page frame: flashes, package CSS/JS, `title` / `body` / `javascripts` blocks |
| `manage/index.html.twig` | Wiki index: search highlight, AI entry point, paginated space cards |
| `manage/space.html.twig` | Space page: page tree, in-space search, import / export card |
| `manage/page_view.html.twig` | Page view: sanitized body (`nowo_wiki_safe_html`), tree, edit / history actions |
| `manage/page_edit.html.twig` | Create / edit form (`WikiPageFormType`, Tiptap editor) |
| `manage/page_history.html.twig` | Paginated revision list with compare links |
| `manage/page_diff.html.twig` | Side-by-side diff of two revisions |
| `manage/search.html.twig` | Search form and results (per space or across spaces) |
| `manage/ai_ask.html.twig` | Full-page AI question / answer with sources |
| `manage/_page_header.html.twig` | Page title, subtitle, dashboard / wiki links, search and AI buttons |
| `manage/_page_tree.html.twig` | Recursive sidebar tree plus the "tree truncated" notice |
| `manage/_pagination.html.twig` | Previous / next pager used by the index and the history |
| `manage/_flashes.html.twig` | Flash messages |
| `manage/_ai_ask_modal.html.twig` | AI modal opened from the index |
| `manage/_ai_ask_form_fields.html.twig` | AI form fields shared by the modal and the AI page |

Renaming or removing one of these `<subpath>` values (or a stable `{% block %}`) is a breaking change and is announced in [UPGRADING.md](UPGRADING.md).

### Recovering upstream after a freeze

Delete (or rename aside) the application override, clear the Twig cache, then re-apply only the surgical changes you still need. Diff your copy against the new vendor file under `vendor/nowo-tech/wiki-bundle/src/Resources/views/<subpath>` before re-copying.

### Forms in templates (REQ-TWIG-005)

Every form in the manage views is a PHP Symfony Form Type (Form Kit `wiki` profile) built by `WikiManageController` and rendered with `form_start` / the child loop / `form_end`:

| View | Variable | Form Type |
| ---- | -------- | --------- |
| `manage/page_edit.html.twig` | `form` | `WikiPageFormType` — its own CSRF field (token id `wiki_edit_<page id>` / `wiki_new_<space id>`) replaces the former top-level `_token` |
| `manage/index.html.twig`, `manage/space.html.twig`, `manage/search.html.twig` | `search_form` | `WikiSearchType` (GET, flat `space` / `q`) |
| `manage/space.html.twig` | `import_form` | `WikiImportType` (multipart, flat `format` / `archive` / `update_existing` / `_token`) |
| `manage/ai_ask.html.twig`, `manage/_ai_ask_modal.html.twig`, `manage/_ai_ask_form_fields.html.twig` | `ai_form` | `WikiAiAskType` (flat `space` / `q` / `_token`, token id `wiki_ai_ask`) |

Render custom children first, then the canonical loop so remaining children (CSRF, hidden fields) still appear:

```twig
{{ form_start(search_form, {attr: {class: 'row g-2 align-items-end'}}) }}
    {{ form_label(search_form.q) }}
    {{ form_widget(search_form.q) }}
    {% for child in search_form %}
        {% if not child.rendered %}
            {{ form_row(child) }}
        {% endif %}
    {% endfor %}
    <button type="submit" class="btn btn-primary">…</button>
{{ form_end(search_form) }}
```

Template overrides and demos **must not** reintroduce raw `<form>`, `<input>`, `<textarea>` or `<select>` markup; `make check-no-raw-html-form` (part of `release-check`) fails when they do.

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

- `nowo_wiki.page.saved` — `WikiPageSavedEvent`
- `nowo_wiki.page.archived` — `WikiPageArchivedEvent`
