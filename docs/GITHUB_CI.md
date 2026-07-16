# GitHub Actions CI — requirements and configuration

This document describes the repository **CI requirements** for REQ-GIT-001 and how they are enforced on GitHub Actions.

## CI requirements

### REQ-GIT-001 — History without Cursor co-author

Commit messages **must not** include Cursor agent trailers:

```text
Co-authored-by: Cursor <cursoragent@cursor.com>
```

or any variant containing `cursoragent@cursor.com`.

| Artifact | Location | Purpose |
|----------|----------|---------|
| Verification | `.scripts/check-no-cursor-coauthor.sh` | Fails if the ref history contains trailers |
| Cleanup | `.scripts/strip-cursor-coauthor-from-history.sh` | Rewrites messages and removes existing trailers |
| Preventive hook | `.githooks/commit-msg` | Strips trailers before creating the commit (`make setup-hooks`) |
| Makefile | `make check-no-cursor-coauthor` | Local shortcut and part of `make release-check` |
| Makefile | `make strip-cursor-coauthor-from-history` | Rewrites local `main` history (then `force-push`) |

#### Verify (local or CI job)

```bash
chmod +x .scripts/check-no-cursor-coauthor.sh
./.scripts/check-no-cursor-coauthor.sh HEAD
```

Equivalent:

```bash
make setup-hooks    # once per clone
make check-no-cursor-coauthor
```

On failure, the script lists affected commits.

#### Clean already-published history

When the check fails in CI (fresh clone from remote), **`git replace` does not help**: it only hides dirty commits on your machine and does not fix `origin`.

1. Ensure you have no uncommitted changes.
2. Run the rewrite on the main branch (default `main`):

```bash
chmod +x .scripts/strip-cursor-coauthor-from-history.sh
./.scripts/strip-cursor-coauthor-from-history.sh main
```

3. Verify again:

```bash
make check-no-cursor-coauthor
```

4. Publish the rewritten history (coordinate with the team):

```bash
git push --force-with-lease origin main
```

5. If release tags are affected, recreate them on the release commit and force-push the tag.

#### Example job in `.github/workflows/ci.yml`

```yaml
git-hygiene:
  name: Git history (no Cursor co-author)
  runs-on: ubuntu-latest
  steps:
    - name: Checkout code
      uses: actions/checkout@v6
      with:
        fetch-depth: 0

    - name: Check for Cursor co-author trailers (REQ-GIT-001)
      run: |
        chmod +x .scripts/check-no-cursor-coauthor.sh
        ./.scripts/check-no-cursor-coauthor.sh HEAD
```

`fetch-depth: 0` is required: with a shallow clone the job does not see full history and may pass incorrectly.

#### Prevention

- Run `make setup-hooks` when cloning.
- Do not add `Co-authored-by: Cursor` manually to commit messages.
- Before release: `make release-check` (includes `check-no-cursor-coauthor`).

---

## References

- [CONTRIBUTING.md](CONTRIBUTING.md) — hooks and contribution flow
- [RELEASE.md](RELEASE.md) — `check-no-cursor-coauthor` before release push
- [.github/workflows/ci.yml](../.github/workflows/ci.yml) — `git-hygiene` job on GitHub Actions
