# Configuration — WikiBundle

Root key: `nowo_wiki`.

| Key | Default | Description |
|-----|---------|-------------|
| `user_class` | *(required)* | Application user entity FQCN |
| `table_prefix` | `wiki_` | DB table prefix |
| `space_scope` | `team` | `team` or `user` |
| `team_membership_resolver` | null | Service id implementing `WikiTeamMembershipResolverInterface` |
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
| `security.ai_roles` | `ROLE_USER` | Roles allowed to use `/tools/wiki/ask` |
| `security.import_roles` | `ROLE_ADMIN` | Roles allowed to import into a space |
| `security.export_roles` | `ROLE_USER` | Roles allowed to export a space |
| `import_export.enabled` | `true` | Enable import/export UI and console commands |
| `import_export.max_upload_bytes` | `52428800` | Max upload size for space import (bytes) |
| `web_ui.css_framework` | `tabler` | REQ-UI-001 CSS stack (`bootstrap5`, `tabler`, `tailwind`, `custom`, …) |
| `web_ui.layout_template` | null | Optional host layout Twig path |


## Web UI (REQ-UI-001)

```yaml
nowo_wiki:
    web_ui:
        enabled: true
        css_framework: tabler   # or bootstrap5, tailwind, custom, …
        # layout_template: 'layouts/app.html.twig'  # optional host layout
```

Semantic hooks: `nowo-ui-body`, `nowo-ui-page`, `nowo-ui-container`, `data-nowo-ui-framework`. Manage pages extend `@NowoWikiBundle/base.html.twig` (stacks package CSS/JS with `parent()`). Prefer `web_ui.layout_template` over copying every page; override under `templates/bundles/NowoWikiBundle/` only when needed (see [USAGE](USAGE.md#twig-overrides-req-twig-001)).

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

The bundle registers `Nowo\WikiBundle\Ai\Tool\WikiKnowledgeSearchTool` for agent toolboxes. Add it under `agent.wiki_assistant.tools` or rely on `context_injection` for one-shot answers.

See `demo/symfony8/config/packages/ai.yaml` for a working example.

See `src/DependencyInjection/Configuration.php` for the full tree.
