# Spec-driven development — WikiBundle

In this repository, **spec-driven development** has three layers that stay in sync:

1. **GitHub Spec Kit baseline** — [`specs/001-baseline/`](../specs/001-baseline/) ([`spec.md`](../specs/001-baseline/spec.md), [`code-inventory.md`](../specs/001-baseline/code-inventory.md)), initialized with [GitHub Spec Kit](https://github.com/github/spec-kit) (`.specify/`, **Cursor Agent** skills in `.cursor/skills/speckit-*`). The inventory maps **100%** of production code in `src/`. **How to install, initialize, and use Spec Kit:** [`SPEC-KIT.md`](SPEC-KIT.md).
2. **Product behavior** — what **WikiBundle** guarantees to applications that integrate it (see [`USAGE.md`](USAGE.md), [`CONFIGURATION.md`](CONFIGURATION.md), [`INSTALLATION.md`](INSTALLATION.md)). **PHPUnit** and **PHPStan** (and **Vitest** when applicable) enforce contracts in CI where applicable.
3. **Traceability anchors** — stable **`REQ-*`** identifiers in Makefiles and demos (when present) so changes to scripts, ports, and demo workflows stay discoverable from issues and PRs.

There is no separate executable spec language (for example Gherkin); Spec Kit specs, tests, and static analysis are the mechanical proof alongside this document.

---

## Product vision

Versionable internal wiki for Symfony apps: runbooks, ADRs, and team documentation with Outline/Notion-style rich text (Tiptap), immutable revisions, and pluggable ACL.

## Scope

| In scope (v1) | Out of scope (v1) |
|---------------|-------------------|
| Spaces (team/user), page tree, revisions, diff, search | Real-time collaborative editing |
| Tiptap HTML editor, archive pages | Public/anonymous wiki |
| Markdown/Obsidian import-export (CLI + interchange services) | Built-in multi-tenant billing |
| Optional Symfony AI assistant (`WikiAiAssistantInterface`) | Full CSP / rate-limit middleware in bundle |
| `WikiAccessCheckerInterface`, team membership resolver | |
| CSRF + HTML sanitization on save | |

## User stories

| ID | Story |
|----|-------|
| US-01 | As a team member, I browse wiki spaces and a page tree. |
| US-02 | As an editor, I create and update pages with Tiptap rich text. |
| US-03 | As a reader, I view page history and diff two revisions. |
| US-04 | As a user, I search pages by title and content within a space. |
| US-05 | As an integrator, I plug team ACL via access checker and membership resolver. |
| US-06 | As a maintainer, I import/export wiki archives via CLI (`wiki:import`, `wiki:export`). |
| US-07 | As a user, I ask the optional AI assistant questions grounded in wiki content. |

## Business rules

| ID | Rule |
|----|------|
| BR-WIKI-001 | Unique slug per space |
| BR-WIKI-002 | Revisions are append-only; archive pages instead of deleting |
| BR-WIKI-003 | Only authorized users can edit (via `WikiAccessCheckerInterface`) |
| BR-WIKI-004 | Diff available between any pair of revisions in the same page |
| BR-WIKI-005 | HTML content is sanitized before persistence |

## REQ traceability

| REQ | Location / validation |
|-----|----------------------|
| REQ-TEST-001 | `make test`, `composer test` |
| REQ-TEST-003 | `make test-coverage-100` (100% PHP lines) |
| REQ-TEST-007 | README coverage table |
| REQ-TEST-008 | `.scripts/php-coverage-percent.sh` |
| REQ-TEST-009 | `make test-ts`, Vitest `wiki.ts` |
| REQ-SEC-001 | `docs/SECURITY.md` |
| REQ-TWIG-001 | `TwigPathsPass`, app overrides under `templates/bundles/NowoWikiBundle/` |
| REQ-I18N-002 | `src/Resources/translations/NowoWikiBundle.*.yaml` (7 locales) |
| REQ-DEMO-005 | `demo/symfony8/Makefile` → port **8025** |
| REQ-DEMO-007 | `demo/symfony8` target `update-bundle` |
| REQ-RECIPE-001 | `.symfony/recipe/nowo-tech/wiki-bundle/1.0.0/` |

## Validating the functional spec

```bash
make test-coverage-100   # all business rules covered by unit/integration tests
make test-ts
make release-check       # full gate before release
make -C demo/symfony8 up # manual US-01–US-04 in browser
```

**Rule:** behaviour changes require tests; coverage must stay at 100% on `src/` (justified exclusions only in `phpunit.xml.dist`).

## Engram

See [ENGRAM.md](ENGRAM.md) for persistent product memory in IDE workflows (`.cursor/mcp.json` → Engram MCP).


## Suggested workflow for contributors

1. **Clarify behavior** in an issue or draft PR: acceptance criteria for the **product** and, if relevant, **Makefiles/demos** (`REQ-*`).
2. **Implement** with tests and static analysis.
3. **Anchor scripts and demos** when dev UX changes: add or adjust `REQ-*` comments and the requirement table.
4. **Ship integrator docs** when behavior or configuration changes: [`USAGE.md`](USAGE.md), [`CONFIGURATION.md`](CONFIGURATION.md), [`CHANGELOG.md`](CHANGELOG.md), and [`UPGRADING.md`](UPGRADING.md) when consumers must change code or config.
5. **Keep Spec Kit artifacts in sync** when production code under `src/` changes:
   - Update [`specs/001-baseline/spec.md`](../specs/001-baseline/spec.md) and [`code-inventory.md`](../specs/001-baseline/code-inventory.md).
   - Follow the maintainer checklist in [`SPEC-KIT.md`](SPEC-KIT.md).
   - For **new features**, use Cursor Agent skills (`/speckit-specify`, `/speckit-plan`, `/speckit-tasks`) as documented in SPEC-KIT.

---

## GitHub Spec Kit (summary)

This repository uses [GitHub Spec Kit](https://github.com/github/spec-kit) with **Cursor Agent** (`cursor-agent` integration).

| Artifact | Path |
| --- | --- |
| **Operator manual** (install, init, usage) | [`SPEC-KIT.md`](SPEC-KIT.md) |
| Baseline spec | [`specs/001-baseline/spec.md`](../specs/001-baseline/spec.md) |
| Code inventory (100%) | [`specs/001-baseline/code-inventory.md`](../specs/001-baseline/code-inventory.md) |
| Constitution | [`.specify/memory/constitution.md`](../.specify/memory/constitution.md) |
| Cursor Agent skills | [`.cursor/skills/`](../.cursor/skills/) (`speckit-*`) |

**Quick start (maintainers):**

```bash
# Install Specify CLI (once per machine) — see SPEC-KIT.md
specify init --here --force --integration cursor-agent --script sh
specify integration list   # Cursor → installed (default)
```

In Cursor Agent, start a new feature with `/speckit-specify <description>`. For day-to-day tooling details, skills reference, folder layout, and troubleshooting, read **[`SPEC-KIT.md`](SPEC-KIT.md)**.

---

## See also

- [`SPEC-KIT.md`](SPEC-KIT.md) — GitHub Spec Kit manual (install, structure, usage)
- [`specs/001-baseline/spec.md`](../specs/001-baseline/spec.md)
- [USAGE.md](USAGE.md) — routes and integration
- [CONFIGURATION.md](CONFIGURATION.md) — `nowo_wiki` options
- [examples/AccessControl.md](examples/AccessControl.md) — ACL patterns
- [RELEASE.md](RELEASE.md) — tagging and Packagist
- [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md) — local demo stack
