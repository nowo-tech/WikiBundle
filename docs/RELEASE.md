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
2. Commit and push to `main`.
3. Create GitHub release — `release.yml` publishes to Packagist when the tag is pushed.
4. `sync-releases.yml` mirrors release notes into `CHANGELOG.md` when configured.

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
