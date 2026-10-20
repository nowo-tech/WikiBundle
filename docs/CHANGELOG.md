# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[1.6.3] - 2026-10-18](#163---2026-10-18)
- [[1.6.2] - 2026-10-13](#162---2026-10-13)
- [[1.6.1] - 2026-10-13](#161---2026-10-13)
- [[1.6.0] - 2026-10-13](#160---2026-10-13)
- [[1.5.0] - 2026-10-13](#150---2026-10-13)
- [[1.4.1] - 2026-10-13](#141---2026-10-13)
- [[1.4.0] - 2026-10-09](#140---2026-10-09)
- [[1.3.5] - 2026-09-27](#135---2026-09-27)
- [[1.3.4] - 2026-08-24](#134---2026-08-24)
- [[1.3.3] - 2026-08-19](#133---2026-08-19)
- [[1.3.2] - 2026-08-18](#132---2026-08-18)
- [[1.3.1] - 2026-08-07](#131---2026-08-07)
- [[1.3.0] - 2026-08-04](#130---2026-08-04)
- [[1.2.0] - 2026-08-03](#120---2026-08-03)
- [[1.1.1] - 2026-07-31](#111---2026-07-31)
- [[1.1.0] - 2026-07-30](#110---2026-07-30)
- [[1.0.5] - 2026-07-29](#105---2026-07-29)
- [[1.0.4] - 2026-07-22](#104---2026-07-22)
- [[1.0.3] - 2026-07-16](#103---2026-07-16)
- [[1.0.2] - 2026-07-16](#102---2026-07-16)
- [[1.0.1] - 2026-07-16](#101---2026-07-16)
- [[1.0.0] - 2026-07-13](#100---2026-07-13)

## [Unreleased]

### Changed

- Documentation: wave-1 REQ compliance — README preamble order, stars badge image and documentation list order, CONTRIBUTING Cursor Agent settings and post-release-commit trailer check, canonical bug-report template, demo `.env.example` / `.env.test` comments, DEMO-FRANKENPHP troubleshooting linked from `demo/README.md`, doc TOCs, Spec Kit inventory refresh (92/92).

## [1.6.3] - 2026-10-18

Allows nowo-tech/ui-kit-bundle 2.x (^1.4 || ^2.2) so hosts can move to UiKit 2.2. See [UPGRADING](UPGRADING.md#to-163).

### Changed

- Allows nowo-tech/ui-kit-bundle 2.x (`^1.4 || ^2.2`); the dev lock and the demo lock use 2.2.0. Templates are unchanged (compatibility release). With UiKit 2.x `nowo-ui.css` ships in `@layer nowo-ui`, so unlayered host/framework CSS wins equal-specificity conflicts with kit rules in the wiki pages; to keep the 1.x cascade, override the `stylesheets` block of `base.html.twig` / `layout.html.twig` and link `css/nowo-ui.unlayered.css` (UiKit 2.x only).

[1.6.3]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.6.3

## [1.6.2] - 2026-10-13

### Changed

- `WikiExtension::load()` now loads `services.yaml` before its explicit definitions, so `NullWikiTeamMembershipResolver`, `WikiHtmlSanitizer` and the access checkers keep the definitions registered by the extension instead of being overwritten by the `Security/` resource scan. No runtime behaviour change (both services have no required arguments); a DI test asserts the final definitions and aliases.

[1.6.2]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.6.2

## [1.6.1] - 2026-10-13

### Fixed

- Space export (`GET /tools/wiki/spaces/{slug}/export`) no longer fails with a 500 "You cannot guess the mime type as the Mime component is not installed": the ZIP download now sets `Content-Type: application/zip` explicitly instead of relying on `symfony/mime` (not a dependency of the bundle) to guess it.

[1.6.1]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.6.1

## [1.6.0] - 2026-10-13

### Fixed

- Flash messages are now rendered by the bundle templates (new `manage/_flashes.html.twig`, included from `_page_header.html.twig` and `page_view.html.twig`): import errors, the import report and creation errors were previously added to the session but never shown.
- Search: LIKE wildcards in the query (`%`, `_`) now match literally on every platform (`ESCAPE '!'` clause with proper escaping); before, `50%` or `snake_case` found nothing on SQLite/PostgreSQL.
- Search excerpts are centred on the match when the text before it contains multibyte characters (`mb_stripos` instead of a byte offset).
- Templates use the `include()` function instead of the `{% include %}` tag so `composer twig:lint` passes.

### Deprecated

- `nowo_wiki.database.driver`, `nowo_wiki.database.platform` and `nowo_wiki.firewall` were never read; they now trigger a deprecation and will be removed in 2.0.

### Changed

- Development: vitest and `@vitest/coverage-v8` upgraded to ^4.1.11.
- Docs: `UPGRADING.md` no longer repeats the "From 1.3.3 to 1.3.4" section or a stray `# Upgrading` heading; the 1.3.0 Twig notes are merged into the "To 1.3.0" section.

[1.6.0]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.6.0

## [1.5.0] - 2026-10-13

### Security

- `WikiHtmlSanitizer` is now built on `symfony/html-sanitizer` (DOM parsing, explicit element/attribute allowlist). The former regex/`strip_tags` implementation could be bypassed (stored XSS) with unquoted event handlers (`<img src=x onerror=…>`), unclosed `<iframe>`, `<iframe srcdoc>`, unquoted or entity-encoded `javascript:` URLs and `style` attributes. Links accept only http/https/mailto (or relative) URLs, media only http/https (or relative), `style` only a plain `text-align`, and iframes only an https `src` on the embed host allowlist.
- Page bodies are re-sanitized at render time: `manage/page_view.html.twig` uses the new `nowo_wiki_safe_html` Twig filter instead of `|raw`, so revisions stored before this fix are cleaned when displayed.
- ZIP imports are rejected before extraction when they contain more than 10 000 entries or more than 512 MiB of declared uncompressed data (ZIP bomb protection; limits are `WikiArchiveHelper` constructor arguments).

### Added

- `symfony/html-sanitizer` is now a runtime dependency.
- `nowo_wiki_safe_html` Twig filter for rendering stored wiki HTML in custom templates.

[1.5.0]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.5.0

## [1.4.1] - 2026-10-13

### Changed

- Development: `composer.json` pins `config.platform.php` to 8.2.0 so the committed lock stays installable on the minimum PHP; CI overrides the platform per matrix cell.
- Repository: `.twig-cs-fixer.cache` is now git-ignored.

### Dependencies

- `nowo-tech/form-kit-bundle` 2.6.1 → 2.7.1 (development lock; #54).
- `nowo-tech/ui-kit-bundle` 1.9.2 → 1.11.0 (development lock; #55).
- `doctrine/orm` 3.7.4 → 3.7.5 (development lock; #56).
- `jsdom` 30.1.1 → 30.1.2 (frontend tooling; #57).
- `vite` 8.3.2 → 8.3.4 (frontend tooling; #58).

[1.4.1]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.4.1

## [1.4.0] - 2026-10-09

### Changed

- **Doctrine ORM SortDirection:** the Doctrine ORM repositories (`DoctrineOrmWikiPageRepository`, `DoctrineOrmWikiPageRevisionRepository`, `DoctrineOrmWikiSpaceRepository`) pass `\SortDirection::Ascending`/`Descending` to QueryBuilder `orderBy()`/`addOrderBy()` instead of `'ASC'`/`'DESC'` (doctrine/orm deprecation, https://github.com/doctrine/orm/issues/11313).
- **Requirement:** `doctrine/orm` is now `^3.7` (was `^2.15 || ^3.0`).

### Fixed

- Tests: `WikiRepositoryWorkerTest` enables Doctrine native lazy objects only on PHP 8.4+, keeping the worker integration test green across the CI matrix.

### Dependencies

- Runtime lock: `doctrine/orm` 3.7.4, `doctrine/dbal` 4.5.0, `nowo-tech/form-kit-bundle` 2.6.1, `nowo-tech/ui-kit-bundle` 1.9.2, `nowo-tech/tiptap-editor-bundle` 1.3.10, `league/commonmark` 2.10.3, `league/html-to-markdown` 5.1.3 (Dependabot #42–#52 + update).
- Dev tooling: `igor-php/igor-php` ^0.10 (v0.10.1), `nowo-tech/phpstan-frankenphp` v1.2.3, `phpstan/phpstan` 2.3.1, `phpstan/phpstan-phpunit` 2.1.1, `phpstan/phpstan-symfony` 2.1.0, `rector/rector` 2.7.0, `phpunit/phpunit` 10.5.66, `friendsofphp/php-cs-fixer` 3.95.27.
- JS dev: Vite 8.3.2, jsdom 30.1.1.
- Demo (Symfony 8): same runtime bumps plus `doctrine/migrations` 3.9.8, `twig/twig` v3.30.0, `twig/extra-bundle` v3.29.0, `nowo-tech/hot-reload-bundle` v1.5.5, `nowo-tech/twig-inspector-bundle` v1.1.7.

[1.4.0]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.4.0

## [1.3.5] - 2026-09-27

### Fixed

- **FrankenPHP worker (no kernel reset):** repositories and `WikiSearchService` resolve the entity manager per call through `WikiEntityManagerProvider`, resetting a manager closed by a failed flush in an earlier request; page/space reads refresh from the database so changes by other workers are not served stale.
- **Security (worker):** new `WikiTokenGuard`; the controller, `ConfigurableWikiAccessChecker` and `WikiKnowledgeSearchTool` ignore a token left by a previous request when the current request is not behind a secured firewall.
- **Export:** a failed space export removes the temporary ZIP before rethrowing.

### Added

- **REQ-CS-008:** `igor-php/igor-php` (require-dev only), root `igor.json`, Composer/`Makefile` `igor` target, and `release-check` wiring for FrankenPHP worker-state audit.
- `ai.http_timeout` (default 30 s) and the `nowo_wiki.ai.http_client` service (timeout + max duration) to set as `http_client` of the AI platform.

### Changed

- **Worker safety (Igor):** justified `// @igor-ignore` annotations and/or `ResetInterface` / request-scoped fixes so `make igor` passes on package `src/`.
- Repository and `WikiSearchService` constructors accept `EntityManagerInterface|WikiEntityManagerProvider`; controller, access checker and AI tool accept an optional `WikiTokenGuard`. See [UPGRADING](UPGRADING.md) and [FRANKENPHP-WORKER-AUDIT](FRANKENPHP-WORKER-AUDIT.md).

[1.3.5]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.3.5

## [1.3.4] - 2026-08-24

### Changed

- **Demos:** MySQL env policy in FrankenPHP stack (REQ-DEMO-011).
- **QA:** add `phpstan-frankenphp` extension (REQ-CS-005).
- **Assets:** English JSDoc on TypeScript sources (REQ-ASSETS-002).
- **Docs:** PHP-FIG PSR evaluation (REQ-CS-007).
- **Style:** PHP CS Fixer alignment.

### Notes

- **No API or configuration changes** for integrators unless noted above.

[1.3.4]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.3.4

## [1.3.3] - 2026-08-19

### Changed

- Code style only (PHP CS Fixer). **No application upgrade steps.**

[1.3.3]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.3.3

## [1.3.2] - 2026-08-18

### Changed

- **Demos:** pin `nowo-tech/hot-reload-bundle` to `^1.4` with FrankenPHP Mercure/`hot_reload` (`dev`/`test` only).

[1.3.2]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.3.2

## [1.3.1] - 2026-08-07

### Fixed

- **CI / FormKit:** `WikiControllerContainerBuilder` injects `FormOptionsMerger` via `FormKitTestSupport::withMerger()` so controller unit tests no longer hit uninitialized `$formOptionsMerger` on `WikiPageFormType`.

[1.3.1]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.3.1

## [1.3.0] - 2026-08-04

### Changed

- **FormKitBundle:** depend on [`nowo-tech/form-kit-bundle`](https://github.com/nowo-tech/FormKitBundle) ^2.0. Admin form types use `FormOptionsTrait` + profile `wiki` (`#[FormKitConfig]`). Extension prepends that profile when missing; form types are tagged `form.type` so `FormOptionsMerger` is injected.

### Added
- **REQ-TWIG-004:** require `twig/extra-bundle` + `twig/string-extra`; `make check-twig-extra` in `release-check`; demos register `TwigExtraBundle`.
- **Twig-CS-Fixer:** `vincentlanglet/twig-cs-fixer`, `.twig-cs-fixer.php`, `composer twig:lint` / `twig:fix`.

### Changed

- **REQ-UI-001-kit:** Requires `nowo-tech/ui-kit-bundle` `^1.4`. Layout/base load `asset('css/nowo-ui.css', 'nowo_ui_kit')` and import `@NowoUiKitBundle/macros/ui.html.twig`. Extension seeds `nowo_ui_kit` from `web_ui.css_framework` when the host has not configured UiKit. Demo registers `NowoUiKitBundle` + `nowo_ui_kit.yaml`.

[1.3.0]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.3.0

## [1.2.0] - 2026-08-03

### Added

- **REQ-UI-002:** `security.allow_unauthenticated` (default `false`) with `AllowAllWikiAccessChecker`, compile-time SecurityBundle guard when the manage Web UI is enabled, and controller soft-gate (no hard `#[IsGranted('IS_AUTHENTICATED')]`).
- Docs: CONFIGURATION / SECURITY host firewall example; Flex recipe comments for `access_roles` / `allow_unauthenticated`.

### Changed

- Demo: resolve TipTap from Packagist (no sibling Docker mount); FrankenPHP docs/README aligned.
- Dev deps: `actions/stale` v11, jsdom 30.0.1, vite 8.2.0; TipTap / Rector lock refresh.

### Compatibility

- PHP `>=8.2 <8.6`; Symfony `^7.4 || ^8.0` (see `composer.json`).
- Manage UI with default security settings requires **SecurityBundle** (or set `allow_unauthenticated: true` for trusted local demos only).

### Upgrade

```bash
composer require nowo-tech/wiki-bundle:^1.2
```

See [UPGRADING.md](UPGRADING.md).

## [1.1.1] - 2026-07-31

### Fixed

- **CI / Vitest** — TypeScript job uses Node.js **22** so `jsdom@30` / `undici@8` can load (`markAsUncloneable`); Node 20 failed `pnpm run test:coverage`.
- **Demo** — `release-check` / `update-bundle` start the FrankenPHP container via `ensure-up` before Composer/exec.

### Tests

- Unit coverage for `web_ui.layout_template` → `nowo_wiki.templates.layout` and Twig `nowo_wiki_web_ui` prepend (restores **100%** PHP line coverage).

### Upgrade

```bash
composer require nowo-tech/wiki-bundle:^1.1.1
```

No application or config API changes. See [UPGRADING.md](UPGRADING.md).

## [1.1.0] - 2026-07-30

### Added

- Intermediate shell **`base.html.twig`**: manage pages extend it; stacks `wiki.css` / `wiki.js` with **`{{ parent() }}`** so host layouts keep their assets (REQ-UI-001).

### Changed

- Manage templates extend **`@NowoWikiBundle/base.html.twig`** instead of `layout` directly.
- Default **`layout.html.twig`**: package assets moved into `base`; CDN gated by `web_ui.css_framework` (Tabler/Bootstrap-compatible).

### Documentation

- **[USAGE](USAGE.md)** / **[CONFIGURATION](CONFIGURATION.md):** host layout preference, freeze rule, `base` shell.
- **[UPGRADING](UPGRADING.md)** section **To 1.1.0**.

## [1.0.5] - 2026-07-29

### Added

- FrankenPHP Friendly Worker Mode banner (REQ-DOCS-017); `make check-open-prs` / `demo-smoke`.
- `SYMFONY_DEPRECATIONS_HELPER=max[direct]=0` (REQ-SF-005).
- `nowo_wiki.web_ui` (`css_framework`, `layout_template`) + `nowo-ui-*` layout hooks (REQ-UI-001).
- GitHub About topics/homepage; optional GH workflows (stale, pr-lint, copilot-instructions).

### Changed

- Demo FrankenPHP image **PHP 8.5**; PHPStan level **8** with empty `ignoreErrors` (REQ-CS-005/006).
- README Documentation order and badge order (REQ-DOCS-002/004).
- Packagist keywords include `php` and `frankenphp`.

## [1.0.4] - 2026-07-22

### Added

- **Demo** — `FRANKENPHP_MODE` (`worker` default, or `classic`) selects FrankenPHP Caddyfile at container start; documented in [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md).

### Changed

- **Dependencies** — `nowo-tech/tiptap-editor-bundle` lockfile bump to `1.2.0` (constraint remains `^1.0`); dev `jsdom` to `29.1.1`.
- **REQ-GIT-001** — Co-author check uses `git --no-replace-objects`; strip script refuses dirty working trees; [GITHUB_CI.md](GITHUB_CI.md) expanded as canonical adoption doc.
- **Tooling** — PHP-CS-Fixer `fully_qualified_strict_types.import_symbols` enabled (import + short class names).

## [1.0.3] - 2026-07-16

### Fixed

- **CI / Doctrine** — `WikiMetadataListenerDoctrineTest` loads attribute metadata via `AttributeDriver` (no `EntityManager` / LazyGhost), fixing PHPUnit failures on PHP 8.4+ in GitHub Actions.
- **CI matrix** — Drop Symfony 7.0 (unsupported; `composer.json` requires `^7.4 || ^8.0`); coverage jobs use Symfony 7.4.

### Added

- **`symfony/var-exporter`** (`require-dev`) — available for Doctrine ORM proxy / LazyGhost scenarios in local and CI installs.

### Changed

- **CI** — Full `composer update --with-all-dependencies` in the test matrix; Dependabot bumps (`actions/checkout` v7, `actions/setup-node` v7, Vite, TypeScript).

## [1.0.2] - 2026-07-16

### Changed

- **Docs** — Rename `docs/GITLAB_CI.md` to `docs/GITHUB_CI.md` (GitHub Actions wording); update README and CONTRIBUTING links.

## [1.0.1] - 2026-07-16

### Fixed

- **i18n** — Complete missing `wiki.editor.help` and `wiki.ai.follow_up` keys in `de`, `fr`, `it`, `nl`, and `pt` translation files (parity with English/Spanish).

### Added

- **Code of Conduct** — Contributor Covenant (`CODE_OF_CONDUCT.md`).
- **Git hygiene (REQ-GIT-001)** — `.githooks/commit-msg`, `make setup-hooks`, `make check-no-cursor-coauthor`, CI job to reject Cursor co-author trailers.
- **Docs** — `docs/GITLAB_CI.md`; links from README and CONTRIBUTING; release checklist note to re-check co-author trailers before push.

### Changed

- **`make release-check`** — Runs `check-no-cursor-coauthor` first.

## [1.0.0] - 2026-07-13

### Added

- **Core wiki** — spaces (team/user scope), page tree, immutable revisions, diff, search.
- **Tiptap integration** — `WikiPageFormType` + `nowo-tech/tiptap-editor-bundle` (Notion variant).
- **Import / export** — Outline and Notion Markdown interchange (UI + `wiki:import` / `wiki:export` console commands, ZIP support).
- **Symfony AI (optional)** — `/tools/wiki/ask`, `WikiKnowledgeSearchTool`, context injection via `symfony/ai-bundle`.
- **Security** — `WikiAccessCheckerInterface`, `WikiSpaceAccessResolver`, CSRF on POST, `WikiHtmlSanitizer`, role-based import/export ACL.
- **Symfony** — dynamic routes, Twig overrides, Flex recipe `1.0.0`, Doctrine metadata listener.
- **i18n** — `NowoWikiBundle` translations (`en`, `es`, `fr`, `de`, `it`, `pt`, `nl`).
- **Tests** — 230 PHPUnit tests, 100% PHP coverage; Vitest for `wiki.ts`.
- **CI** — GitHub Actions (PHPUnit, PHPStan, Vitest, `composer audit`).
- **Demo** — Symfony 8 + FrankenPHP on port **8025** with auto-login fixtures.

### Security

- HTML sanitized on every revision save; see [SECURITY.md](SECURITY.md).

[Unreleased]: https://github.com/nowo-tech/WikiBundle/compare/v1.6.3...HEAD
[1.2.0]: https://github.com/nowo-tech/WikiBundle/compare/v1.1.1...v1.2.0
[1.1.1]: https://github.com/nowo-tech/WikiBundle/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/nowo-tech/WikiBundle/compare/v1.0.5...v1.1.0
[1.0.5]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.0.5
[1.0.4]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.0.4
[1.0.3]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.0.3
[1.0.2]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.0.2
[1.0.1]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.0.1
[1.0.0]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.0.0
