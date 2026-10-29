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
| `security.access_roles` | `[ROLE_USER]` | Open manage UI (hosts may set `ROLE_ADMIN` for admin-only) |
| `security.allow_unauthenticated` | `false` | DEV/DEMO only — wires AllowAll checker; requires SecurityBundle when `false` |
| `routes.*` | see defaults | Path and route name per action |
| `templates.*` | bundle views | Twig override targets |
| `ai.enabled` | `false` | Enable Symfony AI assistant (`composer require symfony/ai-bundle`) |
| `ai.agent` | `wiki_assistant` | Agent name from `config/packages/ai.yaml` (service `ai.agent.{name}`) |
| `ai.context_injection` | `true` | Inject wiki search hits into the system prompt |
| `ai.max_context_pages` | `8` | Max pages in injected context |
| `ai.max_context_chars` | `12000` | Max characters of wiki context |
| `ai.system_prompt` | null | Optional override of the default system prompt |
| `ai.http_timeout` | `30` | Timeout and max duration (seconds, 1–600) of `nowo_wiki.ai.http_client`; set it as `http_client` of your AI platform |
| `security.ai_roles` | `ROLE_USER` | Roles allowed to use `/tools/wiki/ask` |
| `security.import_roles` | `ROLE_ADMIN` | Roles allowed to import into a space |
| `security.export_roles` | `ROLE_USER` | Roles allowed to export a space |
| `import_export.enabled` | `true` | Enable import/export UI and console commands |
| `import_export.max_upload_bytes` | `52428800` | Max upload size for space import (bytes) |
| `web_ui.css_framework` | `tabler` | REQ-UI-001 CSS stack (`bootstrap5`, `tabler`, `tailwind`, `custom`, …) |
| `web_ui.layout_template` | null | Optional host layout Twig path |
| `pagination.spaces_per_page` | `24` | Space cards per wiki index page (`?page=N`, 1–500) |
| `pagination.revisions_per_page` | `25` | Revisions per page history page (`?page=N`, 1–500) |
| `pagination.max_tree_pages` | `500` | Pages loaded into the sidebar tree (10–10000); a notice links to search when more exist |


## Table of contents

- [Web UI (REQ-UI-001)](#web-ui-req-ui-001)
- [Pagination (REQ-PERF-001)](#pagination-req-perf-001)
- [Import / export](#import--export)
- [Symfony AI integration](#symfony-ai-integration)
  - [Timeout hierarchy (REQ-RUNTIME-001)](#timeout-hierarchy-req-runtime-001)
- [Logging (REQ-OBS-001)](#logging-req-obs-001)

## Web UI (REQ-UI-001)

```yaml
nowo_wiki:
    web_ui:
        enabled: true
        css_framework: tabler   # or bootstrap5, tailwind, custom, …
        # layout_template: 'layouts/app.html.twig'  # optional host layout
```

Semantic hooks: `nowo-ui-body`, `nowo-ui-page`, `nowo-ui-container`, `data-nowo-ui-framework`. Manage pages extend `@NowoWikiBundle/base.html.twig` (stacks package CSS/JS with `parent()`). Prefer `web_ui.layout_template` over copying every page; override under `templates/bundles/NowoWikiBundle/` only when needed (see [USAGE](USAGE.md#twig-overrides-req-twig-001)).

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
