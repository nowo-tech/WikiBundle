# Security

## Table of contents

- [Attack surface](#attack-surface)
- [Threats and mitigations](#threats-and-mitigations)
- [Access control](#access-control)
- [Content safety](#content-safety)
- [Secrets and cryptography](#secrets-and-cryptography)
- [Logging](#logging)
- [Limits and DoS](#limits-and-dos)
- [Dependencies](#dependencies)
- [REQ-SEC-004](#req-sec-004)
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
| **Stored XSS** | Malicious HTML/JS in page body. | `WikiHtmlSanitizer` (symfony/html-sanitizer, per-element attribute allowlist) on every save **and** at render time (`nowo_wiki_safe_html` Twig filter); drops `script`, event handlers, `srcdoc`, `style` (except `text-align`), non http/https/mailto URLs and iframes outside the https embed host allowlist. |
| **IDOR on spaces/pages** | User accesses another team's wiki. | `WikiSpaceAccessResolver` filters by team/user scope; controller resolves space via membership. |
| **Unauthorized edit** | Viewer modifies pages. | `WikiAccessCheckerInterface` on all manage actions (`canEdit`, `canCreate`, etc.). |
| **CSRF on POST** | Forged save/archive requests. | `WikiCsrfTrait` validates tokens on edit, create, archive. |
| **Slug injection** | Path traversal or invalid URLs. | Route requirements `[a-z0-9]+(?:-[a-z0-9]+)*`; `WikiSlugger` validation. |
| **Revision tampering** | Delete or overwrite history. | Revisions are append-only; pages are soft-archived only (`BR-WIKI-002`). |
| **Search abuse** | Expensive LIKE queries. | `setMaxResults(25)` default; scope limited to accessible space. |
| **Malicious import** | ZIP bomb or oversized upload. | `import_export.max_upload_bytes` (default 50 MB); `WikiArchiveHelper` rejects archives with more than 10 000 entries or more than 512 MiB declared uncompressed size before extracting; archive extracted to temp dir; import requires `import_roles`. |
| **AI prompt injection** | User steers agent via page content. | Context limited by `max_context_pages` / `max_context_chars`; restrict `ai_roles`; review agent system prompt. |
| **SSRF / outbound HTTP** | The only outbound call is the host-configured AI platform. | Endpoint comes from host config (`config/packages/ai.yaml`), never from user input; bounded by `nowo_wiki.ai.http_client` (`ai.http_timeout`). |
| **Path traversal on import** | ZIP entries such as `../../etc`. | `WikiArchiveHelper` rejects absolute / `..` entries before extracting into a fresh `0700` temp directory, removed afterwards. |
| **Resource exhaustion** | Slow LLM, huge spaces, long histories. | See [Limits and DoS](#limits-and-dos). |

## Access control

Replace defaults via `nowo_wiki.security.access_checker` and `nowo_wiki.team_membership_resolver`.

- **Team scope:** spaces visible when `owner_scope_id` is in resolver team ids.
- **User scope:** spaces owned by the current user id.
- **Roles:** `ConfigurableWikiAccessChecker` supports admin bypass and per-action role lists.
- **Demo bypass:** `security.allow_unauthenticated: true` wires `AllowAllWikiAccessChecker` (never in production).

Host firewall example:

```yaml
security:
    access_control:
        - { path: ^/tools/wiki, roles: ROLE_USER }
```

See [docs/examples/AccessControl.md](examples/AccessControl.md) and [USAGE.md](USAGE.md).

### Space- and page-aware access (1.9)

`WikiSpaceAccessCheckerInterface` is asked on every route with the resolved space and page. A space or page the user
may not view answers 404 (never 403), so its existence does not leak; a denied write on a readable space answers 403.
Attachment downloads ask `view` on the attachment's **own** page and answer 404 to every denial (missing, other space,
forbidden, archived page without `manage`).

## Content safety

- Attachments (1.9): stored under server-generated keys (`<space id>/<32 hex>`, no user input) in a private
  directory; uploads are checked against the size, the space quota and an extension + `finfo` MIME allowlist (no SVG,
  HTML, XML or scripts) and a scanner hook before anything is stored; downloads send `X-Content-Type-Options: nosniff`,
  `Cache-Control: private, no-store`, `Content-Security-Policy: default-src 'none'; img-src 'self'; sandbox` and
  `Content-Disposition: attachment` except for PNG / JPEG / GIF / WebP. `WikiAttachmentReferenceFilter` removes
  attachment URLs of other spaces from page bodies on save.

- Sanitize **on write** in `WikiPageService` and **again on read**: `manage/page_view.html.twig` renders the body with the `nowo_wiki_safe_html` filter (not `|raw`), so revisions stored before a sanitizer fix are cleaned too. If you override that template, keep the filter.
- `WikiHtmlSanitizer` is built on `symfony/html-sanitizer`: allowlisted elements only, per-element attributes, links http/https/mailto (or relative), media http/https (or relative), `style` only `text-align`, iframes only with an `https` `src` on `WikiHtmlSanitizer::DEFAULT_EMBED_HOSTS` (YouTube, youtube-nocookie, Vimeo). To allow other embed hosts, register `WikiHtmlSanitizer` with a custom `$allowedEmbedHosts` argument (or alias `WikiHtmlSanitizerInterface` to your own service).
- Tiptap variant `notion` is layout only; it does not replace server-side sanitization.
- For high-security deployments, add a Content-Security-Policy header in the application layout override.

## Secrets and cryptography

- The bundle stores no credentials and uses no cryptography of its own. CSRF tokens come from `symfony/security-csrf`; page and space ids are random UUIDs (`ValueObject\Uuid`), not secrets.
- AI provider keys (`OPENAI_API_KEY`, …) live only in the host environment and `config/packages/ai.yaml`; the bundle never reads, stores or logs them.
- Nothing secret is committed: demo `.env` files are git-ignored, `.env.example` holds placeholders only, and the demo MySQL is reachable only on the internal Docker network.
- Exported ZIPs are created with mode `0600` in the system temp dir and deleted after sending.

## Logging

Channel `nowo_wiki` (PSR-3, see [CONFIGURATION.md](CONFIGURATION.md#logging-req-obs-001)):

- **Logged:** start / finish / failure of imports and exports (space slug, format, counters), page archiving (space and page slugs), every AI agent call (space slug, question / answer **length**, number of context pages, exception class).
- **Never logged:** page HTML or Markdown, question / answer text, user ids or e-mails, passwords, session ids, API keys, exception messages (they may embed provider URLs).
- No `dump()`, `var_dump()` or `error_log()` in `src/`.

## Limits and DoS

| Resource | Limit |
| -------- | ----- |
| LLM call | `ai.http_timeout` (default 30 s, timeout + max duration); PHP / Caddy / FrankenPHP layers above it ([timeout hierarchy](CONFIGURATION.md#timeout-hierarchy-req-runtime-001)) |
| Upload | `import_export.max_upload_bytes` (default 50 MB); ZIP: ≤ 10 000 entries, ≤ 512 MiB declared uncompressed size |
| Lists | `pagination.*`: 24 spaces / page, 25 revisions / page, 500 pages in the tree; search capped at 25 hits |
| AI context | `max_context_pages` (8), `max_context_chars` (12 000) |

## Dependencies

- `nowo-tech/tiptap-editor-bundle` (editor widget), `symfony/html-sanitizer` (stored HTML), Doctrine ORM (persistence), `league/commonmark` / `league/html-to-markdown` (interchange); `symfony/ai-bundle` is optional.
- CI runs `composer audit` on every push / PR (job **Composer audit**); Dependabot opens update PRs. Security fixes are released as patch versions as soon as they are available; advisories go through GitHub Security Advisories ([.github/SECURITY.md](../.github/SECURITY.md)).

## REQ-SEC-004

AI audit + remedia **2026-07-29** — grade **Pass (conditional)** / residual risk **Medium** (stored HTML depends on sanitizer allowlist; AI prompt-injection; ZIP import within size cap; host must keep ACL roles tight).

## Reporting

See [.github/SECURITY.md](../.github/SECURITY.md) for coordinated disclosure.

## Release security checklist (12.4.1)

Confirm every item before tagging (note it in the release PR):

| # | Item | How to confirm |
| - | ---- | -------------- |
| 1 | `docs/SECURITY.md` up to date | Threats, limits and logging sections match the release |
| 2 | `.env` ignored | Root and `demo/symfony8/.gitignore` ignore `.env` / `.env.dev`; `git ls-files '*.env'` is empty |
| 3 | No secrets | No keys / passwords in the diff; `.env.example` holds placeholders only |
| 4 | Safe config / recipe | Defaults: `allow_unauthenticated: false`, `import_roles: [ROLE_ADMIN]`, AI disabled |
| 5 | Input / output | HTML sanitized on write and read (tests green); CSRF on every POST; slug route requirements |
| 6 | Dependencies | CI **Composer audit** green on the release SHA |
| 7 | No-secret logs | Log context reviewed against [Logging](#logging) |
| 8 | Cryptography | N/A beyond Symfony CSRF tokens and random UUIDs ([Secrets and cryptography](#secrets-and-cryptography)) |
| 9 | Permissions / exposure | Routes and role lists documented ([Access control](#access-control)); demo auto-login only for local dev |
| 10 | Limits / DoS | [Limits and DoS](#limits-and-dos) values unchanged or documented in CHANGELOG |
| 11 | AI security audit (REQ-SEC-004) | Latest grade recorded in [REQ-SEC-004](#req-sec-004) / `BUNDLES_SECURITY_ANALYSIS.md` is a pass |
