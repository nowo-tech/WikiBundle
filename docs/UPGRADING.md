# Upgrading

## Table of contents

- [Unreleased](#unreleased)
- [To 1.9.0](#to-190)
- [To 1.8.1](#to-181)
- [To 1.8.0](#to-180)
- [To 1.7.2](#to-172)
- [To 1.7.1](#to-171)
- [To 1.7.0](#to-170)
  - [Forms rendered through Form Types (REQ-TWIG-005)](#forms-rendered-through-form-types-req-twig-005)
- [To 1.6.4](#to-164)
- [To 1.6.3](#to-163)
- [To 1.6.2](#to-162)
- [To 1.6.1](#to-161)
- [To 1.6.0](#to-160)
- [To 1.5.0](#to-150)
- [To 1.4.1](#to-141)
- [To 1.4.0](#to-140)
- [To 1.3.5](#to-135)
- [From 1.3.3 to 1.3.4](#from-133-to-134)
- [To 1.3.3](#to-133)
- [To 1.3.2](#to-132)
- [To 1.3.1](#to-131)
- [To 1.3.0](#to-130)
  - [Twig Extra Bundle (REQ-TWIG-004)](#twig-extra-bundle-req-twig-004)
  - [Twig-CS-Fixer (maintainers)](#twig-cs-fixer-maintainers)
- [To 1.2.0](#to-120)
  - [Behaviour](#behaviour)
- [To 1.1.1](#to-111)
- [To 1.1.0](#to-110)
- [To 1.0.5](#to-105)
- [To 1.0.4](#to-104)
- [To 1.0.3](#to-103)
- [To 1.0.2](#to-102)
- [To 1.0.1](#to-101)
- [To 1.0.0](#to-100)
  - [Requirements](#requirements)
  - [Install / upgrade steps](#install--upgrade-steps)
  - [Optional features](#optional-features)
  - [Breaking changes](#breaking-changes)
  - [FormKitBundle (admin forms)](#formkitbundle-admin-forms)

## Unreleased

From **1.9.0**:

```bash
composer update nowo-tech/wiki-bundle nowo-tech/ui-kit-bundle
```

- **Requirements:** `nowo-tech/ui-kit-bundle` **^2.17** (was `^1.4 || ^2.2`) and `symfony/security-csrf` (now a direct requirement).
- **CSRF fails closed:** with `web_ui.enabled: true` the container no longer compiles without `framework.csrf_protection` and `framework.form.csrf_protection`. Enable them (Symfony's default) or set `nowo_wiki.web_ui.enabled: false`, which now also registers no wiki route.
- **`security.access_roles` is a real gate:** every permission of the default checker now needs one of the access roles **and** one of the roles of that action (the admin roles still grant everything). With the defaults (`access_roles: [ROLE_USER]`) nothing changes for users holding `ROLE_USER`; a host with `access_roles: [ROLE_ADMIN]` and per-action lists of `ROLE_USER` now really restricts the wiki to admins, as documented. When `access_roles` and every action list are empty (no `access_checker`, `allow_unauthenticated: false`) the container refuses to compile.
- **Hidden pages stay hidden everywhere:** pages the user may not view (`WikiAction::View` on the page) are no longer listed in the sidebar tree (with their sub-pages), the archived list, search results, AI sources or the AI context (`WikiContextRetriever` and `WikiKnowledgeSearchTool` take an optional `WikiSpaceAccessCheckerInterface`, autowired).

### Configuration (deprecated keys, removed in 2.0)

| Deprecated | Use | Notes |
| --- | --- | --- |
| `route_prefix` | `web_ui.path_prefix` | `web_ui.path_prefix` wins when both are set |
| `templates.layout` | `web_ui.layout_template` | default `null` = `nowo_ui_kit.layouts.admin`; the old default `@NowoWikiBundle/layout.html.twig` is read as `null` |
| `templates.<page>` | `web_ui.templates.<page>` | pages: `index`, `space`, `page_view`, `page_edit`, `page_history`, `page_diff`, `search`, `ai_ask` |
| `web_ui.css_framework` | `nowo_ui_kit.css_framework` | default `null` now; when set it only seeds `nowo_ui_kit.css_framework` / `icon_set` if the host left them unset (1.9 seeded `tabler` by default) |
| `security.admin_roles`, `list_roles`, `create_roles`, `edit_roles`, `history_roles`, `archive_roles`, `ai_roles`, `import_roles`, `export_roles` | `security.roles.{admin,list,create,edit,history,archive,ai,import,export}` | same defaults; `security.roles.<action>` wins |

```yaml
nowo_ui_kit:
    css_framework: bootstrap5
    layouts:
        admin: 'layouts/admin.html.twig'     # your layout or a bridge that renders the UiKit canonical blocks

nowo_wiki:
    web_ui:
        path_prefix: null
        layout_template: null
        templates: {}
    security:
        access_roles: [ROLE_USER]
        roles:
            import: [ROLE_ADMIN]
```

### Template contract

Every wiki page now extends a UiKit page scaffold (REQ-UI-003); see [TEMPLATES.md](TEMPLATES.md) for the page → scaffold map, the view models and the data blocks.

- **Removed templates:** `layout.html.twig` (Tabler / Bootstrap CDN shell), `base.html.twig`, `manage/_page_header.html.twig`, `manage/_pagination.html.twig`, `manage/_flashes.html.twig`, `manage/_ai_ask_modal.html.twig`, `manage/_ai_ask_form_fields.html.twig`, `manage/_page_tree_list.html.twig`. The UiKit page header, pagination, flashes, empty state, confirm and `<dialog>` partials replace them (theme them in UiKit, once). New: `manage/_sidebar.html.twig`, `manage/_ai_dialog.html.twig`, `manage/_legacy_layout.html.twig` (deprecated bridge).
- **Removed blocks:** `title`, `body`, `container_class`, `wiki_editor_scripts`, `wiki_header`, `wiki_flashes`, `space_sidebar`, `space_sidebar_header`, `page_tree`, `page_main`, `page_header`, `page_actions`, `page_body`, `page_aside`, `space_main`, `space_search`, `space_import_export`, `space_archived`, `history_list`, `history_pagination`, `history_footer`, `diff_body`, `diff_footer`, `search_form`, `search_results`, `page_form`, `page_form_fields`, `page_form_actions`, `wiki_search_highlight`, `wiki_ai_highlight`, `wiki_ai_modal`, `space_list`, `ai_error`, `ai_answer`, `ai_sources`, `ai_follow_up`, `ai_form`. Pages fill the UiKit blocks (`nowo_ui_styles`, `nowo_ui_scripts`, `nowo_ui_list_row`, `nowo_ui_show_sections`, `nowo_ui_form_body`, `nowo_ui_form_aside`, `nowo_ui_modals`).
- **Page variables:** `page` is now the UiKit view model (`ListPage`, `ShowPage`, `FormPage`); the `WikiPage` / `WikiSpace` / `WikiPageRevision` entities and the 1.9 variables `layout`, `space`, `spaces`, `space_page`, `pagination` (array), `revision`, `revisions`, `older_revision`, `restore_forms`, `archived_pages`, `unarchive_forms`, `results`, `from_revision`, `to_revision`, `form` (page edit), `search_form` (index, search), `ai_form` (AI page) are gone. Tree nodes, archived pages, search hits, AI sources and "Used in" entries are value-free arrays (TEMPLATES.md, "View models"); the page body is `content_html`.
- **Markup:** no Tabler / Bootstrap classes in the bundle markup any more (only `nowo-ui-*` / theme slots and the `nowo-wiki-*` hooks listed in USAGE); the 1.x hooks `wiki-tree`, `wiki-page-content`, `wiki-diff-*`, `#wikiAiAskModal` and the `data-nowo-wiki-skin` attribute are gone. `wiki.css` dropped the `--nowo-wiki-*` token tree: remap the UiKit `--nowo-ui-*` tokens (`nowo_ui_kit.theme.tokens`) instead. The AI modal is a native `<dialog>` (`#nowo-wiki-ai-dialog`); Archive (new header action on the page view) and Restore ask for confirmation in the UiKit confirm `<dialog>`.
- **Form fields:** `WikiSearchType`, `WikiAiAskType` and `WikiImportType` no longer set `form-control` / `form-select` / `form-label` / `form-check-*`: style fields with your form theme (`twig.form_themes`) or the FormKit `wiki` profile. `WikiPageFormType` shows the editor help as the `content` field help.
- **Flashes** are translated (domain `NowoWikiBundle`) when they are added, so any layout prints them as given.
- **Layouts:** a 1.9 layout (`web_ui.layout_template` / `templates.layout` rendering `{% block body %}`, `stylesheets`, `javascripts`) still works through the deprecated bridge, with a deprecation notice; move it to the UiKit canonical blocks (one-file bridge: `{% use '@NowoUiKit/layout/_bridge.html.twig' %}`, see the demo `templates/layouts/admin.html.twig`).
- **Overrides** under `templates/bundles/NowoWikiBundle/` written for 1.9 must be redone: prefer theming UiKit, else `web_ui.templates.<page>` with a thin `{% extends '@NowoWikiBundle/manage/<page>.html.twig' %}` that overrides one UiKit data block.
- **Schema reminder:** 1.9 added `<prefix>_page_links` and `<prefix>_attachments`; the demo now ships the matching migration.

## To 1.9.0

From **1.8.1**:

```bash
composer update nowo-tech/wiki-bundle nowo-tech/tiptap-editor-bundle
```

- **Requirement:** `nowo-tech/tiptap-editor-bundle` **^1.6** (upload and suggestion hooks of the editor).
- **Schema:** two new tables, `<prefix>_page_links` and `<prefix>_attachments` (`bin/console doctrine:migrations:diff` then `doctrine:migrations:migrate`, or `doctrine:schema:update --force` without migrations). Nothing else changes in the existing tables.
- **New configuration keys** (all optional, defaults keep 1.8 behaviour):

  ```yaml
  nowo_wiki:
      tenant_paths: []           # e.g. [{ path: '/projects/{spaceSlug}/wiki', name_prefix: 'app_project_', slug_requirement: '[0-9a-f-]{36}' }]
      tenant_only: false         # true drops the tenant-less copies of the space-scoped routes
      attachments:
          enabled: false
          storage_dir: '%kernel.project_dir%/var/wiki-attachments'
          storage: null          # service id of a WikiAttachmentStorageInterface
          scanner: null          # service id of a WikiAttachmentScannerInterface
          max_file_bytes: 10485760
          max_space_bytes: 524288000
          allowed_types: { … }   # extension => sniffed MIME types (no SVG / HTML / XML / scripts)
      routes:
          page_unarchive:      { path: '/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/unarchive', name: nowo_wiki_page_unarchive }
          page_restore:        { path: '/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/revisions/{revisionNumber}/restore', name: nowo_wiki_page_restore }
          attachment_upload:   { path: '/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/attachments', name: nowo_wiki_attachment_upload }
          attachment_download: { path: '/tools/wiki/spaces/{spaceSlug}/attachments/{attachmentId}', name: nowo_wiki_attachment_download }
  ```

  Hosts that list routes in a firewall / `access_control` or a breadcrumb map should add the four new route names (and their tenant copies, `<name_prefix><route name>`).
- **No behaviour change by default:** without a space checker the 1.x user-level checks still apply (`LegacyWikiSpaceAccessChecker`), no tenant path is registered and attachments stay off.
- **Per-tenant access:** alias `Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface` (and, to create spaces on the first visit, `Nowo\WikiBundle\Service\WikiSpaceLocatorInterface`) to your services. A denied `view` answers 404, any other denied action 403.
- **Template overrides:** `manage/page_view.html.twig` has a `page_aside` block (*Used in*); `manage/page_history.html.twig` renders `restore_forms`; `manage/space.html.twig` renders `archived_pages` / `unarchive_forms`. New translation keys: `wiki.space.archived`, `wiki.page.unarchive`, `wiki.page.used_in`, `wiki.history.restore`, `wiki.history.restored`.
- **Custom repositories:** `WikiPageRepositoryInterface` gains `findArchivedBySpace()`.
- **Attachments:** enable with `attachments.enabled: true`, keep `storage_dir` outside `public/`, on a persistent volume and in your backups; run `WikiAttachmentService::purgeOrphans()` / `purgeStrayFiles()` from a nightly job.
- **Templates (FR-006), only if you override bundle views:**
  - `manage/_page_tree.html.twig` no longer defines the `render_tree` macro: it includes `manage/_page_tree_list.html.twig`, which includes `manage/_page_tree_item.html.twig` per page (override that partial to change one item). The `--depth` custom property is gone; indent with nested lists or the `nowo-wiki-tree__item--depth-N` classes (N capped at 6). Copies that still print `style="--depth: …"` break a strict `style-src`.
  - `base.html.twig` has no inline `<style>` block any more (`.container-wiki`, `.container-narrow` and the body background live in `wiki.css`, the background only on `body.nowo-ui-wiki`). If your copy of `base.html.twig` keeps the old block, drop it. `base.html.twig` now sets `nowo_wiki_skin` and `nowo_wiki_csp_nonce`; keep those two lines in a copy, or the pages fall back to `framework` and no nonce.
  - Each manage page wraps its body in `<div class="nowo-wiki nowo-wiki--<view>" data-nowo-wiki-skin="…">` and splits it into blocks (see the CHANGELOG list); prefer extending the page and overriding one block over copying it. Space cards and search results became `<ul>` / `<li>` (same Tabler classes), the AI modal is styled through `.nowo-wiki-ai-modal` instead of `#wikiAiAskModal` (the id stays).
  - CSS that targeted `.wiki-tree-item` padding or `.wiki-page-content` / `.wiki-diff-*` rules from the old `wiki.css`: the legacy classes stay in the markup, the bundle rules now target `nowo-wiki-tree__*`, `nowo-wiki-content` and `nowo-wiki-diff__line--*`.
- **Theming:** with `web_ui.css_framework` other than `tabler` / `bootstrap*` (`custom`, `none`, `tailwind`, `foundation`), `wiki.css` now draws the pages from `--nowo-wiki-*` tokens; set the tokens on `:root` (or a dark theme on `[data-theme="dark"]`) instead of copying templates. Tabler / Bootstrap hosts see no visual change.
- **CSP:** set the request attribute `csp_nonce` (same value as in `script-src 'nonce-…'`) before rendering and the bundle scripts carry it; nothing is printed without it.

## To 1.8.1

From **1.8.0**:

```bash
composer update nowo-tech/wiki-bundle
```

- Keep importing the routes with `type: nowo_wiki`. Do not add an `attribute` import of the bundle controllers: it would bypass the configured paths, names and `route_prefix`.

## To 1.8.0

From **1.7.2**:

```bash
composer update nowo-tech/wiki-bundle
```

No configuration change is required.

- **List limits (new defaults):** the wiki index shows 24 spaces per page, the page history 25 revisions per page and the sidebar tree at most 500 pages. Tune them under `nowo_wiki.pagination` (`spaces_per_page`, `revisions_per_page`, `max_tree_pages`).
- **Custom repositories:** if you aliased `WikiPageRepositoryInterface` or `WikiPageRevisionRepositoryInterface` to your own class, add the new optional parameters: `findActiveBySpace(WikiSpace $space, ?int $limit = null)` and `findByPage(WikiPage $page, ?int $limit = null, int $offset = 0)`.
- **Template overrides:** `manage/index.html.twig` now loops over `space_page` (current page of `spaces`) and includes `manage/_pagination.html.twig`; `manage/page_history.html.twig` uses `older_revision` for the last row and includes the pager; `manage/_page_tree.html.twig` receives `tree_truncated` / `max_tree_pages`. Overrides keep working but show every space / only the first page of history until you merge these changes.
- **Logging:** route the new `nowo_wiki` Monolog channel if you want it in a separate file (see CONFIGURATION, Logging).
- **Imports:** ZIP archives with absolute or `..` entry names are now rejected.

## To 1.7.2

From **1.7.1**:

```bash
composer update nowo-tech/wiki-bundle
```

## To 1.7.1

From **1.7.0**:

```bash
composer update nowo-tech/wiki-bundle
```

## To 1.7.0

From **1.6.4**:

```bash
composer update nowo-tech/wiki-bundle
```

### Forms rendered through Form Types (REQ-TWIG-005)

`WikiManageController` now always passes these form variables; **if you override a manage template**, render them with `form_start` / the child loop / `form_end` instead of raw markup (see [USAGE — Forms in templates](USAGE.md#forms-in-templates-req-twig-005)):

| Template | New variable | Notes |
| -------- | ------------ | ----- |
| `manage/index.html.twig` | `search_form`, `ai_form` | `ai_form` must also be passed to `_ai_ask_modal.html.twig` (it is included with `with_context = false`) |
| `manage/space.html.twig` | `search_form`, `import_form` | Same `space` / `q` and `format` / `archive` / `update_existing` / `_token` names |
| `manage/search.html.twig` | `search_form` | Same query names (bookmarks keep working) |
| `manage/ai_ask.html.twig`, `manage/_ai_ask_form_fields.html.twig` | `ai_form` | The fields partial now renders `ai_form` children; the caller wraps it in `form_start(ai_form)` / `form_end(ai_form)` |
| `manage/page_edit.html.twig` | — | Remove the hand-rolled `<input type="hidden" name="_token" value="{{ csrf_token(csrf_token_id) }}">`: the page form's own `_token` child (rendered by the loop / `form_end`) is now the one checked. A page-edit override that still posts only the top-level `_token` fails CSRF validation. |

`csrf_token_id` is still passed to `page_edit.html.twig` and `ai_ask.html.twig` for backward compatibility but no longer used by the shipped templates.

## To 1.6.4

From **1.6.3**:

```bash
composer update nowo-tech/wiki-bundle
```

## To 1.6.3

From **1.6.2**:

```bash
composer update nowo-tech/wiki-bundle
```

## To 1.6.2

From **1.6.1** — explicit security service definitions win over the resource scan

```bash
composer update nowo-tech/wiki-bundle
```

- No breaking changes. No application upgrade steps.

## To 1.6.1

From **1.6.0** — space export sets application/zip without symfony/mime

```bash
composer update nowo-tech/wiki-bundle
```

- No breaking changes. No application upgrade steps.

## To 1.6.0

From **1.5.0** — fixes - flash messages rendered, LIKE search escaping, multibyte excerpts, dead config deprecated.

```bash
composer update nowo-tech/wiki-bundle
```

- **Deprecated keys:** remove `nowo_wiki.database.driver`, `nowo_wiki.database.platform` and `nowo_wiki.firewall` from your config. They never had an effect and now trigger a deprecation (removal in 2.0). `database.entity_manager` is still used.
- **Flash messages:** the bundle templates now render session flashes (import results and errors). If your host layout also renders `app.flashes`, the messages are consumed by whichever template runs first; override `@NowoWikiBundle/manage/_flashes.html.twig` with an empty template to keep only yours.

## To 1.5.0

From **1.4.1** — security: HTML sanitizer rebuilt on symfony/html-sanitizer, render-time sanitizing, ZIP import limits.

```bash
composer update nowo-tech/wiki-bundle
```

- **Stricter HTML sanitizer**: `WikiHtmlSanitizer` now removes every attribute that is not allowlisted (event handlers, `srcdoc`, `id`, arbitrary `data-*`, `style` other than `text-align: …`), `javascript:`/`data:` URLs and unknown elements (formatting wrappers such as `<section>` or `<font>` are unwrapped, keeping their text). Iframes need an https `src` on the YouTube/Vimeo allowlist.
- **Render-time sanitizing**: if you override `manage/page_view.html.twig`, replace `revision.contentHtml|raw` with `revision.contentHtml|nowo_wiki_safe_html`.
- **ZIP imports**: archives with more than 10 000 entries or more than 512 MiB uncompressed are rejected; override `WikiArchiveHelper` arguments (`$maxEntries`, `$maxUncompressedBytes`) in your service config if you need larger imports.

## To 1.4.1

From **1.4.0** — dependency refresh and repository maintenance.

```bash
composer update nowo-tech/wiki-bundle
```

- No breaking changes. No application upgrade steps.

## To 1.4.0

From **1.3.5** — Doctrine ORM `^3.7` requirement (`\SortDirection`); dependency updates.

```bash
composer update nowo-tech/wiki-bundle doctrine/orm
php bin/console cache:clear
```

- **Requirement:** `doctrine/orm` must be `^3.7` (ORM 2.x and 3.0–3.6 are no longer supported). It pulls `symfony/polyfill-php86`, which provides the global `\SortDirection` enum on PHP < 8.6.
- No configuration, route or template changes. No database migration.

## To 1.3.5

From **1.3.4** — REQ-CS-008 Igor FrankenPHP worker audit (igor-php require-dev, igor.json, make igor).

```bash
composer update nowo-tech/wiki-bundle
php bin/console cache:clear
```

No breaking changes for configuration. Behaviour changes relevant to FrankenPHP worker mode:

- **Wiki routes outside a secured firewall are anonymous.** The controller, the default access checker and `WikiKnowledgeSearchTool` only use the security token when the main request is handled by a firewall with `security: true`. Keep `route_prefix` behind such a firewall (or use `security.allow_unauthenticated: true`).
- **AI timeout:** set the bundle HTTP client on your platform and tune `nowo_wiki.ai.http_timeout` (seconds, default 30):

```yaml
# config/packages/ai.yaml
ai:
    platform:
        openai:
            http_client: 'nowo_wiki.ai.http_client'
```

- **Custom wiring:** `DoctrineOrmWiki*Repository` and `WikiSearchService` now receive `Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider` from the extension; passing an `EntityManagerInterface` still works. `WikiManageController`, `ConfigurableWikiAccessChecker` and `WikiKnowledgeSearchTool` take an optional last `?WikiTokenGuard` argument.
- No application upgrade steps for require-dev Igor tooling (REQ-CS-008). Consumers do not pull `igor-php/igor-php` transitively.

## From 1.3.3 to 1.3.4

No breaking changes. **No application upgrade steps.**

```bash
composer update nowo-tech/wiki-bundle
```

## To 1.3.3

No application upgrade steps.

```bash
composer update nowo-tech/wiki-bundle
```

## To 1.3.2

No application upgrade steps. **Demos only:** Hot Reload Bundle `^1.4` (FrankenPHP Mercure/`hot_reload`, `dev`/`test`).

```bash
composer update nowo-tech/wiki-bundle
php bin/console cache:clear
```

## To 1.3.1

From **1.3.0** — test-only FormKit merger wiring for controller unit tests. No host migration.

```bash
composer update nowo-tech/wiki-bundle
```

## To 1.3.0

From **1.2.0** — FormKit admin forms, UiKit macros, Twig Extra (REQ-TWIG-004), Twig-CS-Fixer.

```bash
composer update nowo-tech/wiki-bundle
php bin/console cache:clear
php bin/console assets:install --symlink --relative public
```

Requires `nowo-tech/form-kit-bundle` ^2.0 and `nowo-tech/ui-kit-bundle` ^1.4.

### Twig Extra Bundle (REQ-TWIG-004)

Hosts that render this bundle's Twig templates must install:

```bash
composer require twig/extra-bundle twig/string-extra
```

and enable `Twig\Extra\TwigExtraBundle\TwigExtraBundle`. Flex recipes usually register it automatically.

### Twig-CS-Fixer (maintainers)

Package maintainers: `composer twig:lint` / `composer twig:fix` use `.twig-cs-fixer.php` over `src/` (and `templates/` when present).

## To 1.2.0

Minor release: REQ-UI-002 — `security.allow_unauthenticated`, AllowAll checker, SecurityBundle compile-time guard, and soft manage-UI gate.

```bash
composer require nowo-tech/wiki-bundle:^1.2
php bin/console cache:clear
```

### Behaviour

| Topic | Before | 1.2.0 |
| --- | --- | --- |
| Apps without SecurityBundle (Web UI on) | Could boot | Boot fails with `LogicException` unless `allow_unauthenticated: true` |
| Manage auth attribute | `#[IsGranted('IS_AUTHENTICATED')]` | Feature checks via `WikiAccessCheckerInterface` (+ optional AllowAll) |
| Host firewall | Recommended | Still required in production (`access_control` on `/tools/wiki`) |

**Trusted local demos only:**

```yaml
nowo_wiki:
    security:
        allow_unauthenticated: true   # never in production
```

**Production:** keep `allow_unauthenticated: false`, install SecurityBundle, grant roles, and protect manage paths with host `access_control`.

Page mutations still call `requireUser()` — anonymous demos need a host authenticator (or real login) for write flows.

## To 1.1.1

**CI tooling only** (Vitest on Node.js 22). **No application or config changes.**

```bash
composer require nowo-tech/wiki-bundle:^1.1.1
```

Integrators: no changes. Bundle contributors: local/CI TypeScript coverage needs Node **22+** when using `jsdom@30`.

## To 1.1.0

Optional manage page shell for REQ-UI-001. **Non-breaking** — `web_ui.css_framework` / `layout_template` from **1.0.5** unchanged.

```bash
composer require nowo-tech/wiki-bundle:^1.1.0
php bin/console cache:clear
```

- Manage pages go through `@NowoWikiBundle/base.html.twig` (stacks package CSS/JS with `parent()`). Prefer `web_ui.layout_template` at the host chrome instead of freezing every page.
- If you **overrode** manage templates that `{% extends layout %}`, update them to `{% extends '@NowoWikiBundle/base.html.twig' %}` (or keep extending your own shell and stack assets yourself).

```yaml
nowo_wiki:
    web_ui:
        css_framework: bootstrap5
        layout_template: 'layouts/app.html.twig'
```

## To 1.0.5

No breaking changes for application consumers. Defaults keep Tabler-compatible markup.

- Optional `nowo_wiki.web_ui` (`css_framework`, `layout_template`) — see [CONFIGURATION.md](CONFIGURATION.md).
- Demo FrankenPHP image is **PHP 8.5** (`dunglas/frankenphp:1-php8.5-bookworm`); recreate the demo container after pull.
- PHPUnit / CI: `SYMFONY_DEPRECATIONS_HELPER=max[direct]=0` (REQ-SF-005).

```bash
composer update nowo-tech/wiki-bundle
```

## To 1.0.4

No breaking changes for application consumers.

- Composer may resolve `nowo-tech/tiptap-editor-bundle` to `1.2.0` (still within `^1.0`).
- Demo only: optional `FRANKENPHP_MODE=classic|worker` in `demo/symfony8/.env` (default `worker`). Recreate the container after changing it (`docker compose up -d` / `make up`).

## To 1.0.3

No breaking changes for application consumers. CI and test-dev tooling only (`symfony/var-exporter` in `require-dev`, GitHub Actions matrix aligned to Symfony `^7.4`).

## To 1.0.2

No breaking changes. Documentation only: CI requirements live in [GITHUB_CI.md](GITHUB_CI.md) (formerly `GITLAB_CI.md`).

## To 1.0.1

No breaking changes. Optional for consumers:

- Contributors: run `make setup-hooks` once per clone so commit-msg rejects Cursor co-author trailers (REQ-GIT-001).
- See [CHANGELOG.md](CHANGELOG.md) for documentation and CI additions.

## To 1.0.0

Initial stable release. No prior versions.

### Requirements

- PHP `>=8.2 <8.6`
- Symfony `^7.4 || ^8.0`
- `nowo-tech/tiptap-editor-bundle` `^1.0`
- Doctrine ORM 2.15+ or 3.x

### Install / upgrade steps

```bash
composer require nowo-tech/wiki-bundle nowo-tech/tiptap-editor-bundle
```

1. Add `config/packages/nowo_wiki.yaml` with `user_class`.
2. Run Doctrine migrations for `wiki_spaces`, `wiki_pages`, `wiki_page_revisions` (or your `table_prefix`).
3. Implement `WikiAccessCheckerInterface` (or use default role-based checker).
4. For team spaces, implement `WikiTeamMembershipResolverInterface`.
5. Clear cache: `bin/console cache:clear`.

### Optional features

**Import / export** (enabled by default):

```yaml
nowo_wiki:
    import_export:
        enabled: true
        max_upload_bytes: 52428800  # 50 MB
```

Console: `bin/console wiki:import {space} {source}` and `bin/console wiki:export {space} {target} [--zip]`.

**Symfony AI assistant**:

```bash
composer require symfony/ai-bundle
```

```yaml
nowo_wiki:
    ai:
        enabled: true
        agent: wiki_assistant
```

See [CONFIGURATION.md](CONFIGURATION.md) and [USAGE.md](USAGE.md).

### Breaking changes

None (first release).

See [CHANGELOG.md](CHANGELOG.md) for full history.
### FormKitBundle (admin forms)

If you use admin/dashboard Symfony forms, ensure `nowo-tech/form-kit-bundle` ^2.0 is installed (pulled transitively) and `Nowo\FormKitBundle\NowoFormKitBundle` is registered. Form types use profile `wiki` via `#[FormKitConfig]`; the bundle prepends that profile when the host has not defined it.
