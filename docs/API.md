# Wiki REST API

A JSON API so that scripts and agents can read and write wikis with a personal token issued by the host (spec 218,
since 1.24). It is off unless `nowo_wiki.api.enabled: true`. With the API off, the bundle registers no route, service,
listener or parameter, and adds no table. The full contract is [`openapi.yaml`](openapi.yaml) (OpenAPI 3.1).
`ApiOpenApiTest` keeps that document in step with the routes and the answers.

## Table of contents

- [Turning it on](#turning-it-on)
- [What a host provides](#what-a-host-provides)
- [Firewall](#firewall)
- [Access](#access)
- [Bodies](#bodies)
- [Endpoints](#endpoints)
- [Saving a page](#saving-a-page)
- [Idempotency](#idempotency)
- [Errors](#errors)
- [Events](#events)
- [Known limits](#known-limits)

## Turning it on

1. Implement the hooks below and name them under `nowo_wiki.api.hooks`. The `authenticator` and the `directory` are
   required. Every other hook has a default, and each default denies.
2. Put the prefix behind a stateless firewall that authenticates the token ([Firewall](#firewall)).
3. Listen to `WikiEvents::API_WRITE`, which is the audit of every write, and to `WikiEvents::QUOTA_CHECK`, which applies
   the plan limits on page create and unarchive. Without an audit listener, every write is refused. Without a quota
   listener, page creates and unarchives are refused, unless you set `api.quota_listener_required: false`.
4. Create the `<table_prefix>_api_idempotency` table (`doctrine:migrations:diff`), unless you set
   `api.hooks.idempotency_store`. Schedule `nowo:wiki:api:purge-idempotency`. Call `forget()` on token revocation and
   on account erasure.

```yaml
nowo_wiki:
    editor: { type: milkdown }          # the reader's Markdown follows the per-viewer render
    api:
        enabled: true
        prefix: /api/wiki/v1
        quota_listener_required: true
        max_body_bytes: 1048576
        search_candidates: 500
        hooks:
            authenticator: App\Api\WikiApiAuthenticator
            directory: App\Wiki\Api\WikiApiDirectory
            rate_limiter: App\Api\SharedRateLimiter
            plan_gate: App\Wiki\Api\WikiApiPlanGate
            users: App\Api\ApiUsers
            wiki_management: App\Wiki\Api\WikiApiManagement
            files: App\Files\Api\WikiApiFiles
            idempotency_store: ~
```

The build refuses `api.enabled` in three cases:
- `security.allow_unauthenticated` is set.
- The access checker is `AllowAllWikiAccessChecker`, however it is wired.
- The page repository does not implement `WikiPageLockInterface`.

## What a host provides

The bundle stores no tokens. Each hook is an interface in `Nowo\WikiBundle\Api\Hook`.

| `api.hooks.*` | Interface | Without it |
|---|---|---|
| `authenticator` | `WikiApiAuthenticatorInterface`: answers a `WikiApiPrincipal` (the user, the level, *where*, the token id and name), or `Invalid`, `ApiDisabled` or `NoWikiLevel` | The container does not build |
| `directory` | `WikiApiDirectoryInterface`: lists and finds the wikis the token may see (a read never creates one) and provisions a wiki on its first write | The container does not build |
| `rate_limiter` | `WikiApiRateLimiterInterface`: one bucket per token, plus a failed-token limit per IP | Every request gets 429 |
| `plan_gate` | `WikiApiPlanGateInterface` | 403 `PLAN_001` |
| `users` | `WikiApiUsersInterface`: the `id` shown for the token's own user | People have no `id` |
| `wiki_management` | `WikiApiWikiManagementInterface`: the owner key of a new wiki, then create; rename, archive and unarchive wikis | 403 `WIKI_001` |
| `files` | `WikiApiFilesInterface`: list, upload and download, with every check of the file | No files (403 `FILE_001`, empty lists) |
| `idempotency_store` | `WikiApiIdempotencyStoreInterface` | The bundle's table |

Every operation is a public service in `Nowo\WikiBundle\Api\Operation`: `WikiApiWikiOperations`,
`WikiApiPageReadOperations`, `WikiApiRevisionOperations`, `WikiApiSearchOperations`,
`WikiApiCommentReadOperations`, `WikiApiPageWriteOperations`, `WikiApiCommentWriteOperations` and
`WikiApiDelegatedOperations`. `WikiApiGuard` is public too. An in-process caller, such as an MCP server, gets the same
checks without making an HTTP call. It must still run on a request behind the firewall, because the per-viewer render
reads the security token.

## Firewall

The API must sit behind a **stateless, secured firewall** whose security user is the token's user. The access checkers
and the per-viewer render read the security token, and `WikiTokenGuard` trusts that token only on a secured firewall.
If the API is mounted on `security: false`, every request answers 500 `SERVER_001` and one error is logged. When a token
is bad, the firewall should let the request through with no user; the bundle then answers, so every refusal has the
same shape. Do not add an `access_control` rule that redirects on the prefix.

```yaml
security:
    firewalls:
        api:
            pattern: ^/api/(tasks|wiki)/
            stateless: true
            custom_authenticators: [App\Api\TokenAuthenticator]
```

## Access

Every request is decided as level ∩ the person's access ∩ *where*, checked again on each request. The checks run in
this order, and the first one that fails answers:

| Check | Answer |
|---|---|
| Out of reach: missing, outside *where*, a page of another wiki, a page or an ancestor hidden, an archived page the token may not see | 404 `ACCESS_001`, always the same body |
| The plan has no wiki module | 403 `PLAN_001` |
| A write on an archived wiki | 403 `WIKI_002` |
| The level may not | 403 `TOKEN_001` |
| The person may not (the space checker and the role gate) | 403 `ACCESS_002` |

**Levels** form a closed table over `WikiAction` (`WikiApiCapability`). A new action is denied until it is added there.

| `WikiAction` | Read | Edit | Full |
|---|---|---|---|
| View, History, ViewComments | yes | yes | yes |
| Create, Edit, Comment, ResolveComment | no | yes | yes |
| Archive | no | own pages | any page |
| Restore, ModerateComments, Manage | no | no | yes |
| Import, Export, AskAi, Delete, Purge | no | no | no |

**Where only narrows.** `WikiApiWhere::everything($exceptOwnerKeys)` gives the person's whole reach.
`WikiApiWhere::limitedTo($ownerKeys, $wikiSlugs)` limits it to the owners and wikis named. A restricted wiki is reached
only when its slug is named. An empty or unreadable scope must be `limitedTo([], [])`.

**Archived pages** need an Edit token, the role gate's `canArchive()`, and `Archive` granted on the page. A Read token
never sees them.

## Bodies

Dates are Unix milliseconds. Ids are UUID strings. Wikis are named by their slug. Lists hold 100 rows per page, start at
`page=0`, and say `last_page`. Writes list ignored fields in `warnings`, each with the reason `unknown` or
`not_supported`.

`content_view` is `source` only when the token's level is Edit or higher **and** the person may edit the page. In every
other case it is `viewer`. The viewer Markdown is built from the reader's own render (`WikiViewerMarkdownView`). Links to
pages the reader may open come back as `[[wiki:<uuid>]]`. Pages the reader may not open, or that lie outside the token's
*where*, read *Content not visible*. Whatever the render hides is absent, such as reference definitions, link targets,
directive attributes, and raw HTML when it is stripped. A page over the renderer's limits gives a fixed placeholder
instead of its text. Comment bodies follow the same rule.

Host files (1.25) go through the host's file resolver with the token's reach. This covers Milkdown host references
and, with Milkdown's `host_url_patterns`, plain URLs such as `/files/<uuid>/original`. A file whose wiki lies outside
*where*, or that the person may not read, reads *Content not visible*, without its uuid, alt text or name. See
[CONFIGURATION](CONFIGURATION.md) (`editor.milkdown.host_files`).

Not covered by the reach (audit IJ-L4, IJ-I7):

- **`content_view: source`** (an Edit-or-higher token whose person may edit the page) is the raw Markdown, with every
  file uuid, including files outside *where*. Editors need the source.
- **Search** matches and quotes `content_text`, the plain text of the viewer-neutral render. For a Markdown page this
  keeps the **text of links and the labels of `::file` cards** (often file names) even when the file is outside the
  token's *where*; uuids, alt texts and URLs are attributes and are not in it. An HTML-format revision's `content_text`
  (returned as `content_text`) is the same plain text. The reader view hides these; search and HTML revisions do not yet.
  Fix planned: build excerpts from a render that refuses host files.

People appear as `{"id", "name"}`. The name is whatever the history policy shows (`WikiAuthorLabel`). The id is filled
only for the token's own user.

Write provenance (spec 234, additive fields; see [USAGE](USAGE.md#member-badges-and-write-provenance)):

- Pages (lists, bodies, write answers) add `created_by` (the author of the page's oldest stored revision, a person like
  `updated_by`), `channel` and `token_name` of the last write.
- Revisions add `channel` and `token_name` of that write (`author` is who made it).
- `channel` is `web`, `app`, `api` or `mcp`, as the host's `WikiWriteProvenanceProviderInterface` recorded it (decided
  by the host from the request path, never from client input; a stored value outside that list reads null). `token_name`
  is the host's actor label: plain text, at most 120 characters, never a secret. Both are null when nothing was
  recorded (no provider, or a write made before the columns existed), and `token_name` is null once its author's
  account is deleted.
- `token_name` is answered only for the token's own writes, or beside an author named by a real name with
  `history.show_actor_labels` on (default `history.show_names`) and a label that passes the safe-name rule. Beside an
  opaque "Editor xxxx" it is null: it would link and name the person (audit PQ-M1).
- Values are plain JSON strings (no HTML; `json_encode` escapes `<` and `>`): escape them when you render HTML.
- A page list reads creators and provenance in two extra queries, whatever the number of rows.

## Endpoints

| Endpoint | Level |
|---|---|
| `GET /wikis`, `GET /wikis/{wiki}` | Read |
| `POST /wikis`, `PATCH /wikis/{wiki}`, `POST /wikis/{wiki}/archive`, `/unarchive` (host hook) | Full |
| `GET /wikis/{wiki}/pages` (`parent`, `archived`, `updated_since`), `GET …/pages/{page}` | Read |
| `POST /wikis/{wiki}/pages`, `PUT …/pages/{page}`, `POST …/move` | Edit |
| `POST …/archive`, `/unarchive` | Edit for your own pages, Full for any page |
| `GET …/revisions`, `GET …/revisions/{number}` | Read |
| `GET …/revisions/{number}/diff?against=` | Edit (source) |
| `POST …/revisions/{number}/restore` | Full |
| `GET /search?q=&wiki=` | Read |
| `GET …/threads`, `GET …/threads/{thread}` | Read |
| `POST …/threads`, `POST …/threads/{thread}/comments`, `PATCH …/threads/{thread}`, `PATCH …/comments/{comment}` (your own) | Edit |
| `DELETE …/comments/{comment}` | Edit for your own comments, Full with `ModerateComments` for anyone's |
| `DELETE …/threads/{thread}` | Full with `ModerateComments` |
| `GET …/pages/{page}/files`, `GET /wikis/{wiki}/files/{file}` (host hook) | Read |
| `POST /wikis/{wiki}/files` (host hook, multipart field `file`) | Edit |

In the table, `…` stands for `/wikis/{wiki}/pages/{page}`.

**Search** covers the wikis in reach and matches titles and plain text, never the Markdown source. Each hit is checked
against the page, its ancestors and its space. Each request reads at most `api.search_candidates` matching rows, and
`truncated` says when that cap stopped it. `total` counts only the hits the reader may read.

**Comments.** New threads are page-level; an `anchor` field is answered with a `not_supported` warning. Other people's
comments are never edited through the API.

**Creating a wiki.** The hook names the new wiki's owner key. If that owner is outside the token's *where*, the answer is
404 and nothing is created. The token that creates a restricted wiki gets it back in the answer. After that, the usual
reach applies: a restricted wiki must be named in *where*.

**Downloads** always leave as an `attachment`, with `X-Content-Type-Options: nosniff` and
`Content-Security-Policy: default-src 'none'; sandbox`.

## Saving a page

`PUT …/pages/{page}` requires `base_revision`, the revision your text started from. The page row is locked in a
transaction and the revision is compared under that lock:

- The page has moved on: 409 `WIKI_REVISION_CONFLICT`, with `current_revision`, `updated_by` and `updated_at`. Nothing
  is written. Read the page again and decide.
- The same title and body: 200 with the current revision. Nothing is added.
- An empty body over a page that has content needs `"allow_empty": true`, otherwise 422 `WIKI_EMPTY_BODY`.
- With co-editing on, if the live session holds updates no revision has, the answer is 409 `WIKI_LIVE_SESSION`.
- A lock that cannot be taken (a deadlock or a lock wait timeout) answers 409 `WIKI_SAVE_COLLISION` with
  `Retry-After: 1`. Nothing is written.

`POST …/revisions/{number}/restore` takes `base_revision` too and always appends a new revision.

## Idempotency

Send `Idempotency-Key` on any write. If you retry with the same key within 24 hours, you get the first successful answer
again, marked `Idempotency-Replayed: true`, and the write does not run twice. Keys are scoped to the token.

| Case | Answer |
|---|---|
| The same key with another request | 409 `IDEMP_002` |
| The same key while the first request is still running | 409 `IDEMP_001`, `Retry-After: 1` |
| A key that is empty or longer than 255 characters | 400 `IDEMP_003` |
| The store fails | 503 `IDEMP_004` |

Refusals and errors are not kept, so a retry is decided afresh. A replay checks the token's reach again.

## Errors

| ECODE | HTTP | When |
|---|---|---|
| `OAUTH_017` / `OAUTH_019` | 401 | No credential / the token is not valid (also when the role gate refuses the person) |
| `API_001` | 404 | The host switched the API off |
| `ACCESS_001` | 404 | Out of reach, or an unknown path under the prefix |
| `ACCESS_002` | 403 | The person may not |
| `TOKEN_001` | 403 | The level may not, or the token has no wiki level |
| `PLAN_001` / `PLAN_002` | 403 | No wiki module / a plan limit (`err` holds the host's reason) |
| `WIKI_001` / `WIKI_002` | 403 | Wikis are not managed through the API / the wiki is archived |
| `WIKI_003` | 409 | Slug or name taken |
| `WIKI_REVISION_CONFLICT`, `WIKI_SAVE_COLLISION`, `WIKI_LIVE_SESSION`, `WIKI_PAGE_ARCHIVED` | 409 | [Saving a page](#saving-a-page) |
| `WIKI_MOVE_001` / `WIKI_MOVE_002` | 409 / 422 | Stale move or cycle / too deep |
| `WIKI_EMPTY_BODY` | 422 | See [Saving a page](#saving-a-page) |
| `WIKI_TOO_LARGE` | 413 | Over the Markdown limits |
| `COMMENT_001` / `COMMENT_002` | 422 / 409 | A comment cap / the thread changed |
| `FILE_001` | 403 | No files hook; the hook may answer its own `FILE_*` codes |
| `INPUT_001` / `INPUT_002` | 400 | Malformed JSON / an invalid field (`err` names it) |
| `INPUT_003` | 413 | Body over `api.max_body_bytes`, or a comment over 10,000 characters |
| `INPUT_004` | 422 | `base_revision` is missing or not an integer |
| `INPUT_005` | 415 | A write body that is not `application/json` |
| `RATE_001` / `RATE_002` / `RATE_003` | 429 | Token limit / failed tokens per IP / the comment limit shared with the UI |
| `IDEMP_001` – `IDEMP_004` | 409 / 409 / 400 / 503 | See [Idempotency](#idempotency) |
| `AUDIT_001` | 503 | No `API_WRITE` listener |
| `SERVER_001` | 500 | Anything else. Only the exception class is logged |

## Events

- `WikiEvents::API_WRITE` (`WikiApiWriteEvent`) fires once per write, after the commit. It carries the principal, the
  operation (such as `page.update`), the ids involved and the names of the changed fields, never page text.
- The usual page and comment events fire as they do in the UI, with the token's user as the actor. Inside an API write
  they fire after the commit. A listener that fails at that point is logged by class; it does not refuse a write that
  has already been stored.

## Known limits

- Inline comment anchors, reactions, import, export, AI, deleting pages or wikis, and access settings are not available
  through the API.
- The SECURITY section [REST API](SECURITY.md#rest-api-124-spec-218) covers how long idempotent answers are kept, what
  replays check, and what `truncated` reveals in search.
