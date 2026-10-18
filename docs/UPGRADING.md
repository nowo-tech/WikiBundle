# Upgrading

## Table of contents

- [Unreleased](#unreleased)
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
