# Contributing

Thank you for contributing to Wiki Bundle.

## Code of Conduct

This project follows the [Contributor Covenant Code of Conduct](../CODE_OF_CONDUCT.md). By participating, you are expected to uphold it. Please report unacceptable behavior to **hectorfranco@nowo.tech**.

## Development setup

```bash
make up
make install
make assets
make test
make test-ts
```

Demo (Symfony 8 + FrankenPHP):

```bash
make -C demo/symfony8 up
# http://localhost:8025/tools/wiki
```

## Quality checks

| Command | Scope |
|---------|--------|
| `make qa` | PHP-CS-Fixer + PHPUnit |
| `make phpstan` | Static analysis (level 6 + baseline) |
| `make test-coverage-100` | PHPUnit with 100% line threshold |
| `make test-ts` | Vitest (`wiki.ts`) |
| `make release-check` | Full pre-release pipeline |

Run `make release-check` before tagging a release.

## Pull requests

1. Fork and branch from `main`.
2. Add or update tests for behaviour changes (target 100% PHP coverage on `src/`).
3. Run `make cs-fix` and `make test` before opening the PR.
4. Update `docs/CHANGELOG.md` under `[Unreleased]`.
5. Use the PR template and link related issues.

## Documentation

- User-facing changes: `README.md` and `docs/`.
- Security: `docs/SECURITY.md` and `.github/SECURITY.md`.
- Breaking changes: `docs/UPGRADING.md` and a new section in `CHANGELOG.md`.

## Code style

- PHP: PSR-12 via PHP-CS-Fixer; PHPStan per `phpstan.neon.dist`.
- TypeScript: `src/Resources/assets/src/`; run `make test-ts` after changes.
- Comments and PHPDoc: **English** (see `BUNDLES_FULL_SPECS_CHECKLIST.md`).

## Git hooks (REQ-GIT-001)

Do **not** add `Co-authored-by: Cursor` or `cursoragent@cursor.com` trailers to commit messages.

```bash
make setup-hooks
make check-no-cursor-coauthor
```

`make setup-hooks` installs `.githooks/commit-msg` (or sets `core.hooksPath` to `.githooks`). Run it once per clone before your first commit.

If CI fails because trailers are already on the remote, see [GITLAB_CI.md](GITLAB_CI.md) (REQ-GIT-001) and run `make strip-cursor-coauthor-from-history` before `git push --force-with-lease`.

## License

By contributing, you agree that your contributions will be licensed under the MIT License.
