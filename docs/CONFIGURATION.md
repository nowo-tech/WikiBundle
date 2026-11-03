# Configuration — WikiBundle

Root key: `nowo_wiki`.

| Key | Default | Description |
|-----|---------|-------------|
| `user_class` | *(required)* | Application user entity FQCN |
| `table_prefix` | `wiki_` | DB table prefix |
| `space_scope` | `team` | `team` or `user` |
| `team_membership_resolver` | null | Service id implementing `WikiTeamMembershipResolverInterface` |
| `database.entity_manager` | `default` | Doctrine entity manager used by the wiki repositories and search |
| `database.driver` | `doctrine_orm` | **Deprecated since 1.6, no effect** (only Doctrine ORM is supported); remove it from your config |
| `database.platform` | `mysql` | **Deprecated since 1.6, no effect** (the platform comes from the Doctrine connection); remove it from your config |
| `firewall` | `main` | **Deprecated since 1.6, no effect** (the bundle trusts any secured firewall that handles the wiki routes); remove it from your config |
| `sanitizer.extra_allowed_elements` | `{}` | 1.11: extra elements kept in page bodies, element => its extra attributes (e.g. `{details: [open], summary: []}`); see [Sanitizer allowlist](#sanitizer-allowlist-111) |
| `sanitizer.extra_allowed_attributes` | `[]` | 1.11: attributes kept on every allowed element (e.g. `[data-callout]`) |
| `editor.type` | `tiptap` | `tiptap`: HTML pages edited with TiptapEditorType; `markdown` (1.11): Markdown pages (`content_markdown` is the source, `content_html` its sanitized render cache), see [Markdown pages](#markdown-pages-111); `milkdown` (1.13): Markdown pages in the nowo-tech/milkdown-editor-bundle dialect, see [Milkdown editor](#milkdown-editor-113) |
| `editor.tiptap_config` | `notion` | TiptapEditorBundle config profile name |
| `editor.markdown_form_type` | null | 1.11: form type FQCN of the page body when `editor.type` is `markdown` (e.g. a Markdown editor bundle); null = a plain textarea (`MilkdownEditorType` with `milkdown`) |
| `editor.milkdown.page_resolver` | null | 1.13: service id of the Milkdown `PageResolverInterface` that resolves `[[wiki:<uuid>]]` in wiki pages for the viewer; null = `WikiMilkdownPageResolver` (pages the viewer may read, `page_view` URLs) |
| `security.access_checker` | null | Custom `WikiAccessCheckerInterface` service id |
| `security.access_roles` | `[ROLE_USER]` | General gate of the wiki (REQ-UI-002); an empty list grants nothing through it |
| `security.roles.<action>` | see [Roles per action](#roles-per-action-req-ui-003) | Roles per action (`admin`, `list`, `create`, `edit`, `history`, `archive`, `ai`, `import`, `export`); `null` = the deprecated `<action>_roles` key, else the default |
| `security.<action>_roles` | null | **Deprecated since 1.10**: use `security.roles.<action>` |
| `security.allow_unauthenticated` | `false` | DEV/DEMO only — wires AllowAll checker; requires SecurityBundle when `false` |
| `routes.*` | see defaults | Path and route name per action |
| `route_prefix` | null | **Deprecated since 1.10**: use `web_ui.path_prefix` |
| `templates.*` | null | **Deprecated since 1.10**: use `web_ui.layout_template` and `web_ui.templates.<page>` |
| `ai.enabled` | `false` | Enable Symfony AI assistant (`composer require symfony/ai-bundle`) |
| `ai.agent` | `wiki_assistant` | Agent name from `config/packages/ai.yaml` (service `ai.agent.{name}`) |
| `ai.context_injection` | `true` | Inject wiki search hits into the system prompt |
| `ai.max_context_pages` | `8` | Max pages in injected context |
| `ai.max_context_chars` | `12000` | Max characters of wiki context |
| `ai.system_prompt` | null | Optional override of the default system prompt |
| `ai.http_timeout` | `30` | Timeout and max duration (seconds, 1–600) of `nowo_wiki.ai.http_client`; set it as `http_client` of your AI platform |
| `import_export.enabled` | `true` | Enable import/export UI and console commands |
| `import_export.max_upload_bytes` | `52428800` | Max upload size for space import (bytes) |
| `web_ui.enabled` | `true` | `false`: the `nowo_wiki` route loader registers no route |
| `web_ui.path_prefix` | null | Prefix of every wiki route path (e.g. `/admin`); null = the deprecated `route_prefix`, else none |
| `web_ui.layout_template` | null | Layout the pages extend; null = `nowo_ui_kit.layouts.admin` (see [TEMPLATES](TEMPLATES.md#layout)) |
| `web_ui.templates.<page>` | null | Per-page template override (`index`, `space`, `page_view`, `page_edit`, `page_history`, `page_diff`, `search`, `ai_ask`); null = the bundle page |
| `web_ui.css_framework` | null | **Deprecated since 1.10**: set `nowo_ui_kit.css_framework` once for every bundle (when set, it only seeds `nowo_ui_kit.css_framework` / `icon_set` if the host left them unset) |
| `pagination.spaces_per_page` | `24` | Space cards per wiki index page (`?page=N`, 1–500) |
| `pagination.revisions_per_page` | `25` | Revisions per page history page (`?page=N`, 1–500) |
| `pagination.max_tree_pages` | `500` | Pages loaded into the sidebar tree (10–10000); a notice links to search when more exist |

## Table of contents

- [Markdown pages (1.11)](#markdown-pages-111)
- [Milkdown editor (1.13)](#milkdown-editor-113)
- [Page tree (1.14)](#page-tree-114)
- [Trash (1.14)](#trash-114)
- [History timeline (1.15)](#history-timeline-115)
- [Sanitizer allowlist (1.11)](#sanitizer-allowlist-111)
- [Space access and tenant paths (1.9)](#space-access-and-tenant-paths-19)
- [Attachments (1.9)](#attachments-19)
- [Web UI (REQ-UI-001 / REQ-UI-003)](#web-ui-req-ui-001--req-ui-003)
- [Roles per action (REQ-UI-003)](#roles-per-action-req-ui-003)
- [Pagination (REQ-PERF-001)](#pagination-req-perf-001)
- [Import / export](#import--export)
- [Symfony AI integration](#symfony-ai-integration)
- [Logging (REQ-OBS-001)](#logging-req-obs-001)

## Markdown pages (1.11)

```yaml
nowo_wiki:
    editor:
        type: markdown                       # default: tiptap
        markdown_form_type: null             # e.g. App\Form\MarkdownEditorType; null = textarea
```

- Revisions store `format` (`html` | `markdown`); with `markdown` the Markdown (`content_markdown`) is the source and `content_html` the render cache (sanitized). HTML revisions stay readable and are converted when edited, or in bulk with `bin/console wiki:convert-markdown` ([USAGE](USAGE.md#markdown-pages-111)).
- The renderer is `Nowo\WikiBundle\Markdown\WikiMarkdownRendererInterface` (default `WikiMarkdownRenderer`: CommonMark + GFM, raw HTML escaped, unsafe links dropped). Add syntax with services implementing `WikiMarkdownExtensionInterface` (autoconfigured, tag `nowo_wiki.markdown_extension`), or replace the alias.
- A host `markdown_form_type` receives the editor options (`upload_url`, `upload_csrf_token` when attachments are enabled, plus whatever `WikiEvents::EDITOR_OPTIONS` listeners add): it must accept them, or a listener removes them.
- Schema: see [UPGRADING](UPGRADING.md#to-1110) (two columns on `<prefix>_page_revisions`).

## Milkdown editor (1.13)

```bash
composer require nowo-tech/milkdown-editor-bundle   # ^1.2, register NowoMilkdownEditorBundle
```

```yaml
nowo_wiki:
    editor:
        type: milkdown                       # default: tiptap
        markdown_form_type: null             # null = Nowo\MilkdownEditorBundle\Form\MilkdownEditorType
        milkdown:
            page_resolver: null              # null = WikiMilkdownPageResolver

nowo_milkdown_editor:
    markdown:                                # the limits and safety settings wiki pages are rendered with
        max_input_length: 100000
        max_inline_markers: 10000
        max_references: 500
        icon_sprite_url: '/build/tabler.svg'
        icons: [home, bell, alert-triangle]
```

- Everything of `markdown` applies (Markdown source, `content_html` render cache, `wiki:convert-markdown`), plus the **Milkdown ↔ wiki bridge**: `WikiMarkdownRendererInterface` is `MilkdownWikiMarkdownRenderer` (CommonMark + GFM + the Milkdown dialect + your `nowo_wiki.markdown_extension` services) with the `nowo_milkdown_editor.markdown` options: `html_input` (`escape` / `strip`), `allow_remote_images`, `math`, `max_nesting_level`, `max_input_length`, `max_inline_markers`, `max_references`, `mark_colors`. Unsafe links are always dropped. See [USAGE](USAGE.md#milkdown-editor-113).
- The sanitizer keeps the dialect markup on top of your `sanitizer` lists: `details` (`open`) / `summary`, and `role`, `aria-label`, `data-math`, `data-directive`, `data-icon` on every allowed element (`WikiExtension::MILKDOWN_SANITIZER_ELEMENTS` / `MILKDOWN_SANITIZER_ATTRIBUTES`). SVG is never allowed: icons are drawn after sanitizing from the icon allowlist.
- `nowo_milkdown_editor.markdown.icons` / `icon_sprite_url` decide which `:tabler-<name>:` icons are drawn; mentions are resolved by the Milkdown `UserResolverInterface` alias (yours) and host leaf directives by `DirectiveRendererInterface` services.
- The container refuses to compile when the package is missing or `NowoMilkdownEditorBundle` is not registered.

### Collaboration (1.13)

| Key | Default | Meaning |
|-----|---------|---------|
| `collaboration.enabled` | `false` | Co-editing of Milkdown pages over Mercure (needs `editor.type: milkdown`, symfony/mercure-bundle unless `publisher` and `subscriber_authorizer` are yours) |
| `collaboration.hub_url` | `/.well-known/mercure` | Hub URL the browser subscribes to (same registrable domain as the app, for the cookie) |
| `collaboration.topic_prefix` | `urn:nowo-wiki:page:` | Topic of a page = prefix + page id |
| `collaboration.max_update_bytes` | 262144 | Largest decoded update / presence message (413 above) |
| `collaboration.max_snapshot_bytes` | 4194304 | Largest decoded compaction snapshot |
| `collaboration.max_log_bytes` | 16777216 | Byte budget of a page's stored log (snapshot + updates); over it the log is dropped and editors reload (409 `log_reset`) |
| `collaboration.compact_after` | 200 | Ask clients to compact once the log holds this many updates |
| `collaboration.max_log_entries` | 2000 | Refuse updates (429 `log_full`) above this until a client compacts |
| `collaboration.seed_timeout` | 30 | Seconds after which an unfulfilled seed claim may be taken again |
| `collaboration.corrupt_resets` / `corrupt_window` | 3 / 600 | Resets clients may trigger by reporting a log they cannot apply, per page and window (seconds) |
| `collaboration.reset_after_idle` | 300 | Idle seconds after which a log not matching the current revision is dropped |
| `collaboration.rate_limiter` | null | **Required** with `enabled`: service id of a `RateLimiterFactory` per user and page, applied to every request (token, state, update, awareness, compact) |
| `collaboration.update_rate_limiter` / `awareness_rate_limiter` | null | 1.14.1: per user and page budgets for `update` and `awareness` requests instead of `rate_limiter` (e.g. `limiter.wiki_collab_update`, `limiter.wiki_collab_awareness`) |
| `collaboration.user_rate_limiter` | null | 1.13.1 (CW-I9): service id of a `RateLimiterFactory` per **user** across all pages (optional, on top of `rate_limiter`) |
| `collaboration.show_names` | `false` | 1.13.1 (CW-I2): co-editors see display names instead of "Editor xxxx" (never an identifier or e-mail-shaped value) |
| `collaboration.store` / `publisher` / `subscriber_authorizer` | null | Your services instead of the Doctrine store and the Mercure defaults |
| `collaboration.mercure_hub` | null | Mercure hub name of the defaults |
| `routes.page_collab` | `…/pages/{pageSlug}/collab/{action}` | The collaboration endpoints (`action`: token, state, update, awareness, compact, corrupt, reset) |

Schema: `<prefix>_page_collab_documents` and `<prefix>_page_collab_updates`, part of the generated schema only while `collaboration.enabled` is true (1.13.1; see [UPGRADING](UPGRADING.md#to-1131)).

## Page tree (1.14)

```yaml
nowo_wiki:
    tree:
        enabled: true          # literal boolean; off = the 1.13 nested list, no tree routes
        max_depth: 10          # deepest level after a move (root pages are level 1)
        state_store: null      # service id of a WikiTreeStateStoreInterface (null: the session)
```

| Key | Default | Meaning |
|-----|---------|---------|
| `tree.enabled` | `false` | UiKit tree in the sidebar with fold state and moves (drag, Alt+arrows, Move form) |
| `tree.max_depth` | 10 | A move that would put a page deeper is refused (`too_deep`) |
| `tree.state_store` | null | Where the expanded pages of each user and space are kept (default: the session, 500 ids per space) |
| `routes.page_move` | `…/pages/{pageSlug}/move` | The Move form (GET, POST + CSRF) |
| `routes.space_tree` | `…/spaces/{spaceSlug}/tree/{action}` | JSON endpoints `move` and `state` (POST, same origin, CSRF per space) |
| `web_ui.templates.page_move` | null | Template of the Move form |

Who may move: the role gate `canEdit()` and `WikiAction::Edit` on the page, on its current parent and on the new parent
(the space for the top level); a page or parent the user may not read **with every ancestor** answers 404 (as the
sidebar hides a whole hidden branch). Positions count the siblings the user can see. A page moves with its whole
sub-tree, including descendants the actor cannot see: their readers become those of the new branch (as in Outline and
Notion). The tree is only movable for users with
`Edit` on the space.

Decisions (2026-11-03, see [ROADMAP › Decided](ROADMAP.md#decided)): pages move within their space only (D-W1); the
depth limit defaults to 10 (D-W2); sibling order uses sparse integers in the existing `position` column (D-W27, no
schema change); the fold state lives in the session unless `tree.state_store` names a per-user store (D-W28).

## Trash (1.14)

```yaml
nowo_wiki:
    trash:
        enabled: true          # literal boolean; off = no Delete action, no trash routes, no trash table
        retention_days: 30     # wiki:trash:purge deletes older deletions for good (D-W4)
```

| Key | Default | Meaning |
|-----|---------|---------|
| `trash.enabled` | `false` | *Delete* moves a page and its sub-pages to the trash (restore together, purge for good) |
| `trash.retention_days` | 30 | Age at which `wiki:trash:purge` removes a deletion |
| `routes.page_delete` | `…/pages/{pageSlug}/delete` | *Delete* (POST + CSRF, behind a confirm) |
| `routes.space_trash` | `…/spaces/{spaceSlug}/trash` | The trash list |
| `routes.trash_restore` | `…/trash/{batchId}/restore` | *Restore* (POST + CSRF) |
| `routes.trash_purge` | `…/trash/{batchId}/purge` | GET: the confirmation page; POST: purge (CSRF, behind a second confirm) |
| `web_ui.templates.trash` / `trash_purge` | null | Templates of the two pages |

Who may: the role gate `canArchive()`, then `WikiAction::Delete` for *Delete*, the trash list and *Restore*, and
`WikiAction::Purge` for *Delete for good* (the 1.x mapping: archive roles delete, import / manage roles purge, D-W5).
A purge is irreversible: when the deletion holds pages the user may not read, it also needs `WikiAction::Manage` on the
space (otherwise a neutral notice says so). The trash list counts only the pages the user may read.
Only deletions whose page the user may read with every ancestor are listed; on the purge page, pages the user may not
read are counted, not named. Schedule the retention, e.g. with cron: `0 3 * * * bin/console wiki:trash:purge`.

Decisions (2026-11-03, see [ROADMAP › Decided](ROADMAP.md#decided)): a delete takes the page and its whole sub-tree,
and a restore brings them back together (D-W29); pages archived before the delete stay archived (D-W30); a restored
page whose parent is gone meanwhile comes back at the top level (D-W31).

## History timeline (1.15)

```yaml
nowo_wiki:
    history:
        timeline: true         # literal boolean; off = the 1.14 revision list, no named-version routes or table
        session_minutes: 30    # saves by the same author less than this apart form one session
        show_names: false      # true = display names that say nothing about the login, else "Editor xxxx"
        retention_days: 30     # wiki:history:thin thins older revisions to one per session and day (D-W6)
```

| Key | Default | Meaning |
|-----|---------|---------|
| `history.timeline` | `false` | Version name, author and session columns; *Save version*; *Copy to new page* |
| `history.session_minutes` | 30 | Gap (1–1440 minutes) that ends a session of saves by one author |
| `history.show_names` | `false` | Show safe display names (`getDisplayName()` / `getName()`, the co-editing rule) instead of opaque labels |
| `history.retention_days` | 30 | Age at which `wiki:history:thin` thins revisions |
| `routes.page_version_save` | `…/pages/{pageSlug}/versions/new` | *Save version* (GET form, POST + CSRF) |
| `routes.page_version_copy` | `…/pages/{pageSlug}/revisions/{revisionNumber}/copy` | *Copy to new page* (POST + CSRF) |
| `web_ui.templates.page_version` | null | Template of the *Save version* form |

Who may: *Save version* needs the role gate `canEdit()` and `WikiAction::Edit` on the page (D-W7: every editor);
*Copy to new page* needs `WikiAction::History` on the page, `canCreate()` and `WikiAction::Create` on the space. Both
answer 404 for a page the user may not read with every ancestor.

Authors are labelled per page with an HMAC of `kernel.secret` (the co-editing labels): the same person has the same
label on one page and unrelated labels on other pages, and a label cannot be matched against a user directory. With
`show_names`, a display name that contains the login, its local part or an e-mail address falls back to the label.

**Retention.** `wiki:history:thin` (add `--dry-run` to count only) removes, for every page, the revisions older than
`retention_days` except the newest of each session and day. A session ends when the author changes, after
`session_minutes` without a save, at midnight (the stored time) and at a named version (a session of its own). Named
versions, the current revision and the newest revision are always kept. Revision numbers are never reused, so a thinned
history has gaps. Run it from a single scheduler, e.g. `30 3 * * * bin/console wiki:history:thin`. Without the
timeline the command does nothing.

## Sanitizer allowlist (1.11)

Page bodies (HTML revisions and the render cache of Markdown revisions) go through `WikiHtmlSanitizer`. An editor dialect that emits more markup declares it here:

```yaml
nowo_wiki:
    sanitizer:
        extra_allowed_elements:
            details: [open]
            summary: []
        extra_allowed_attributes: [data-callout, data-align]
```

- Names must be plain element / attribute names. The container refuses to compile with `script`, `style`, `iframe`, `object`, `embed`, forms and form controls, `svg`, `math`, `template`, `link`, `meta`, `base`…, with the attributes `style`, `href`, `src`, `srcset`, `srcdoc`, `action`, `formaction`… or any `on*` handler (`WikiHtmlSanitizer::FORBIDDEN_ELEMENTS` / `FORBIDDEN_ATTRIBUTES`). `class` is already allowed on every element.
- A blocked-by-default element (e.g. `section`) listed here is kept.

## Space access and tenant paths (1.9)

```yaml
nowo_wiki:
    # Copies of every space-scoped route; the first tenant whose slug_requirement matches a space slug owns its URLs.
    tenant_paths:
        - { path: '/projects/{spaceSlug}/wiki', name_prefix: 'app_project_', slug_requirement: '[0-9a-f-]{36}' }
        - { path: '/wiki/{spaceSlug}', name_prefix: 'app_', slug_requirement: 'general|user-[0-9a-f-]{36}' }
    tenant_only: true   # leave out /tools/wiki/spaces/{spaceSlug}/… (index, search and ask stay; 1.12: the search keeps its space, no "All spaces" picker)
```

```yaml
# config/services.yaml — decide per space / page and create spaces on the first visit
services:
    Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface: '@App\Wiki\Security\MyWikiAccessChecker'
    Nowo\WikiBundle\Service\WikiSpaceLocatorInterface: '@App\Wiki\Tenancy\MySpaceLocator'
```

## Attachments (1.9)

```yaml
nowo_wiki:
    attachments:
        enabled: true
        storage_dir: '%kernel.project_dir%/var/wiki-attachments'   # private; never under public/
        # storage: App\Storage\MyWikiStorage          # a WikiAttachmentStorageInterface service
        # scanner: App\Security\MyAntivirusScanner     # a WikiAttachmentScannerInterface service
        max_file_bytes: 10485760
        max_space_bytes: 524288000
        # allowed_types: { png: [image/png], pdf: [application/pdf], … }   # extension => sniffed MIME types
```

Limits per space can come from host settings: alias `WikiAttachmentLimitsProviderInterface` to your service.

## Web UI (REQ-UI-001 / REQ-UI-003)

Since 1.10 every wiki page extends a [UiKitBundle](https://github.com/nowo-tech/UiKitBundle) page scaffold (list, show, form): the host sets its layout and theme **once** in UiKit and every bundle follows. Page → scaffold, view models and data blocks: [TEMPLATES.md](TEMPLATES.md).

```yaml
# config/packages/nowo_ui_kit.yaml — once for every UiKit bundle
nowo_ui_kit:
    css_framework: bootstrap5        # or tailwind | foundation | custom | none
    icon_set: bootstrap-icons
    layouts:
        admin: 'layouts/admin.html.twig'   # the project layout (or a bridge) rendering the UiKit canonical blocks
```

```yaml
# config/packages/nowo_wiki.yaml — all optional
nowo_wiki:
    web_ui:
        enabled: true
        path_prefix: null                  # e.g. /admin
        layout_template: null              # wiki-only layout override; null = nowo_ui_kit.layouts.admin
        templates:
            page_view: 'wiki/page_view.html.twig'   # thin override: {% extends '@NowoWikiBundle/manage/page_view.html.twig' %} + one block
```

- **Layout:** a host layout that renders the canonical blocks (`nowo_ui_document_title`, `nowo_ui_styles`, `nowo_ui_page_header`, `nowo_ui_flashes`, `nowo_ui_content`, `nowo_ui_modals`, `nowo_ui_scripts`) is used as is — the reference is a one-file bridge that `{% use '@NowoUiKit/layout/_bridge.html.twig' %}` (see `demo/symfony8/templates/layouts/admin.html.twig`). A pre-1.10 layout that only renders `{% block body %}` keeps working through a **deprecated** bridge (removed in 2.0).
- **CSS stack:** the pages emit `nowo-ui-*` classes plus the theme slots of `nowo_ui_kit.theme` (Bootstrap 5, Tailwind, Foundation, `custom` / `none` = semantic classes only); the bundle loads no framework and no CDN. Switch the stack with `nowo_ui_kit.css_framework`, re-skin with `nowo_ui_kit.theme` (classes, attributes, partials, `--nowo-ui-*` tokens).
- **Assets:** each page stacks `{{ parent() }}` + `wiki.css` (asset package `nowo_wiki`) in `nowo_ui_styles`; the page editor adds the TiptapEditorBundle script in `nowo_ui_scripts` (with the request attribute `csp_nonce` when set). `wiki.css` only lays out the wiki-specific regions (tree, two columns, page body, diff) with the `--nowo-ui-*` tokens.
- **Form fields:** the form types carry no framework class; the host form theme (`twig.form_themes`, e.g. `bootstrap_5_layout.html.twig`) or the FormKit `wiki` profile styles them.
- **Dialogs:** the AI question of the index is a UiKit `<dialog>` (`data-nowo-ui-dialog-open`); Archive and Restore go through the UiKit confirm `<dialog>` (POST + CSRF). Load the UiKit runtime scripts in the host layout (`{{ include(nowo_ui_partial('scripts')) }}`, done by the UiKit layouts and the bridge).
- **Flashes:** messages are translated in the `NowoWikiBundle` domain when they are added, so the layout's `nowo_ui_flashes` prints them as given.
- **CSRF (fail closed):** with `web_ui.enabled: true` the container refuses to compile without `framework.csrf_protection` / `framework.form.csrf_protection` (`symfony/security-csrf`).

## Roles per action (REQ-UI-003)

```yaml
nowo_wiki:
    security:
        access_roles: [ROLE_USER]      # general gate
        roles:
            admin: [ROLE_ADMIN]        # every action
            list: [ROLE_USER]          # index, space, page view, search
            create: [ROLE_USER]
            edit: [ROLE_USER]          # also attachment upload
            history: [ROLE_USER]       # history and diff (restore needs edit + history)
            archive: [ROLE_USER]       # archive and unarchive
            ai: [ROLE_USER]
            import: [ROLE_ADMIN]
            export: [ROLE_USER]
```

An empty list grants that action to nobody but the `admin` roles. When `access_roles` and every list are empty (and there is no `access_checker` and `allow_unauthenticated` is false), the container refuses to compile. The 1.x keys `security.admin_roles`, `list_roles`, `create_roles`, `edit_roles`, `history_roles`, `archive_roles`, `ai_roles`, `import_roles`, `export_roles` still work (deprecated since 1.10, removed in 2.0); `security.roles.<action>` wins when both are set. Space- and page-level decisions stay with `WikiSpaceAccessCheckerInterface` (1.9).

## Pagination (REQ-PERF-001)

```yaml
nowo_wiki:
    pagination:
        spaces_per_page: 24
        revisions_per_page: 25
        max_tree_pages: 500
```

Details per page in [USAGE.md](USAGE.md#pagination-and-list-limits-req-perf-001).

## Import / export

Supports Outline and Notion Markdown directory trees and ZIP archives. Disable entirely with `import_export.enabled: false` if your app does not need interchange.

See [USAGE.md](USAGE.md) for routes and console commands.

## Symfony AI integration

1. `composer require symfony/ai-bundle`
2. Configure `config/packages/ai.yaml` with a platform (OpenAI, Ollama, etc.) and agent `wiki_assistant`
3. Enable in `nowo_wiki`:

```yaml
nowo_wiki:
    ai:
        enabled: true
        agent: wiki_assistant
```

To bound LLM calls (important with FrankenPHP workers), set `http_client: 'nowo_wiki.ai.http_client'` on the platform in `config/packages/ai.yaml`; its timeout comes from `ai.http_timeout`.

The bundle registers `Nowo\WikiBundle\Ai\Tool\WikiKnowledgeSearchTool` for agent toolboxes. Add it under `agent.wiki_assistant.tools` or rely on `context_injection` for one-shot answers.

See `demo/symfony8/config/packages/ai.yaml` for a working example.

### Timeout hierarchy (REQ-RUNTIME-001)

The only blocking third-party call is the LLM request made by the AI platform. Bound it with the bundle client and keep each outer layer strictly larger, innermost first:

| Layer | Default / demo | Role |
| ----- | -------------- | ---- |
| `nowo_wiki.ai.http_timeout` (`nowo_wiki.ai.http_client`: `timeout` + `max_duration`) | 30 s | Fires first; the transport error is logged and the page shows `wiki.ai.error` |
| PHP `max_execution_time` / `max_input_time` | 60 s in the demo | Must be greater than `http_timeout` |
| Caddy `servers.timeouts.write` (FrankenPHP) | 75 s in the demo | Must be greater than PHP |
| FrankenPHP `max_wait_time` | 30 s in the demo | Caps the wait for a free PHP thread (504) so slow LLM calls do not queue forever |

When you raise `http_timeout`, raise the PHP and HTTP write deadlines in the same change. Without `http_client: 'nowo_wiki.ai.http_client'` on the platform, the call only has the timeouts of `framework.http_client` (Symfony default: none beyond `default_socket_timeout`). Imports and exports work on local files and are bounded by the upload cap (`import_export.max_upload_bytes`), the ZIP entry/size limits of `WikiArchiveHelper` and PHP `max_execution_time`. The demo stack is documented in [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md#timeouts-req-runtime-001).

## Logging (REQ-OBS-001)

The bundle logs through PSR-3 `Psr\Log\LoggerInterface` (`@?logger`, tagged `monolog.logger` channel **`nowo_wiki`**; nothing is logged when no logger service exists):

| Event | Level | Context |
| ----- | ----- | ------- |
| Import started / finished / failed | info / info / error | `space`, `format`, `update_existing`, `dry_run`, counters, `exception_class` |
| Export started / finished / failed | info / info / error | `space`, `format`, `pages_exported`, `exception_class` |
| Page archived | info | `space`, `page` (slugs) |
| AI agent call started / succeeded / failed / non-text result | info / info / error / error | `space`, `question_length`, `context_pages`, `answer_length`, `exception_class`, `result_type` |

Never logged: page content, question or answer text, user identifiers, API keys or exception messages (which may contain provider URLs). Route the channel with Monolog, e.g. `monolog.handlers.wiki: { type: stream, path: '%kernel.logs_dir%/wiki.log', channels: [nowo_wiki] }`.

See `src/DependencyInjection/Configuration.php` for the full tree.
