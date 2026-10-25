# Usage — WikiBundle

## Table of contents

- [Manage UI routes (defaults)](#manage-ui-routes-defaults)
- [Import / export](#import--export)
- [Symfony AI](#symfony-ai)
- [Search](#search)
- [Application integration](#application-integration)
- [Twig overrides (REQ-TWIG-001)](#twig-overrides-req-twig-001)
  - [Forms in templates (REQ-TWIG-005)](#forms-in-templates-req-twig-005)
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

## Twig overrides (REQ-TWIG-001)

The bundle registers **`@NowoWikiBundle/`**. Application files under **`templates/bundles/NowoWikiBundle/`** win over vendor (`TwigPathsPass`).

**Freeze rule:** a full-file override hides vendor updates for that path until you delete or merge it. Prefer `web_ui.layout_template` / `web_ui.css_framework` over copying manage pages.

**Procedure:** copy only the `<subpath>` you customise into `templates/bundles/NowoWikiBundle/<subpath>`, then clear the Twig cache if needed.

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

## Events

- `nowo_wiki.page.saved` — `WikiPageSavedEvent`
- `nowo_wiki.page.archived` — `WikiPageArchivedEvent`
