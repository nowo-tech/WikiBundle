# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/nowo-tech/WikiBundle/compare/v1.0.3...HEAD
[1.0.3]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.0.3
[1.0.2]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.0.2
[1.0.1]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.0.1
[1.0.0]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.0.0
