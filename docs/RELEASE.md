# Release process

## Versioning

[Semantic Versioning](https://semver.org/): `MAJOR.MINOR.PATCH`.

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
5. `sync-releases.yml` mirrors release notes into `CHANGELOG.md` when configured.

### Example for v1.1.1

```bash
git add -A
git -c core.hooksPath=.githooks commit -m "Release 1.1.1: Vitest CI on Node.js 22"
make check-no-cursor-coauthor
git tag -a v1.1.1 -m "Release 1.1.1 - Vitest CI on Node.js 22"
git push origin main
git push origin v1.1.1
```

### Example for v1.1.0

```bash
git add -A
git -c core.hooksPath=.githooks commit -m "Release 1.1.0: base.html.twig parent() stacking (REQ-UI-001)"
make check-no-cursor-coauthor
git tag -a v1.1.0 -m "Release 1.1.0 - base.html.twig parent() stacking (REQ-UI-001)"
git push origin main
git push origin v1.1.0
```

### Example for v1.0.5

```bash
git add -A
git -c core.hooksPath=.githooks commit -m "Release 1.0.5: web_ui hooks, FrankenPHP banner, demo php8.5"
make check-no-cursor-coauthor
git tag -a v1.0.5 -m "Release 1.0.5"
git push origin main
git push origin v1.0.5
```

## Packagist

Package: [nowo-tech/wiki-bundle](https://packagist.org/packages/nowo-tech/wiki-bundle).

## Demo verification

```bash
make -C demo/symfony8 up
curl -sf http://localhost:8025/tools/wiki
```

## Post-release

- Verify Packagist version and GitHub release assets.
- Open `[Unreleased]` section in `CHANGELOG.md` for the next cycle.

After creating the release commit and tag, run `make check-no-cursor-coauthor` again **before** `git push` (REQ-GIT-001). The release commit itself is not covered by an earlier `release-check` run.
