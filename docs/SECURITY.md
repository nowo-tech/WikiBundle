# Security

## Table of contents

- [Attack surface](#attack-surface)
- [Threats and mitigations](#threats-and-mitigations)
- [Access control](#access-control)
- [Content safety](#content-safety)
- [Dependencies](#dependencies)
- [Reporting](#reporting)
- [Release security checklist (12.4.1)](#release-security-checklist-1241)

## Attack surface

| Input | Description |
|-------|-------------|
| **Manage routes** | Authenticated CRUD for spaces, pages, revisions, search (`WikiManageController`). |
| **Import / export** | Multipart ZIP or Markdown tree upload; file export download (`WikiManageController`, console commands). |
| **AI ask** | POST question to `/tools/wiki/ask` when Symfony AI is enabled. |
| **Page HTML** | Rich text from TiptapEditorBundle stored as HTML in `wiki_page_revisions.content_html`. |
| **Search query** | GET `q` parameter; LIKE search on title and content. |
| **Configuration** | `nowo_wiki` YAML (routes, ACL, table prefix, space scope). |

## Threats and mitigations

| Threat | Risk | Mitigation |
|--------|------|------------|
| **Stored XSS** | Malicious HTML/JS in page body. | `WikiHtmlSanitizer` allowlist on every save; strip `script`, event handlers, `javascript:` URLs. |
| **IDOR on spaces/pages** | User accesses another team's wiki. | `WikiSpaceAccessResolver` filters by team/user scope; controller resolves space via membership. |
| **Unauthorized edit** | Viewer modifies pages. | `WikiAccessCheckerInterface` on all manage actions (`canEdit`, `canCreate`, etc.). |
| **CSRF on POST** | Forged save/archive requests. | `WikiCsrfTrait` validates tokens on edit, create, archive. |
| **Slug injection** | Path traversal or invalid URLs. | Route requirements `[a-z0-9]+(?:-[a-z0-9]+)*`; `WikiSlugger` validation. |
| **Revision tampering** | Delete or overwrite history. | Revisions are append-only; pages are soft-archived only (`BR-WIKI-002`). |
| **Search abuse** | Expensive LIKE queries. | `setMaxResults(25)` default; scope limited to accessible space. |
| **Malicious import** | ZIP bomb or oversized upload. | `import_export.max_upload_bytes` (default 50 MB); archive extracted to temp dir; import requires `import_roles`. |
| **AI prompt injection** | User steers agent via page content. | Context limited by `max_context_pages` / `max_context_chars`; restrict `ai_roles`; review agent system prompt. |

## Access control

Replace defaults via `nowo_wiki.security.access_checker` and `nowo_wiki.team_membership_resolver`.

- **Team scope:** spaces visible when `owner_scope_id` is in resolver team ids.
- **User scope:** spaces owned by the current user id.
- **Roles:** `ConfigurableWikiAccessChecker` supports admin bypass and per-action role lists.

See [docs/examples/AccessControl.md](examples/AccessControl.md) and [USAGE.md](USAGE.md).

## Content safety

- Sanitize **on write** in `WikiPageService` (not only on read).
- Tiptap variant `notion` is layout only; it does not replace server-side sanitization.
- For high-security deployments, add a Content-Security-Policy header in the application layout override.

## Dependencies

- `nowo-tech/tiptap-editor-bundle` (editor widget)
- Doctrine ORM (persistence)

Run `composer audit` and Dependabot before releases.

## Reporting

See [.github/SECURITY.md](../.github/SECURITY.md) for coordinated disclosure.

## Release security checklist (12.4.1)

- [ ] No secrets in repo or demo `.env` committed
- [ ] `composer audit` clean
- [ ] ACL and CSRF documented for integrators
- [ ] HTML sanitization covered by tests
- [ ] Demo uses `space_scope: user` with auto-login only for local dev
