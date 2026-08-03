# Release process

## Versioning

[Semantic Versioning](https://semver.org/): `MAJOR.MINOR.PATCH`.

Current stable target: **v1.2.0**.

## Pre-release checklist

```bash
make release-check
```

This runs: `composer validate`, CS-Fixer, Rector dry-run, PHPStan, translation YAML parse, PHPUnit 100% coverage, demo checks, Vitest.

## Tagging

1. Update `docs/CHANGELOG.md` (move `[Unreleased]` entries to `[x.y.z] - YYYY-MM-DD`).
2. Update `docs/UPGRADING.md` when integrators need notes.
3. Commit and push to `main`.
4. Create annotated tag and push — `release.yml` publishes to Packagist when the tag is pushed.
5. Re-run `make check-no-cursor-coauthor` **before** `git push` (REQ-GIT-001).

### Example for v1.2.0

```bash
git add -A
git -c core.hooksPath=.githooks commit -m "feat(security): REQ-UI-002 allow_unauthenticated and AllowAll (v1.2.0)"
make check-no-cursor-coauthor
git tag -a v1.2.0 -m "Release v1.2.0 - REQ-UI-002 allow_unauthenticated / AllowAll"
git push origin main
git push origin v1.2.0
```

### Example for v1.1.1

```bash
git add -A
git -c core.hooksPath=.githooks commit -m "Release 1.1.1: Vitest CI on Node.js 22"
make check-no-cursor-coauthor
git tag -a v1.1.1 -m "Release 1.1.1 - Vitest CI on Node.js 22"
git push origin main
git push origin v1.1.1
```
