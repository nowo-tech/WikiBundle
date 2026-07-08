# ENGRAM — WikiBundle

Persistent context for agents working on this repository.

## Package

- **Composer:** `nowo-tech/wiki-bundle`
- **Namespace:** `Nowo\WikiBundle`
- **Config alias:** `nowo_wiki`
- **Translation domain:** `NowoWikiBundle`

## Purpose

Versionable team wiki (Outline/Notion-style): spaces, page trees, immutable revisions, diff, search, Tiptap rich text.

## Key extension points

| Interface | Role |
|-----------|------|
| `WikiAccessCheckerInterface` | Feature ACL (list, create, edit, history, archive) |
| `WikiTeamMembershipResolverInterface` | Team-scoped space visibility |
| `WikiHtmlSanitizerInterface` | HTML sanitization on persist (default: `WikiHtmlSanitizer`) |

## Dependencies

- `nowo-tech/tiptap-editor-bundle` (^1.0) — page editor (`WikiPageFormType` → `TiptapEditorType`)
- Doctrine ORM — entities + repositories

## Tests

- PHP: `make test` / `make test-coverage-100` (100% line coverage, 111 tests)
- TS: `make test-ts` (wiki.ts, 100% coverage)
- Demo: `make -C demo/symfony8 up` → http://localhost:8025/tools/wiki

## Security notes

- CSRF on POST (edit, create, archive)
- HTML sanitized before revision persist
- Space access via `WikiSpaceAccessResolver` (no IDOR on foreign spaces)
- See `docs/SECURITY.md`

## MCP / Cursor

Verify `.cursor/mcp.json` and `docs/SPEC-DRIVEN-DEVELOPMENT.md` when changing agent workflows.
