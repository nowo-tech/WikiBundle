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
| `editor.tiptap_config` | `notion` | TiptapEditorBundle config profile name |
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

- [Space access and tenant paths (1.9)](#space-access-and-tenant-paths-19)
- [Attachments (1.9)](#attachments-19)
- [Web UI (REQ-UI-001 / REQ-UI-003)](#web-ui-req-ui-001--req-ui-003)
- [Roles per action (REQ-UI-003)](#roles-per-action-req-ui-003)
- [Pagination (REQ-PERF-001)](#pagination-req-perf-001)
- [Import / export](#import--export)
- [Symfony AI integration](#symfony-ai-integration)
- [Logging (REQ-OBS-001)](#logging-req-obs-001)

## Space access and tenant paths (1.9)

```yaml
nowo_wiki:
    # Copies of every space-scoped route; the first tenant whose slug_requirement matches a space slug owns its URLs.
    tenant_paths:
        - { path: '/projects/{spaceSlug}/wiki', name_prefix: 'app_project_', slug_requirement: '[0-9a-f-]{36}' }
        - { path: '/wiki/{spaceSlug}', name_prefix: 'app_', slug_requirement: 'general|user-[0-9a-f-]{36}' }
    tenant_only: true   # leave out /tools/wiki/spaces/{spaceSlug}/… (index, search and ask stay)
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
