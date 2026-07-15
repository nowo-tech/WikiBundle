# Wiki Bundle

[![CI](https://github.com/nowo-tech/WikiBundle/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/WikiBundle/actions/workflows/ci.yml) [![Packagist Version](https://img.shields.io/packagist/v/nowo-tech/wiki-bundle.svg?style=flat)](https://packagist.org/packages/nowo-tech/wiki-bundle) [![Packagist Downloads](https://img.shields.io/packagist/dt/nowo-tech/wiki-bundle.svg)](https://packagist.org/packages/nowo-tech/wiki-bundle) [![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE) [![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php)](https://php.net) [![Symfony](https://img.shields.io/badge/Symfony-7.4%20%7C%208.0%20%7C%208.1-000000?logo=symfony)](https://symfony.com) [![Coverage](https://img.shields.io/badge/Coverage-100%25-brightgreen)](#tests-and-coverage) [![GitHub stars](https://img.shields.io/github/stars/nowo-tech/WikiBundle.svg?style=social&label=Star)](https://github.com/nowo-tech/WikiBundle)

> ⭐ **Found this useful?** Give it a **star** on [GitHub](https://github.com/nowo-tech/WikiBundle) so more developers can find it.

Symfony bundle for a **versionable team wiki**: spaces, page trees, immutable revisions, diff, search, and Tiptap rich-text editing (Notion-like variant).

## Features

- Wiki **spaces** scoped per team or user
- **Pages** with slug, parent/child tree, archive (revisions kept)
- **Immutable revisions** on every save
- **Revision diff** between any two versions
- Full-text **search** in title and current revision body (per space or all accessible spaces), with contextual excerpts
- **Import / export** — Outline and Notion Markdown trees (UI + `wiki:import` / `wiki:export` commands)
- **Symfony AI (optional)** — ask questions about wiki content with RAG context (`symfony/ai-bundle`)
- `WikiAccessCheckerInterface` + `WikiTeamMembershipResolverInterface` for app ACL
- Twig layout overrides + CSRF on POST actions
- Integrates **[TiptapEditorBundle](https://github.com/nowo-tech/TiptapEditorBundle)** (`variant: notion` by default)

## Installation

```bash
composer require nowo-tech/wiki-bundle nowo-tech/tiptap-editor-bundle
```

```yaml
# config/packages/nowo_wiki.yaml
nowo_wiki:
    user_class: App\Entity\User
```

See [Installation](docs/INSTALLATION.md).

## Demo

```bash
make -C demo/symfony8 up
# Demo started at: http://localhost:8025  →  /tools/wiki
```

## Documentation

- [GitLab CI requirements](docs/GITLAB_CI.md)
- [Installation](docs/INSTALLATION.md)
- [Configuration](docs/CONFIGURATION.md)
- [Usage](docs/USAGE.md)
- [Contributing](docs/CONTRIBUTING.md)
- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Changelog](docs/CHANGELOG.md)
- [Upgrading](docs/UPGRADING.md)
- [Release](docs/RELEASE.md)
- [Security](docs/SECURITY.md)
- [Engram](docs/ENGRAM.md)
- [Spec-driven development](docs/SPEC-DRIVEN-DEVELOPMENT.md)
- [GitHub Spec Kit](docs/SPEC-KIT.md)
### Additional documentation

- [Demo with FrankenPHP](docs/DEMO-FRANKENPHP.md)

## Tests and coverage

| Language | Coverage |
|----------|----------|
| PHP | **100%** — `make test-coverage` |
| TypeScript | **100%** — `make test-ts` (wiki assets) |

PHPUnit: **230 tests**. Coverage threshold enforced via `make test-coverage-100` / `composer test-coverage-100`.

## License

MIT — see [LICENSE](LICENSE).
