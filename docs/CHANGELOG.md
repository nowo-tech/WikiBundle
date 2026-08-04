# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/nowo-tech/WikiBundle/compare/v1.2.0...HEAD
[1.2.0]: https://github.com/nowo-tech/WikiBundle/compare/v1.1.1...v1.2.0
[1.1.1]: https://github.com/nowo-tech/WikiBundle/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/nowo-tech/WikiBundle/compare/v1.0.5...v1.1.0
[1.0.5]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.0.5
[1.0.4]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.0.4
[1.0.3]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.0.3
[1.0.2]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.0.2
[1.0.1]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.0.1
[1.0.0]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.0.0
