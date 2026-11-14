# Configuration — WikiBundle

Root key: `nowo_wiki`.

| Key | Default | Description |
|-----|---------|-------------|
| `user_class` | *(required)* | Application user entity FQCN (the security user may be another class with the same `getId()` when listed in `accepted_user_classes`, 1.27) |
| `accepted_user_classes` | `[]` | 1.27: security-user classes other than `user_class` stored as `user_class` by their `getId()` (wrappers / read models). Empty: only `user_class` instances write and own comments; any other user is refused, so overlapping ids of another identity domain fail closed. Ids ≤ 0 and empty ids are refused |
| `table_prefix` | `wiki_` | DB table prefix |
| `space_scope` | `team` | `team` or `user` |
| `team_membership_resolver` | null | Service id implementing `WikiTeamMembershipResolverInterface` |
| `database.entity_manager` | `default` | Doctrine entity manager used by the wiki repositories and search |
| `database.driver` | `doctrine_orm` | **Deprecated since 1.6, no effect** (only Doctrine ORM is supported); remove it from your config |
| `database.platform` | `mysql` | **Deprecated since 1.6, no effect** (the platform comes from the Doctrine connection); remove it from your config |
| `firewall` | `main` | **Deprecated since 1.6, no effect** (the bundle trusts any secured firewall that handles the wiki routes); remove it from your config |
| `sanitizer.extra_allowed_elements` | `{}` | 1.11: extra elements kept in page bodies, element => its extra attributes (e.g. `{details: [open], summary: []}`); see [Sanitizer allowlist](#sanitizer-allowlist-111) |
| `sanitizer.extra_allowed_attributes` | `[]` | 1.11: attributes kept on every allowed element (e.g. `[data-callout]`). 1.16.2: an `id` listed here (or under `extra_allowed_elements`) has no effect — only the renderer anchors `nowo-h-…` / `nowo-fn-…` are kept, every other id is dropped, and a deprecation is logged at container build; `name` is refused (configuration error) |
| `editor.type` | `tiptap` | `tiptap`: HTML pages edited with TiptapEditorType; `markdown` (1.11): Markdown pages (`content_markdown` is the source, `content_html` its sanitized render cache), see [Markdown pages](#markdown-pages-111); `milkdown` (1.13): Markdown pages in the nowo-tech/milkdown-editor-bundle dialect, see [Milkdown editor](#milkdown-editor-113) |
| `editor.tiptap_config` | `notion` | TiptapEditorBundle config profile name |
| `editor.markdown_form_type` | null | 1.11: form type FQCN of the page body when `editor.type` is `markdown` (e.g. a Markdown editor bundle); null = a plain textarea (`MilkdownEditorType` with `milkdown`) |
| `editor.milkdown.page_resolver` | null | 1.13: service id of the Milkdown `PageResolverInterface` that resolves `[[wiki:<uuid>]]` in wiki pages for the viewer; null = `WikiMilkdownPageResolver` (pages the viewer may read, `page_view` URLs) |
| `security.access_checker` | null | Custom `WikiAccessCheckerInterface` service id |
| `security.access_roles` | `[ROLE_USER]` | General gate of the wiki (REQ-UI-002); an empty list grants nothing through it |
| `security.roles.<action>` | see [Roles per action](#roles-per-action-req-ui-003) | Roles per action (`admin`, `list`, `create`, `edit`, `history`, `archive`, `ai`, `import`, `export`); `null` = the deprecated `<action>_roles` key, else the default |
| `security.<action>_roles` | null | **Deprecated since 1.10**: use `security.roles.<action>` |
| `security.allow_unauthenticated` | `false` | DEV/DEMO only — wires AllowAll checker; requires SecurityBundle when `false` |
| `routes.*` | see defaults | Path and route name per action |
| `route_prefix` | null | **Deprecated since 1.10**: use `web_ui.path_prefix` |
| `templates.*` | null | **Deprecated since 1.10**: use `web_ui.layout_template` and `web_ui.templates.<page>` |
| `ai.enabled` | `false` | Enable Symfony AI assistant (`composer require symfony/ai-bundle`) |
| `ai.agent` | `wiki_assistant` | Agent name from `config/packages/ai.yaml` (service `ai.agent.{name}`) |
| `ai.context_injection` | `true` | Inject wiki search hits into the system prompt |
| `ai.max_context_pages` | `8` | Max pages in injected context |
| `ai.max_context_chars` | `12000` | Max characters of wiki context |
| `ai.system_prompt` | null | Optional override of the default system prompt |
| `ai.http_timeout` | `30` | Timeout and max duration (seconds, 1–600) of `nowo_wiki.ai.http_client`; set it as `http_client` of your AI platform |
| `import_export.enabled` | `true` | Enable import/export UI and console commands |
| `import_export.max_upload_bytes` | `52428800` | Max upload size for space import (bytes) |
| `import_export.max_document_bytes` | `8388608` | Largest Markdown document an import accepts (bytes, 1024 to 536870912). A ZIP entry that declares more is refused before anything is extracted, one that holds more while it is written, and a file over it in a directory source (`wiki:import`) before anything is written. It bounds one document, not the sum: the sum is checked against `memory_limit`, see [SECURITY](SECURITY.md) |
| `web_ui.enabled` | `true` | `false`: the `nowo_wiki` route loader registers no route |
| `web_ui.path_prefix` | null | Prefix of every wiki route path (e.g. `/admin`); null = the deprecated `route_prefix`, else none |
| `web_ui.layout_template` | null | Layout the pages extend; null = `nowo_ui_kit.layouts.admin` (see [TEMPLATES](TEMPLATES.md#layout)) |
| `web_ui.templates.<page>` | null | Per-page template override (`index`, `space`, `page_view`, `page_edit`, `page_history`, `page_diff`, `search`, `ai_ask`); null = the bundle page |
| `web_ui.tab_icons.<tab>` | `page`: `tabler:file-text`, `edit`: `tabler:pencil`, `history`: `tabler:history` | 1.18: icon of each page tab (Page / Edit / History) passed to the UiKit `tabs` partial, so the row follows the host's tab style (text, icon and text, icon only); null or `''` = no icon on that tab; a name must be `[prefix:]name` in lowercase letters, digits and single hyphens (checked at container build) |
| `web_ui.css_framework` | null | **Deprecated since 1.10**: set `nowo_ui_kit.css_framework` once for every bundle (when set, it only seeds `nowo_ui_kit.css_framework` / `icon_set` if the host left them unset) |
| `space_icon.allowed_prefixes` | `[tabler]` | 1.32: icon set prefixes accepted for `WikiSpace::icon` (`<prefix>:<name>`, each prefix `[a-z0-9-]+`); the setter and `WikiSpaceIconType` reject any other |
| `pagination.spaces_per_page` | `24` | Space cards per wiki index page (`?page=N`, 1–500) |
| `pagination.revisions_per_page` | `25` | Revisions per page history page (`?page=N`, 1–500) |
| `pagination.max_tree_pages` | `500` | Pages loaded into the sidebar tree (10–10000); a notice links to search when more exist |

## Table of contents

- [Markdown pages (1.11)](#markdown-pages-111)
- [Milkdown editor (1.13)](#milkdown-editor-113)
- [Page tree (1.14)](#page-tree-114)
- [Trash (1.14)](#trash-114)
- [Page files (1.20)](#page-files-120)
- [History timeline (1.15)](#history-timeline-115)
- [Comments (1.16)](#comments-116)
- [Avatars (1.16)](#avatars-116)
- [Search text (1.16)](#search-text-116)
- [Sanitizer allowlist (1.11)](#sanitizer-allowlist-111)
- [Space access and tenant paths (1.9)](#space-access-and-tenant-paths-19)
- [Attachments (1.9)](#attachments-19)
- [Web UI (REQ-UI-001 / REQ-UI-003)](#web-ui-req-ui-001--req-ui-003)
- [Host hooks (1.21)](#host-hooks-121)
- [Page layout (1.29)](#page-layout-129)
- [REST API (1.24)](#rest-api-124)
- [Roles per action (REQ-UI-003)](#roles-per-action-req-ui-003)
- [Pagination (REQ-PERF-001)](#pagination-req-perf-001)
- [Import / export](#import--export)
- [Symfony AI integration](#symfony-ai-integration)
- [Logging (REQ-OBS-001)](#logging-req-obs-001)

## Markdown pages (1.11)

```yaml
nowo_wiki:
    editor:
        type: markdown                       # default: tiptap
        markdown_form_type: null             # e.g. App\Form\MarkdownEditorType; null = textarea
```

- Revisions store `format` (`html` | `markdown`); with `markdown` the Markdown (`content_markdown`) is the source and `content_html` the render cache (sanitized). HTML revisions stay readable and are converted when edited, or in bulk with `bin/console wiki:convert-markdown` ([USAGE](USAGE.md#markdown-pages-111)).
- The renderer is `Nowo\WikiBundle\Markdown\WikiMarkdownRendererInterface` (default `WikiMarkdownRenderer`: CommonMark + GFM, raw HTML escaped, unsafe links dropped). Add syntax with services implementing `WikiMarkdownExtensionInterface` (autoconfigured, tag `nowo_wiki.markdown_extension`), or replace the alias.
- A host `markdown_form_type` receives the editor options (`upload_url`, `upload_csrf_token` when attachments are enabled, plus whatever `WikiEvents::EDITOR_OPTIONS` listeners add): it must accept them, or a listener removes them.
- Schema: see [UPGRADING](UPGRADING.md#to-1110) (two columns on `<prefix>_page_revisions`).

## Milkdown editor (1.13)

```bash
composer require nowo-tech/milkdown-editor-bundle   # ^1.2, register NowoMilkdownEditorBundle
```

```yaml
nowo_wiki:
    editor:
        type: milkdown                       # default: tiptap
        markdown_form_type: null             # null = Nowo\MilkdownEditorBundle\Form\MilkdownEditorType
        milkdown:
            page_resolver: null              # null = WikiMilkdownPageResolver

nowo_milkdown_editor:
    markdown:                                # the limits and safety settings wiki pages are rendered with
        max_input_length: 100000
        max_inline_markers: 10000
        max_references: 500
        icon_sprite_url: '/build/tabler.svg'
        icons: [home, bell, alert-triangle]
```

- Everything of `markdown` applies (Markdown source, `content_html` render cache, `wiki:convert-markdown`), plus the **Milkdown ↔ wiki bridge**: `WikiMarkdownRendererInterface` is `MilkdownWikiMarkdownRenderer` (CommonMark + GFM + the Milkdown dialect + your `nowo_wiki.markdown_extension` services) with the `nowo_milkdown_editor.markdown` options: `html_input` (`escape` / `strip`), `allow_remote_images`, `math`, `max_nesting_level`, `max_input_length`, `max_inline_markers`, `max_references`, `mark_colors`. Unsafe links are always dropped. See [USAGE](USAGE.md#milkdown-editor-113).
- The sanitizer keeps the dialect markup on top of your `sanitizer` lists: `details` (`open`) / `summary`, and `role`, `aria-label`, `data-math`, `data-directive`, `data-icon` on every allowed element (`WikiExtension::MILKDOWN_SANITIZER_ELEMENTS` / `MILKDOWN_SANITIZER_ATTRIBUTES`). SVG is never allowed: icons are drawn after sanitizing from the icon allowlist.
- `nowo_milkdown_editor.markdown.icons` / `icon_sprite_url` decide which `:tabler-<name>:` icons are drawn; mentions are resolved by the Milkdown `UserResolverInterface` alias (yours) and host leaf directives by `DirectiveRendererInterface` services.
- The container refuses to compile when the package is missing or `NowoMilkdownEditorBundle` is not registered.

### Collaboration (1.13)

| Key | Default | Meaning |
|-----|---------|---------|
| `collaboration.enabled` | `false` | Co-editing of Milkdown pages over Mercure (needs `editor.type: milkdown`, symfony/mercure-bundle unless `publisher` and `subscriber_authorizer` are yours) |
| `collaboration.hub_url` | `/.well-known/mercure` | Hub URL the browser subscribes to (same registrable domain as the app, for the cookie) |
| `collaboration.topic_prefix` | `urn:nowo-wiki:page:` | Topic of a page = prefix + page id |
| `collaboration.max_update_bytes` | 262144 | Largest decoded update / presence message (413 above) |
| `collaboration.max_snapshot_bytes` | 4194304 | Largest decoded compaction snapshot |
| `collaboration.max_log_bytes` | 16777216 | Byte budget of a page's stored log (snapshot + updates); over it the log is dropped and editors reload (409 `log_reset`) |
| `collaboration.compact_after` | 200 | Ask clients to compact once the log holds this many updates |
| `collaboration.max_log_entries` | 2000 | Refuse updates (429 `log_full`) above this until a client compacts |
| `collaboration.seed_timeout` | 30 | Seconds after which an unfulfilled seed claim may be taken again |
| `collaboration.corrupt_resets` / `corrupt_window` | 3 / 600 | Resets clients may trigger by reporting a log they cannot apply, per page and window (seconds) |
| `collaboration.reset_after_idle` | 300 | Idle seconds after which a log not matching the current revision is dropped |
| `collaboration.rate_limiter` | null | **Required** with `enabled`: service id of a `RateLimiterFactory` per user and page, applied to every request (token, state, update, awareness, compact) |
| `collaboration.update_rate_limiter` / `awareness_rate_limiter` | null | 1.14.1: per user and page budgets for `update` and `awareness` requests instead of `rate_limiter` (e.g. `limiter.wiki_collab_update`, `limiter.wiki_collab_awareness`) |
| `collaboration.user_rate_limiter` | null | 1.13.1 (CW-I9): service id of a `RateLimiterFactory` per **user** across all pages (optional, on top of `rate_limiter`) |
| `collaboration.save_conflict` | `true` | A page form saved outside the live session (hub down, editor standalone or offline), or a restore, while the shared log holds updates no version has: `true` saves nothing, answers 409 and keeps the text in the form, with the live version and "Replace for everyone" (any editor; the same right as "Reset collaboration"). `false`: saves and drops the log, so those updates are lost. It also never asks about the log's name: a save from a replaced log wins (an update from one is still refused) |
| `collaboration.show_names` | `false` | 1.13.1 (CW-I2): co-editors see display names instead of "Editor xxxx" (never an identifier or e-mail-shaped value) |
| `collaboration.store` / `publisher` / `subscriber_authorizer` | null | Your services instead of the Doctrine store and the Mercure defaults |
| `collaboration.mercure_hub` | null | Mercure hub name of the defaults |
| `routes.page_collab` | `…/pages/{pageSlug}/collab/{action}` | The collaboration endpoints (`action`: token, state, update, awareness, compact, corrupt, reset) |

Schema: `<prefix>_page_collab_documents` and `<prefix>_page_collab_updates`, part of the generated schema only while `collaboration.enabled` is true (1.13.1; see [UPGRADING](UPGRADING.md#to-1131)).


**Host files (1.25).** `editor.milkdown.host_files` decides whether the per-viewer render (page body, comments, the API
reader view) resolves host file references through Milkdown's `HostFileResolverInterface`, with the render's
`WikiRenderFrame` (space, page, reach) as context:

- `auto` (default): only when `nowo_milkdown_editor.markdown.host_url_patterns` is set.
- `on`: also with `host_schemes` alone.
- `off`: never, as in 1.24.

A refused file reads *Content not visible*, without URL, alt text or name. Under the API's reach, a resolver that is
not a `ContextualHostFileResolverInterface` resolves nothing. The neutral render (`content_html`) never resolves host
files. See [UPGRADING](UPGRADING.md#to-1260).

**File cards (1.25, WK-8).** `editor.milkdown.file_cards` decides how a `::file[name]{href size type}` card that no host
`DirectiveRendererInterface` supports reads in the per-viewer render (page body, comments, the API reader view):

- `link` (default): a plain link to the card's `href` with its label, as in 1.24.
- `card`: Milkdown's own file card (badge, name, size; `link`, `icon` and `card` views; 1.28: preview frames, see below), linked to the
  URL Milkdown's host file resolution gave it (see `host_files`) and only if that URL also passes the wiki link policy.
  A refused file reads *Content not visible*, without uuid, name or link. In the API reader view a card reads
  `[name](url)`.

The neutral render (`content_html`) keeps the link either way.

**File previews (1.28, Symfony Beacon spec 212 block B).** `editor.milkdown.file_previews` (default `true`) applies only
with `file_cards: card`: a `view="preview"` card frames the preview URL the host's Milkdown `FilePreviewResolverInterface`
gives it, called inside the render's `WikiRenderFrame` (under the API's reach, only for host-reference cards). The frame
uses Milkdown's `file_preview_sandbox`, `loading="lazy"` and `referrerpolicy="no-referrer"`, the URL must pass the wiki
link policy, and it reaches the page through `nowo_wiki_safe_html` only (a per-request placeholder survives the
sanitizer; see [SECURITY](SECURITY.md)). Milkdown's default resolver answers only for `preview` templates, so without a
host resolver or a template nothing changes. The page's CSP needs `frame-src` for the preview origin. `false`: preview
cards read as plain cards (as 1.27).

```yaml
nowo_wiki:
    editor:
        type: milkdown
        milkdown:
            file_cards: card
            file_previews: true   # 1.28, default
```

`card` hides nothing by itself (audit IK-I4): a refused file reads *Content not visible* only while host files are
resolved (`host_files: auto` with Milkdown's `host_url_patterns` set, or `on`); otherwise the card links its written
`href` like `link`. With a `url: resolved` pattern or a scheme value, a visible card shows the resolver's URL, name and
type (IK-I5). Before replacing a host `file` renderer with `card`, see [UPGRADING](UPGRADING.md#to-1260): Milkdown
≥ 1.14 and every link host in `host_url_hosts` (IK-L1).

**Copy as Markdown (1.15).** `editor.milkdown.copy_markdown: true` (default `false`, needs Milkdown ≥ 1.5) adds a "Copy
as Markdown" button to the page view, for users who may edit the page only. It copies the stored Markdown, raw: page
ids of `[[wiki:<id>]]` links (also of pages the reader may not see), mention handles and directive attributes. That
is why readers never get it (Milkdown audit DN-I2). A page view that renders the button answers
`Cache-Control: private, no-store`, so no shared cache (reverse proxy, CDN) keeps the source and serves it to another
user (audit DR-I1). If you cache wiki pages in front of Symfony, keep that header.
## Page tree (1.14)

```yaml
nowo_wiki:
    tree:
        enabled: true          # literal boolean; off = the 1.13 nested list, no tree routes
        max_depth: 10          # deepest level after a move (root pages are level 1)
        state_store: null      # service id of a WikiTreeStateStoreInterface (null: the session)
```

| Key | Default | Meaning |
|-----|---------|---------|
| `tree.enabled` | `false` | UiKit tree in the sidebar with fold state and moves (drag, Alt+arrows, Move form) |
| `tree.max_depth` | 10 | A move that would put a page deeper is refused (`too_deep`) |
| `tree.state_store` | null | Where the expanded pages of each user and space are kept (default: the session, 500 ids per space) |
| `routes.page_move` | `…/pages/{pageSlug}/move` | The Move form (GET, POST + CSRF) |
| `routes.space_tree` | `…/spaces/{spaceSlug}/tree/{action}` | JSON endpoints `move` and `state` (POST, same origin, CSRF per space) |
| `web_ui.templates.page_move` | null | Template of the Move form |

Who may move: the role gate `canEdit()` and `WikiAction::Edit` on the page, on its current parent and on the new parent
(the space for the top level); a page or parent the user may not read **with every ancestor** answers 404 (as the
sidebar hides a whole hidden branch). Positions count the siblings the user can see. A page moves with its whole
sub-tree, including descendants the actor cannot see: their readers become those of the new branch (as in Outline and
Notion). The tree is only movable for users with
`Edit` on the space.

Decisions (2026-11-03, see [ROADMAP › Decided](ROADMAP.md#decided)): pages move within their space only (D-W1); the
depth limit defaults to 10 (D-W2); sibling order uses sparse integers in the existing `position` column (D-W27, no
schema change); the fold state lives in the session unless `tree.state_store` names a per-user store (D-W28).

## Trash (1.14)

```yaml
nowo_wiki:
    trash:
        enabled: true          # literal boolean; off = no Delete action, no trash routes, no trash table
        retention_days: 30     # wiki:trash:purge deletes older deletions for good (D-W4)
```

| Key | Default | Meaning |
|-----|---------|---------|
| `trash.enabled` | `false` | *Delete* moves a page and its sub-pages to the trash (restore together, purge for good) |
| `trash.retention_days` | 30 | Age at which `wiki:trash:purge` removes a deletion |
| `routes.page_delete` | `…/pages/{pageSlug}/delete` | *Delete* (POST + CSRF, behind a confirm) |
| `routes.space_trash` | `…/spaces/{spaceSlug}/trash` | The trash list |
| `routes.trash_restore` | `…/trash/{batchId}/restore` | *Restore* (POST + CSRF) |
| `routes.trash_purge` | `…/trash/{batchId}/purge` | GET: the confirmation page; POST: purge (CSRF, behind a second confirm) |
| `web_ui.templates.trash` / `trash_purge` | null | Templates of the two pages |

Who may: the role gate `canArchive()`, then `WikiAction::Delete` for *Delete*, the trash list and *Restore*, and
`WikiAction::Purge` for *Delete for good* (the 1.x mapping: archive roles delete, import / manage roles purge, D-W5).
A purge is irreversible: when the deletion holds pages the user may not read, it also needs `WikiAction::Manage` on the
space (otherwise a neutral notice says so). The trash list counts only the pages the user may read.
Only deletions whose page the user may read with every ancestor are listed; on the purge page, pages the user may not
read are counted, not named. Schedule the retention, e.g. with cron: `0 3 * * * bin/console wiki:trash:purge`.

Decisions (2026-11-03, see [ROADMAP › Decided](ROADMAP.md#decided)): a delete takes the page and its whole sub-tree,
and a restore brings them back together (D-W29); pages archived before the delete stay archived (D-W30); a restored
page whose parent is gone meanwhile comes back at the top level (D-W31).

## Page files (1.20)

The files a page refers to: Milkdown 1.8 file cards `::file[name]{href="…" size="…" type="…"}` (uploads that are not
images, MilkdownEditorBundle feature `file_cards`) and images `![alt](url)`. The host owns their storage (its
`upload_url`); the bundle only reads the Markdown.

```yaml
nowo_wiki:
    page_files:
        enabled: false        # a "Files" card in the page view (current revision, everyone who may read the page)
        purge_event: false    # nowo_wiki.page.files_purged when the trash purges pages for good (needs trash.enabled)
        max_files: 200        # files listed at most per page (and per revision for the event)
        max_purge_files: 5000 # files one purge event carries at most (then truncated: true and a warning)
```

| Key | Default | Meaning |
|-----|---------|---------|
| `page_files.enabled` | `false` | The page view lists the files of the current revision (variable `page_files`, list of `PageFile\WikiPageFile` with `url`, `name`, `kind` = `file` / `image`, `size`, `type`, `sizeLabel()`) |
| `page_files.purge_event` | `false` | After a purge commits, dispatch `WikiEvents::PAGE_FILES_PURGED` (`WikiPageFilesPurgedEvent`: `space`, `batchId`, `pageIds`, `files`, `actor`) with the files of **every revision** of the purged pages, once each |
| `page_files.max_files` | 200 | Cap per page / revision (1–1000) |
| `page_files.max_purge_files` | 5000 | Cap of distinct files per purge event (1–100000); beyond it the event has `truncated: true` and a warning is logged |

Only relative and http(s) URLs are listed (no `javascript:`, `data:` or `//host`); names lose control and invisible
format characters and are cut at 200 characters; code blocks, code spans and HTML comments are ignored; HTML
revisions list nothing. The event is not sent on *Delete* (a restore needs the files) nor when the purged pages
referred to no file; it is sent after the purge committed, also when a `nowo_wiki.trash.purged` listener throws.

The list is **best effort** (a pattern match, not a CommonMark parser): cards on their own line, also in block quotes
and list items; a repeated attribute keeps its last value, as the renderer does; reference-style images
`![alt][ref]` and `<img>` in raw HTML are not listed. Keep `page_files.enabled` off if a host
`DirectiveRendererInterface` renders `::file` differently from Milkdown (e.g. hides some files from some readers):
the list reads the Markdown, not the host's rendering.

**`files` are untrusted, editor-supplied URLs.** Any editor of a purged page could have written any URL into one of
its revisions — including a file of another space or user. Map URLs to your files by **lookup** (never build a path
from a URL: relative URLs may contain `..`), delete only files recorded for **this space / these pages** at upload
time, and skip files something else still refers to:

```php
// A host listener (Beacon owns the storage of its upload endpoint)
#[AsEventListener(WikiEvents::PAGE_FILES_PURGED)]
public function onFilesPurged(WikiPageFilesPurgedEvent $event): void
{
    foreach ($event->files as $file) {
        $stored = $this->files->findOneByPublicUrl($file->url);   // lookup only: no path built from the URL
        if ($stored === null
            || $stored->getSpaceId() !== $event->space->getId()     // ownership recorded at upload time
            || !\in_array($stored->getPageId(), $event->pageIds, true)
            || $this->references->isReferencedElsewhere($stored, $event->pageIds)) {
            continue;
        }
        $this->storage->delete($stored);
    }
    if ($event->truncated) {
        $this->logger->notice('Wiki purge reported only part of its files; run the orphan sweep.');
    }
}
```

## History timeline (1.15)

```yaml
nowo_wiki:
    history:
        timeline: true         # literal boolean; off = the 1.14 revision list, no named-version routes or table
        session_minutes: 30    # saves by the same author less than this apart form one session
        show_names: false      # true = display names that say nothing about the login, else "Editor xxxx"
        show_actor_labels: ~   # spec 234: token names beside real names only (null = follow show_names)
        retention_days: 30     # wiki:history:thin thins older revisions to one per session and day (D-W6)
```

| Key | Default | Meaning |
|-----|---------|---------|
| `history.timeline` | `false` | Version name, author and session columns; *Save version*; *Copy to new page* |
| `history.session_minutes` | 30 | Gap (1–1440 minutes) that ends a session of saves by one author |
| `history.show_names` | `false` | Show safe display names (`getDisplayName()` / `getName()`, the co-editing rule) instead of opaque labels |
| `history.show_actor_labels` | `null` (= `show_names`) | Spec 234 (audit PQ-M1): show a write's actor label (token name) in the history and the API `token_name`, only beside an author shown under a real name and only when it passes the same safe-name rule; never beside an opaque "Editor xxxx". The author always sees their own labels |
| `history.retention_days` | 30 | Age at which `wiki:history:thin` thins revisions |
| `routes.page_version_save` | `…/pages/{pageSlug}/versions/new` | *Save version* (GET form, POST + CSRF) |
| `routes.page_version_copy` | `…/pages/{pageSlug}/revisions/{revisionNumber}/copy` | *Copy to new page* (POST + CSRF) |
| `web_ui.templates.page_version` | null | Template of the *Save version* form |

Who may: *Save version* needs the role gate `canEdit()` and `WikiAction::Edit` on the page (D-W7: every editor);
*Copy to new page* needs `WikiAction::History` on the page, `canCreate()` and `WikiAction::Create` on the space.
The copy is a new **top-level** page (title "<title> (vN)" and slug "<slug>-vN", cut to the column limits), so it
takes the space's visibility: content from a branch with restricted rights becomes visible to everyone who may read
the space's top level. That is by design (audit DT-I1); restrict `Create` or move the copy if it must stay private. Both
answer 404 for a page the user may not read with every ancestor.

Authors are labelled per page with an HMAC of `kernel.secret` (the co-editing labels): the same person has the same
label on one page and unrelated labels on other pages, and a label cannot be matched against a user directory. With
`show_names`, a display name that contains the login, its local part or an e-mail address falls back to the label.

**Retention.** `wiki:history:thin` (add `--dry-run` to count only) removes, for every page, the revisions older than
`retention_days` except the newest of each session and day. A session ends when the author changes, after
`session_minutes` without a save, at midnight (the stored time) and at a named version (a session of its own). Named
versions, the current revision and the newest revision are always kept. Revision numbers are never reused, so a thinned
history has gaps. Run it from a single scheduler, e.g. `30 3 * * * bin/console wiki:history:thin`. Without the
timeline the command does nothing.

## Comments (1.16)

```yaml
framework:
    rate_limiter:
        wiki_comments: { policy: sliding_window, limit: 60, interval: '1 minute' }

nowo_wiki:
    editor:
        type: milkdown                         # required (nowo-tech/milkdown-editor-bundle ^1.6)
    comments:
        enabled: true                          # literal boolean; off = no toggle, panel, route or comment table
        rate_limiter: limiter.wiki_comments    # required when enabled: comment writes per user, across all pages
        page_rate_limiter: null                # optional: a second budget per user and page
        limits:                                # 422 limit_reached beyond these
            threads_per_page: 500              # open threads (resolved ones do not count, 1.16.1)
            user_threads_per_page: 50          # 1.16.1: open threads one user starts on one page
            total_threads_per_page: 1000       # 1.16.1: every thread of a page, resolved ones included
            comments_per_thread: 500
            reactions_per_comment: 20          # distinct emoji on one comment
            user_reactions_per_comment: 10
        state_store: null                      # a WikiPanelStateStoreInterface service id (null: the session)
        read_view_source: false                # true: non-editors get the page Markdown on the read view (see below)
        shortcut: null                         # e.g. 'Alt+Shift+C' (aria-keyshortcuts); none by default
```

| Key | Default | Meaning |
|-----|---------|---------|
| `comments.enabled` | `false` | The comments toggle and panel, the endpoints and the three comment tables |
| `comments.rate_limiter` | null (required when on) | `RateLimiterFactory` service id, keyed by **user identifier, across all pages**, on every write (429) |
| `comments.page_rate_limiter` | null | Optional `RateLimiterFactory` service id keyed by user identifier + page id, on top of `rate_limiter` |
| `comments.limits.*` | 500 / 50 / 1000 / 500 / 20 / 10 | Open threads per page, open threads one user starts on one page (1.16.1), every thread of a page including resolved ones (1.16.1), comments per thread, distinct emoji per comment, reactions of one user per comment (422 `limit_reached` beyond) |
| `comments.state_store` | null | Service id of a `WikiPanelStateStoreInterface` (open / closed per user); the session by default |
| `comments.read_view_source` | `false` | Literal boolean. `false`: on the read view only editors get the read-only Milkdown mount (inline highlights) and so the page source; other commenters get the threads rendered by the server. `true`: every viewer with `ViewComments` gets the mount **and the Markdown source** ([SECURITY](SECURITY.md#comments-threat-model-116)) |
| `comments.moderation_panel` | `false` | 1.21, literal boolean. `true`: users with `ModerateComments` get *Moderate threads* in the panel: the threads of the page (at most the 50 of `GET …/threads`), each with *Delete thread* behind a confirm dialog (`DELETE …/threads/{id}`). The endpoint exists either way |
| `comments.error_notices` | `false` | 1.21. `true`: a write refused with 422 or 429 shows its own text (a UiKit toast when the toast script is loaded, else a notice in the panel). The Milkdown event only carries the status, so every 422 reads as `wiki.comments.error.limit_reached` |
| `comments.dock_min_width` | `md` | 1.29: `md` (768 px, as before), `lg` (1200 px) or `xl` (1440 px) — from this width the panel docks as a column, below it is an overlay drawer (UiKit `drawer_below`). A remembered "open" panel starts closed under `lg` / `xl`; `?comments=1` still opens it ([Page layout](#page-layout-129)) |
| `comments.shortcut` | null | Modifiers + one key (`Control`, `Alt`, `Shift`, `Meta`), e.g. `Alt+Shift+C` |
| `routes.page_comments` | `…/pages/{pageSlug}/comments/{path}` | The thread component's endpoints (a custom path must end with `/{path}`) |
| `routes.space_panel_state` | `…/spaces/{spaceSlug}/panel-state` | `POST {panel: "comments", open}` (204); 1.29 also `{panel: "tree_rail", open}` with `web_ui.tree_rail` (registered with either feature) |

The tab row (*Page* / *Edit* / *History*) is rendered with or without comments. The toggle and the panel appear only
for viewers with `WikiAction::ViewComments`. **Rights** are default deny: the host's `WikiSpaceAccessCheckerInterface`
grants `ViewComments`, `Comment`, `ResolveComment` and `ModerateComments` (Beacon maps them to its View, Edit, manage
and manage permissions); the 1.x legacy mapping denies them all. Every endpoint also applies the read rule (View on the
page and every ancestor): an unreadable page answers 404, a missing right 403.

Tables (`<prefix>` = `table_prefix` without its trailing `_`): `<prefix>_comment_threads` (page, opaque anchor JSON,
quote copy, resolved by / at, created by / at, updated at), `<prefix>_comments` (thread, author, Markdown body,
position, created / edited / deleted at), `<prefix>_comment_reactions` (comment, user, emoji). Threads are deleted with
their page (purge) and hidden with it (archive, trash). A deleted user's comments stay, shown as *Former person* (1.26; as do their page revisions).

## Avatars (1.16)

```yaml
# config/services.yaml
services:
    Nowo\WikiBundle\Avatar\WikiUserAvatarResolverInterface: '@App\Wiki\WikiAvatarUrls'
```

`WikiUserAvatarResolverInterface::urlsFor(list<UserInterface>, int $size): array<string, ?string>` is called once per
response, keyed by `WikiAvatarKey::of($user)` (`getId()` as a string, else the user identifier), with `$size` 32 (CSS
pixels). Return a stable, access-checked URL: `https://` without userinfo, or a same-origin path starting with one `/`
(the UiKit avatar URL policy drops anything else, and initials are shown). The default
(`NullWikiUserAvatarResolver`) returns none. **Privacy:** pictures follow the names — only authors shown under their
real name (`history.show_names: true`, and a name that passes the `isSafeName()` rule) are ever passed to the
resolver; opaque "Editor xxxx" authors get no avatar and no resolution.

## Search text (1.16)

The search matches the revision's `content_text` (plain text of the sanitized HTML, filled on save). Fill it for
revisions written before 1.16 with `bin/console wiki:search:backfill-text` (`--dry-run` counts, `--batch-size=N`,
`--all` recomputes every revision). `content_text` is public API: filter on it in SQL, and use
`Nowo\WikiBundle\Search\WikiPlainText::fromMarkdown()` for text you only have as Markdown.

## Sanitizer allowlist (1.11)

1.31: the built-in allowlist keeps the disabled task checkbox of MilkdownEditorBundle >= 1.17
(`li.nowo-milkdown-task > input.nowo-milkdown-task__box`): an `<input>` survives only as the first child of a
`li.nowo-milkdown-task`, rebuilt as `type="checkbox" class="nowo-milkdown-task__box" disabled` plus `checked` and
`aria-label` when present (`WikiHtmlSanitizer::TASK_BOX_ATTRIBUTES`); any other `<input>`, and any other attribute
(`on*`, `name`, `value`, `form`, `formaction`, `src`, `style`), is removed. `input` stays forbidden in
`extra_allowed_elements`.
HTML revisions (authored HTML) also lose the renderers' reference classes (`nowo-md-ref`, `nowo-md-ref--*`) on save, so
only rendered Markdown carries the hidden-reference marker that plain text and search drop (audit NB-W-1).

Page bodies (HTML revisions and the render cache of Markdown revisions) go through `WikiHtmlSanitizer`. An editor dialect that emits more markup declares it here:

```yaml
nowo_wiki:
    sanitizer:
        extra_allowed_elements:
            details: [open]
            summary: []
        extra_allowed_attributes: [data-callout, data-align]
```

- Names must be plain element / attribute names. The container refuses to compile with `script`, `style`, `iframe`, `object`, `embed`, forms and form controls, `svg`, `math`, `template`, `link`, `meta`, `base`…, with the attributes `style`, `href`, `src`, `srcset`, `srcdoc`, `action`, `formaction`… or any `on*` handler (`WikiHtmlSanitizer::FORBIDDEN_ELEMENTS` / `FORBIDDEN_ATTRIBUTES`). `class` is already allowed on every element.
- A blocked-by-default element (e.g. `section`) listed here is kept.
- 1.16.2: `name` is refused too (it clobbers `document.<name>` and form controls). `id` is accepted but **has no effect**: the sanitizer keeps an `id` only when it is a Markdown renderer anchor — `nowo-h-<slug>` on `h1`…`h6`, `nowo-fn-<8 hex>-<n>` on `li`, `nowo-fn-ref-<8 hex>-<n>[__<k>]` on `sup` — and drops every other id on every element (DOM clobbering of globals, `Object.prototype` and host element ids). Listing `id` logs a deprecation at container build; remove it.

## Space access and tenant paths (1.9)

```yaml
nowo_wiki:
    # Copies of every space-scoped route; the first tenant whose slug_requirement matches a space slug owns its URLs.
    tenant_paths:
        - { path: '/projects/{spaceSlug}/wiki', name_prefix: 'app_project_', slug_requirement: '[0-9a-f-]{36}' }
        - { path: '/wiki/{spaceSlug}', name_prefix: 'app_', slug_requirement: 'general|user-[0-9a-f-]{36}' }
    tenant_only: true   # leave out /tools/wiki/spaces/{spaceSlug}/… (index, search and ask stay; 1.12: the search keeps its space, no "All spaces" picker)
```

```yaml
# config/services.yaml — decide per space / page and create spaces on the first visit
services:
    Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface: '@App\Wiki\Security\MyWikiAccessChecker'
    Nowo\WikiBundle\Service\WikiSpaceLocatorInterface: '@App\Wiki\Tenancy\MySpaceLocator'
```

## Attachments (1.9)

```yaml
nowo_wiki:
    attachments:
        enabled: true
        storage_dir: '%kernel.project_dir%/var/wiki-attachments'   # private; never under public/
        # storage: App\Storage\MyWikiStorage          # a WikiAttachmentStorageInterface service
        # scanner: App\Security\MyAntivirusScanner     # a WikiAttachmentScannerInterface service
        max_file_bytes: 10485760
        max_space_bytes: 524288000
        # allowed_types: { png: [image/png], pdf: [application/pdf], … }   # extension => sniffed MIME types
```

Limits per space can come from host settings: alias `WikiAttachmentLimitsProviderInterface` to your service.

## Web UI (REQ-UI-001 / REQ-UI-003)

Since 1.10 every wiki page extends a [UiKitBundle](https://github.com/nowo-tech/UiKitBundle) page scaffold (list, show, form): the host sets its layout and theme **once** in UiKit and every bundle follows. Page → scaffold, view models and data blocks: [TEMPLATES.md](TEMPLATES.md).

```yaml
# config/packages/nowo_ui_kit.yaml — once for every UiKit bundle
nowo_ui_kit:
    css_framework: bootstrap5        # or tailwind | foundation | custom | none
    icon_set: bootstrap-icons
    layouts:
        admin: 'layouts/admin.html.twig'   # the project layout (or a bridge) rendering the UiKit canonical blocks
```

```yaml
# config/packages/nowo_wiki.yaml — all optional
nowo_wiki:
    web_ui:
        enabled: true
        path_prefix: null                  # e.g. /admin
        layout_template: null              # wiki-only layout override; null = nowo_ui_kit.layouts.admin
        templates:
            page_view: 'wiki/page_view.html.twig'   # thin override: {% extends '@NowoWikiBundle/manage/page_view.html.twig' %} + one block
        tab_icons:                         # 1.18: page tab icons; ~ or '' = no icon on that tab
            page: 'tabler:file-text'
            edit: 'tabler:pencil'
            history: 'tabler:history'
```

- **Layout:** a host layout that renders the canonical blocks (`nowo_ui_document_title`, `nowo_ui_styles`, `nowo_ui_page_header`, `nowo_ui_flashes`, `nowo_ui_content`, `nowo_ui_modals`, `nowo_ui_scripts`) is used as is — the reference is a one-file bridge that `{% use '@NowoUiKit/layout/_bridge.html.twig' %}` (see `demo/symfony8/templates/layouts/admin.html.twig`). A pre-1.10 layout that only renders `{% block body %}` keeps working through a **deprecated** bridge (removed in 2.0).
- **CSS stack:** the pages emit `nowo-ui-*` classes plus the theme slots of `nowo_ui_kit.theme` (Bootstrap 5, Tailwind, Foundation, `custom` / `none` = semantic classes only); the bundle loads no framework and no CDN. Switch the stack with `nowo_ui_kit.css_framework`, re-skin with `nowo_ui_kit.theme` (classes, attributes, partials, `--nowo-ui-*` tokens).
- **Assets:** each page stacks `{{ parent() }}` + `wiki.css` (asset package `nowo_wiki`) in `nowo_ui_styles`; the page editor adds the TiptapEditorBundle script in `nowo_ui_scripts` (with the request attribute `csp_nonce` when set). `wiki.css` only lays out the wiki-specific regions (tree, two columns, page body, diff) with the `--nowo-ui-*` tokens.
- **Form fields:** the form types carry no framework class; the host form theme (`twig.form_themes`, e.g. `bootstrap_5_layout.html.twig`) or the FormKit `wiki` profile styles them.
- **Dialogs:** the AI question of the index is a UiKit `<dialog>` (`data-nowo-ui-dialog-open`); Archive and Restore go through the UiKit confirm `<dialog>` (POST + CSRF). Load the UiKit runtime scripts in the host layout (`{{ include(nowo_ui_partial('scripts')) }}`, done by the UiKit layouts and the bridge).
- **Flashes:** messages are translated in the `NowoWikiBundle` domain when they are added, so the layout's `nowo_ui_flashes` prints them as given.
- **CSRF (fail closed):** with `web_ui.enabled: true` the container refuses to compile without `framework.csrf_protection` / `framework.form.csrf_protection` (`symfony/security-csrf`).

## Host hooks (1.21)

Options for a host that keeps its own shell around the wiki. Every default is the 1.20 behaviour.

```yaml
# config/packages/nowo_wiki.yaml
nowo_wiki:
    web_ui:
        space_index: true          # false: the index route is not registered (404, no breadcrumb link); literal boolean
        import_export: page        # dialog: one "Import & export" header action opens a dialog with both
        assets: true               # false, or {styles: false} / {scripts: false}
        slots:                     # host templates, included with the page variables; null = the bundle part
            sidebar: null          # the page tree card (space home, page view)
            space_intro: null      # the search card (space home)
            page_header_extra: null  # after the page header, before the tab row (page view)
            page_header_toolbar: null  # 1.30: host buttons in the page header toolbar (page view, edit form)
    search:
        scope: all                 # space: a request without a valid ?space= is 404
```

| Key | Default | Meaning |
|-----|---------|---------|
| `web_ui.space_index` | `true` | `false`: `routes.index` is unset, so `/tools/wiki` is 404 and the breadcrumbs lose its link. For hosts whose spaces live under their own pages (`tenant_paths`). Literal boolean |
| `search.excluded_spaces` | `[]` | With `search.scope: space` (anything else is a compile error): these exact space slugs have no search page (404) |
| `search.scope` | `all` | `space`: `WikiSearchScopeListener` (`kernel.request`, priority 30, registered only with this value) answers 404 without a valid `?space=` and sets the request attribute `spaceSlug`, for host listeners, tabs and breadcrumbs. Access to the space is still checked by the controller. The search page has no space choice and the index search form no "all spaces" choice |
| `web_ui.import_export` | `page` | `dialog`: the exports leave the header and the import card leaves the page, into `manage/_import_export_dialog.html.twig` (UiKit `dialog`; users who may neither import nor export get neither the action nor the dialog) |
| `web_ui.assets` | `true` | `false` or `{styles: false, scripts: false}`: the `nowo_ui_styles` / `nowo_ui_scripts` blocks of every page print nothing. That also leaves out what the scaffold prints there: the UiKit stylesheet and tokens, and the UiKit runtime scripts. A host bundling the assets loads `src/Resources/assets/src/wiki.css`, `wiki.ts` and the UiKit equivalents itself. Form widgets print their own assets, which this does not remove: on the page view and edit with Milkdown, `milkdown-editor.js` and its CSS stay, so a host bundling Milkdown as well loads it twice. Literal booleans (an env placeholder counts as `true`) |
| `web_ui.slots.*` | null | A template name; the bundle part is not drawn. The same places are the blocks `wiki_sidebar`, `wiki_space_intro`, `wiki_page_header_extra` and (1.30) `wiki_page_header_toolbar`, for a `web_ui.templates.<page>` that extends the bundle page. Slot templates are trusted host code; escape what they print |

### Quota event

`nowo_wiki.quota.check` (`WikiEvents::QUOTA_CHECK`, `WikiQuotaCheckEvent`) runs **before** a metered write, after the
access and CSRF checks. `deny(reason)` refuses it and stops the other listeners. Without a listener nothing changes.

| `action` | When | `count` | `bytes` | Refusal |
|----------|------|---------|---------|---------|
| `page_create` | New page; *Copy to new page* of a version | 1 | 0 | Error flash on the form, 422 (the copy: error flash, redirect to the history) |
| `page_unarchive` | An archived page comes back | 1 | 0 | Error flash, redirect to the space |
| `trash_restore` | A deletion comes back from the trash | Pages of the deletion | 0 | Error flash, redirect to the trash |
| `import` | An import archive | Markdown documents of the archive | Archive size | Error flash, redirect to the space; nothing is written |
| `attachment_upload` | A file attached to a page | 1 | File size | 422 `{"error": "quota_exceeded", "reason": "…"}` |

The event also has `space`, `user` and `page` (the page concerned, null for a new page or an import). `reason` is plain
text: control and format characters are removed (invalid UTF-8 is scrubbed), it is cut at 500 characters and escaped
wherever it is shown; an empty one becomes `wiki.quota.denied`. A listener that throws fails the request.

An import's `count` is an upper bound of the new pages: a document that later updates an existing page, is skipped or
fails still counts. The archive is read for it only when a listener exists.

Limits: the check and the write are not one transaction, so a strict quota must also be enforced where the host counts
(two parallel requests can both pass). `wiki:import` and direct calls to `WikiPageService` do not dispatch the event.
**Not metered:** page edits (a body can grow), revision restore, comments and co-editing updates. `page_create` reports
`bytes: 0` and an import the compressed size, so the event is no hook for a quota on page bytes.

### Tree tokens

`wiki.css` reads these on `.nowo-wiki-tree` (set them on `.nowo-wiki-sidebar` or any ancestor); the defaults are the 1.20
values. They style the nested list; `tree.enabled` draws the UiKit `tree` partial, which has its own tokens.

| Token | Default |
|-------|---------|
| `--nowo-wiki-tree-gap` | `0` |
| `--nowo-wiki-tree-indent` | `0.85rem` |
| `--nowo-wiki-tree-guide` | `var(--nowo-ui-border, #e2e8f0)` |
| `--nowo-wiki-tree-link-padding` | `0.25rem 0.5rem` |
| `--nowo-wiki-tree-font-size` / `--nowo-wiki-tree-line-height` | `inherit` |
| `--nowo-wiki-tree-radius` | `var(--nowo-ui-radius, 0.375rem)` |
| `--nowo-wiki-tree-hover-bg` | `var(--nowo-ui-surface-muted, #f6f8fb)` |
| `--nowo-wiki-tree-current-bg` | `var(--nowo-ui-surface-muted, #f6f8fb)` |
| `--nowo-wiki-tree-current-fg` | `var(--nowo-ui-primary, #2563eb)` |

## Page layout (1.29)

Options of the page view (and, for the header and the comments dock, the edit form and the space home). Every default
keeps the 1.28 layout. They need UiKit ^2.48 (required by the bundle).

```yaml
nowo_wiki:
    web_ui:
        tree_rail: false               # literal boolean: a collapse button folds the page tree card to a thin rail
        narrow_tree_position: before   # after: under 62rem the page tree follows the page (page view)
        reading_width: full            # readable: the main column centred at --nowo-wiki-reading-width
        tree_collapsed_mode: rail      # 1.30 hidden: a collapsed tree disappears; its expand button is in the toolbar
        reading_align: center          # 1.30 start: the readable column next to the tree
        read_view:
            seamless_swap: false       # 1.30 true: the read-only editor swaps in with no reflow and the same typography
        page_header:
            layout: stacked            # compact: tabs and actions (split, comments toggle at the end) on one row
            title: both                # dedupe: a first body <h1> equal to the title is not shown twice
                                       # 1.30 body: the header leaves its title (and subtitle) to that <h1>
                                       # 1.31 card: never in the header; the card prints it when the body has none
            tabs: row                  # 1.30 toolbar: Edit / History (Back to page) as toolbar icon buttons
            toolbar_position: header   # 1.30 card: the toolbar sticky at the top right of the content
            path: none                 # 1.30 card: Space › ancestors above the body (hidden ones as "…")
            edit_toolbar:
                current_tab: shown     # 1.33 hidden: the edit form leaves its own Edit tab / button out
                back: none             # 1.33 view: a "View page" eye button on the edit form
        edit_view:
            layout: form               # 1.33 read: the edit form in the page view's layout (tree, card, path)
        new_page_dialog: false         # 1.35 true: "New page" dialog + the page tree's "New page above / below / as child" menu
        children: never                # 1.30 auto / always: "N subpages" chip + "Pages in this section"
        breadcrumb_current_label: false  # 1.30: BreadcrumbKit >= 2.17 names its current crumb after the page
        page_meta: scaffold            # 1.31 card: "v3 · Updated …" (reader's locale) as the card's footer line
    comments:
        dock_min_width: md             # lg / xl: dock from 1200 / 1440 px, a drawer below
        layout: inline                 # 1.31 overlay: a drawer at every width, starting closed
        thread_list: plain             # 1.31 uikit: UiKit rows, plural count badges, UiKit checkbox
```

| Key | Default | Meaning |
|-----|---------|---------|
| `web_ui.tree_rail` | `false` | Literal boolean. The tree card (space home, page view) gets the UiKit `rail_toggle`; collapsed it is `--nowo-ui-rail-width` (2.75rem) wide with "Pages" written vertically. Remembered per user in the panel state store (`comments.state_store`, default the session; `WikiPanelStateStoreInterface::TREE_RAIL`, "open" = collapsed), `?tree=rail` / `?tree=full` for one request. Without a session: `localStorage` (`nowo-ui-rail:wiki-tree`). Not applied when `web_ui.slots.sidebar` replaces the card |
| `web_ui.narrow_tree_position` | `before` | `after`: one column (under 62rem), the page first, then its tree (page view). Visual order only (CSS `order`): the DOM / focus order stays tree first (audit MA-I2) |
| `web_ui.reading_width` | `full` | `readable`: the page view's main column is at most `--nowo-wiki-reading-width` wide (default `var(--nowo-ui-reading-width, 75ch)`), centred |
| `web_ui.page_header.layout` | `stacked` | `compact`: UiKit `tabs_inline` on the read and edit views; the read view's actions use the `split` layout; the comments toggle ends the row; no separate tab row |
| `web_ui.page_header.title` | `both` | `dedupe`: on the read view, a leading body `<h1>` whose normalised text equals the title is dropped (server body; its `id`s stay on empty anchors) or hidden (read-only Milkdown view); a heading with an image, media or math is kept |
| `web_ui.tree_collapsed_mode` | `rail` | 1.30, with `tree_rail` on the page view. `hidden`: a collapsed tree is not drawn (`display: none`, the main column takes the width) and its expand button (UiKit `rail_toggle`, `data-testid="wiki-tree-toggle"`) is in the page toolbar before the comments toggle (the tab row with the stacked header), visible only while the tree is collapsed. Same state store and `?tree=` as the rail. The space home keeps the rail (it has no toolbar) |
| `web_ui.reading_align` | `center` | 1.30, with `reading_width: readable`. `start`: the column at the start of the main area (next to the tree), same maximum width |
| `web_ui.page_header.title` (1.30) | | `body`: when the body starts with an `<h1>` equal to the title (the `dedupe` match), the page view header uses the UiKit compact presentation: no visible title, no subtitle, no breadcrumbs or back link; the title stays a visually hidden `<h1>`, and tabs, actions and toolbar stay. The body (server and read-only editor) keeps its heading. Otherwise the header is unchanged |
| `web_ui.page_header.tabs` | `row` | 1.30. `toolbar`: no tabs. Page view: Edit (pencil) and History (clock) icon buttons, toolbar order tree toggle, Edit, History, host buttons, comments, actions / "more". Edit form and history: "Back to page", Edit, History; the current one has `aria-current="page"`. Implies the one-row header (whatever `layout` is). Icons follow `web_ui.tab_icons` when the UiKit icon set can draw them |
| `web_ui.page_header.toolbar_position` | `header` | 1.30, page view. `card`: the toolbar (tree toggle, tab buttons with `tabs: toolbar`, host buttons, comments toggle, actions) leaves the header for the top right of the content, sticky (`role="group"` named "Page actions": its buttons stay in the normal tab order). The header keeps the title and the tabs (unless `tabs: toolbar`); with `title: body` and `tabs: toolbar` it is not printed at all (the body's `<h1>` is the page's only one). Tokens: `--nowo-wiki-card-toolbar-top` (0.5rem: the sticky offset; a host with a sticky top bar sets it to that bar's height plus a gap, e.g. `calc(var(--app-navbar-height) + 0.5rem)`), `--nowo-wiki-card-toolbar-inset` (0.75rem); wiki.js sets `--nowo-wiki-card-toolbar-width` (12rem without it) and `--nowo-wiki-card-toolbar-height` (3rem without it). Focusable elements in the content get `scroll-margin-block-start` = top + height + inset, so a focused link scrolled into view is never under the toolbar (WCAG 2.4.11). Known limit (audit MG-1-I4): `scroll-margin` only acts when the browser scrolls; an element that receives focus while already in view but under the sticky toolbar (focus moved by script, or the page scrolled by hand) is not moved. A host that needs it can add a `focusin` listener on `[data-nowo-wiki-card-frame]` that calls `window.scrollBy()` by the overlap with `[data-nowo-wiki-card-toolbar]` |
| `web_ui.read_view.seamless_swap` | `false` | 1.30, literal boolean, page view with the read-only editor (commenters with the page source). The editor and the server body share one grid cell (`.nowo-wiki-read-swap`, `data-nowo-wiki-read-swap="pending|ready"`), the editor in the same UiKit `card` / `card_body` box, invisible until ready, then cross-faded (`--nowo-wiki-read-swap-duration`, 150ms; none with reduced motion). Both bodies use the `--nowo-wiki-read-*` typography tokens below; every other child of the box (the server body, also a host `wiki_page_body` without `data-nowo-wiki-server-body`) becomes `inert` / `aria-hidden` and is faded out after the swap, and focus inside it first moves to the same link in the editor, else to the view (`tabindex="-1"`). Audit MG-3-I1: the swap is now invisible, so a difference between the server render and the editor render (a Markdown feature one of them draws differently, a host style that only targets one body) no longer shows as a style change: what readers see after the fade is the editor's version; keep the read tokens mapped and compare both renders when you change the body styles. MG-3-I2: without JavaScript the editor never mounts and the box keeps the server body (plus at most the height of the editor's raw textarea, invisible). Off: the 1.29 swap (hidden body, shown editor) |
| `web_ui.page_header.path` | `none` | 1.30, page view. `card`: the in-wiki path above the body (`data-testid="wiki-page-path"`): the space, then the ancestors, each a link (an archived ancestor is plain text). More than three levels: the middle ones in a "…" disclosure menu. Under 30rem only "‹ Parent". An ancestor the viewer may not read renders "…" (hidden text "Restricted page"), without link, title or name. Tokens `--nowo-wiki-path-separator` ("›"), `--nowo-wiki-path-item-max` (14rem), `--nowo-wiki-path-font-size` |
| `web_ui.children` | `never` | 1.30, page view. The direct sub-pages the viewer may read, not archived, in tree order: a "N subpages" chip above the body (disclosure with the list) and a "Pages in this section" card after the body. `always`: both; `auto`: only while the tree is collapsed (`tree_rail`) or under 62rem. Usually one query (`WikiPageChildrenFinderInterface::findActiveChildren(parent, limit, offset)`, refreshed rows; batches of `max_tree_pages` + 1 until enough readable ones), at most 3 batches (`WikiManageController::CHILDREN_MAX_BATCHES`) over a total order (position, title, id), ids deduplicated; a batch with no new page or the batch cap marks the list truncated), else `findActiveBySpace()` bounded like the tree. At most `pagination.max_tree_pages` readable pages; more shows as "N+ subpages". Audit MG-2-I4: without the finder, only the sub-pages among the space's first `max_tree_pages` + 1 active pages are listed (then marked truncated); a host page repository should implement `WikiPageChildrenFinderInterface` |
| `web_ui.breadcrumb_current_label` | `false` | 1.30, literal boolean. With `nowo-tech/breadcrumb-kit-bundle` >= 2.17: the page view calls `BreadcrumbCurrentLabelInterface::setCurrentLabel(<page title>)` (BreadcrumbKit scopes the label to the current route and its parameters; outside a routed request it is a no-op; safe in worker mode). 1.31: the edit, history, compare (`page_diff`) and move pages name their page crumb after the page too: once the viewer passed the page's checks the controller puts the title on the request (attribute `_nowo_wiki_crumb_page_title`), and a `BreadcrumbTrailBuiltEvent` listener (`Ui\WikiBreadcrumbParentLabel`, registered only with the option and BreadcrumbKit) renames the non-current crumb whose URL path is that page's view; nothing is kept in a service. 1.33 (fix): the controller also puts the route key of the action on the request (`_nowo_wiki_crumb_route_key`, the `nowo_wiki_route` option the route loader now sets on every route), so tenant copies of the routes (`tenant_paths.name_prefix`, e.g. `app_project_nowo_wiki_page_edit`) are recognised and the page view URL is generated with the same prefix: "Project › Wiki › <title> › Edit" instead of a generic "Page" Without BreadcrumbKit or with an older one: nothing. No `conflict`: the integration is optional |
| `web_ui.page_header.title` (1.31) | | `card`: the page view header never shows the title (the `body` header). When the body does not start with the title `<h1>`, the title is printed in the content card after the path / sub-pages head and before the body (`.nowo-wiki-page-title`, `data-testid="wiki-page-title"`, `--nowo-wiki-read-h1-size`, heading weight / line height / font tokens, `--nowo-wiki-page-title-gap` 0.75rem below). It is an `<h1>` when the header is not printed (`toolbar_position: card` + `tabs: toolbar`, no status), else a `<p aria-hidden="true">` beside the header's visually hidden `<h1>` (one `<h1>` per page either way) |
| `web_ui.page_meta` | `scaffold` | 1.31, page view. `card`: no scaffold meta list; "v3 · Updated <date>" as the muted footer line of the content (`.nowo-wiki-page-meta`, `data-testid="wiki-page-meta"`, inside `.nowo-wiki-card-frame` with `toolbar_position: card`, else after the body), `<time datetime="ISO 8601">`. The date is `IntlDateFormatter` MEDIUM date + SHORT time in the request locale and the date's time zone (Twig filter `nowo_wiki_datetime(locale)`); without ext-intl, or for a locale the polyfill cannot format, `Y-m-d H:i`. Keys `wiki.page.meta_revision`, `wiki.page.meta_updated`; token `--nowo-wiki-page-meta-font-size` |
| `web_ui.edit_view.layout` | `form` | 1.33, edit form of an existing page. `read`: the page view's layout around the form (`_read_scaffold.html.twig`, shared with the page view): the tree aside (`wiki_sidebar` block / `slots.sidebar`; with `tree_rail` its fold state, remembered as on the page view, and with `tree_collapsed_mode: hidden` its expand button in the toolbar), the main column (`reading_width` / `reading_align`), the card frame and its sticky toolbar with `page_header.toolbar_position: card` (tab buttons with `tabs: toolbar`, host buttons, comments toggle, Reset collaboration), and the page head with the in-wiki path (`page_header.path: card`). The form (title + editor, Save / Cancel) takes the place of the page body: block `wiki_page_form_body` inside `.nowo-wiki-edit-body` (`data-testid="wiki-edit-body"`), which gives the editor the read typography (`--nowo-wiki-read-*` tokens). The controller then also passes `tree`, `tree_truncated`, `max_tree_pages`, `tree_search_href`, `tree_ui`, `tree_rail`, `space_icon` and `page_path` (the page view's values). The new-page form keeps the form layout. `form`: the 1.32 edit form |
| `web_ui.new_page_dialog` | `false` | 1.35, literal boolean. `true`: the page view gets a "New page" action in its header actions (`create`, `data-testid="wiki-new-page"`) that opens a dialog (`<dialog id="nowo-wiki-new-page-dialog">`, UiKit `dialog` partial, wide) with the new-page form (`WikiPageFormType` with `placement: true`: title, editor, hidden `parent` / `position`; CSRF token `wiki_new_<space id>`; posted to `page_new`). With `tree.enabled` the page tree of the page view and the space home gets a menu on each row (right click, the row's «…» button, the ContextMenu key or Shift+F10): "New page above", "New page below", "New page as child", each opening the dialog with the place filled in. Only users with the create right on the space (role gate `canCreate()` and `WikiAction::Create`) get the dialog and the menu; "as child" needs `WikiAction::Edit` on the row's page, "above" / "below" the same on its parent (the top level: the create right only); the server checks every create again. The new-page form then reads `parent` / `position` (also `?parent=&position=` on its own page). The space home's "New page" stays a link. `false`: no dialog, no menu, no new fields: the 1.34 pages |
| `web_ui.page_header.edit_toolbar.current_tab` | `shown` | 1.33, edit form. `hidden`: the Edit tab (tab row, compact header) or Edit button (`tabs: toolbar`) is left out while editing; Page / History stay |
| `web_ui.page_header.edit_toolbar.back` | `none` | 1.33, edit form. `view`: a "View page" icon button (UiKit `view` icon, an eye; `wiki.action.view_page`, `data-testid="wiki-edit-view-page"`) opens the header actions, linking to the page view. With `tabs: toolbar` it replaces "Back to page" in the toolbar (`data-testid="wiki-toolbar-view"`). `none`: as in 1.32 |
| `web_ui.slots.page_header_toolbar` | null | 1.30. Template of host buttons for the page header toolbar (page view, edit form; only pages with tabs). Same place as the block `wiki_page_header_toolbar` |
| `comments.dock_min_width` | `md` | See [Comments](#comments-116) |
| `comments.layout` | `inline` | 1.31. `overlay`: the panel is UiKit's side panel with `layout: overlay` — a drawer over the page at every width (`nowo-ui-side-panel--overlay`), the text keeps its width. It starts closed: the remembered open state (`state_store`, `localStorage`) is not applied and the drawer's open / close is not stored; `?comments=1` renders it open (notification links, the reload of a server-rendered list). `dock_min_width` has no effect |
| `comments.thread_list` | `plain` | 1.31. `uikit`: the comments mount carries `data-nowo-wiki-thread-list` (UiKit `list` / `list_item` macro classes, `badge_neutral`, `check`, `check_input`, the translated labels) and wiki.js decorates the Milkdown thread component's list on every redraw: list rows, the count as a badge with the plural form of `wiki.comments.list.count_one|many|other` (`#` = the number; `Intl.PluralRules` of the page `lang`, `other` when a category has no form), a UiKit checkbox for "Show resolved", and `wiki.comments.list.thread` instead of the component's "Open thread" |

Read typography tokens (1.30, `read_view.seamless_swap`; 1.33 also the editor of `edit_view.layout: read`, in
`.nowo-wiki-edit-body`; set them on `.nowo-wiki-read-swap`, `.nowo-wiki-edit-body` or an ancestor — they
style the server body and the read-only editor alike, so match them to your Markdown typography instead of styling
the body with your own class): `--nowo-wiki-read-font-family` (inherit), `--nowo-wiki-read-font-size` (inherit),
`--nowo-wiki-read-line-height` (1.7), `--nowo-wiki-read-color` (inherit), `--nowo-wiki-read-h1-size` (2em),
`--nowo-wiki-read-h2-size` (1.5em), `--nowo-wiki-read-h3-size` (1.25em), `--nowo-wiki-read-heading-line-height` (1.25),
`--nowo-wiki-read-heading-weight` (600), `--nowo-wiki-read-heading-font-family` (inherit),
`--nowo-wiki-read-heading-margin` (1.5em 0 0.5em), `--nowo-wiki-read-block-margin` (0 0 1em: p, lists, pre,
blockquote, tables), `--nowo-wiki-read-list-indent` (1.5em), `--nowo-wiki-read-list-item-margin` (0.25em 0),
`--nowo-wiki-read-code-font-family` (ui-monospace…), `--nowo-wiki-read-code-font-size` (0.875em),
`--nowo-wiki-read-code-padding` (0.1em 0.3em), `--nowo-wiki-read-code-color` (inherit),
`--nowo-wiki-read-code-background` (`--nowo-ui-surface-muted`), `--nowo-wiki-read-code-radius` (`--nowo-ui-radius`),
`--nowo-wiki-read-pre-padding` (0.75rem 1rem), `--nowo-wiki-read-link-color` (`--nowo-ui-primary`),
`--nowo-wiki-read-blockquote-padding` (0.25rem 1rem), `--nowo-wiki-read-blockquote-border` (3px solid
`--nowo-ui-border-strong`), `--nowo-wiki-read-blockquote-color` (`--nowo-ui-muted`), `--nowo-wiki-read-table-width`
(auto), `--nowo-wiki-read-cell-padding` (0.4rem 0.6rem), `--nowo-wiki-read-swap-duration` (150ms). 1.31:
`--nowo-wiki-read-h4-size` (1em), `--nowo-wiki-read-h5-size` (0.875em), `--nowo-wiki-read-h6-size` (0.85em) (h4–h6 also
take the heading margin, weight, line height and font); callouts (`.nowo-md-callout`, `--{type}`, `__icon`, `__body`,
the server render and the read-only editor of MilkdownEditorBundle >= 1.17): `--nowo-wiki-read-callout-margin` (the
block margin), `--nowo-wiki-read-callout-padding` (0.5rem 0.75rem), `--nowo-wiki-read-callout-radius`
(`--nowo-ui-radius`), `--nowo-wiki-read-callout-gap` (0.5rem, icon / body), `--nowo-wiki-read-callout-icon-size`
(1.25em); the body's first and last blocks have no outer margin. A leading `.ProseMirror-widget` (Crepe) is not a block:
it has no margin and the first block after it is the one without a top margin (1.31 fix).

Tokens: `--nowo-wiki-reading-width` (reading column), 1.30 `--nowo-wiki-card-toolbar-top` / `-inset` (card toolbar), UiKit `--nowo-ui-rail-width` (rail), `--nowo-ui-reading-width`,
`--nowo-ui-side-panel-width` (comments panel, 22rem).

Always on since 1.29: the main column never grows past its track (long code lines and wide tables scroll inside it),
the body is UiKit rendered content (`data-nowo-ui-rendered-content`: tables as wide as their content, at most the
column, each in a labelled scroll box that keeps the table semantics) and the read view loads UiKit `js/nowo-ui-scroll-region.js` (keyboard access to overflowing blocks). See
[UPGRADING](UPGRADING.md#to-1290).

## REST API (1.24)

`nowo_wiki.api` (off by default) turns on the JSON API of spec 218 under `api.prefix` (`/api/wiki/v1`). With it off,
nothing is registered. See [API.md](API.md) for the hooks, the firewall, the levels and the errors.

| Key | Default | Meaning |
|---|---|---|
| `api.enabled` | `false` | Literal switch; needs `hooks.authenticator` and `hooks.directory` |
| `api.prefix` | `/api/wiki/v1` | Base path; not affected by `web_ui.path_prefix` or `tenant_paths` |
| `api.quota_listener_required` | `true` | Page creates and unarchives are refused unless a `nowo_wiki.quota.check` listener exists |
| `api.max_body_bytes` | `1048576` | Largest JSON body (413 above) |
| `api.search_candidates` | `500` | Rows a search reads at most before filtering |
| `api.hooks.*` | `~` | `authenticator`, `directory`, `rate_limiter`, `plan_gate`, `users`, `wiki_management`, `files`, `idempotency_store` |

## Roles per action (REQ-UI-003)

```yaml
nowo_wiki:
    security:
        access_roles: [ROLE_USER]      # general gate
        roles:
            admin: [ROLE_ADMIN]        # every action
            list: [ROLE_USER]          # index, space, page view, search
            create: [ROLE_USER]
            edit: [ROLE_USER]          # also attachment upload
            history: [ROLE_USER]       # history and diff (restore needs edit + history)
            archive: [ROLE_USER]       # archive and unarchive
            ai: [ROLE_USER]
            import: [ROLE_ADMIN]
            export: [ROLE_USER]
```

An empty list grants that action to nobody but the `admin` roles. When `access_roles` and every list are empty (and there is no `access_checker` and `allow_unauthenticated` is false), the container refuses to compile. The 1.x keys `security.admin_roles`, `list_roles`, `create_roles`, `edit_roles`, `history_roles`, `archive_roles`, `ai_roles`, `import_roles`, `export_roles` still work (deprecated since 1.10, removed in 2.0); `security.roles.<action>` wins when both are set. Space- and page-level decisions stay with `WikiSpaceAccessCheckerInterface` (1.9).

## Pagination (REQ-PERF-001)

```yaml
nowo_wiki:
    pagination:
        spaces_per_page: 24
        revisions_per_page: 25
        max_tree_pages: 500
```

Details per page in [USAGE.md](USAGE.md#pagination-and-list-limits-req-perf-001).

## Import / export

Supports Outline and Notion Markdown directory trees and ZIP archives. Disable entirely with `import_export.enabled: false` if your app does not need interchange.

See [USAGE.md](USAGE.md) for routes and console commands.

## Symfony AI integration

1. `composer require symfony/ai-bundle`
2. Configure `config/packages/ai.yaml` with a platform (OpenAI, Ollama, etc.) and agent `wiki_assistant`
3. Enable in `nowo_wiki`:

```yaml
nowo_wiki:
    ai:
        enabled: true
        agent: wiki_assistant
```

To bound LLM calls (important with FrankenPHP workers), set `http_client: 'nowo_wiki.ai.http_client'` on the platform in `config/packages/ai.yaml`; its timeout comes from `ai.http_timeout`.

The bundle registers `Nowo\WikiBundle\Ai\Tool\WikiKnowledgeSearchTool` for agent toolboxes. Add it under `agent.wiki_assistant.tools` or rely on `context_injection` for one-shot answers.

See `demo/symfony8/config/packages/ai.yaml` for a working example.

### Timeout hierarchy (REQ-RUNTIME-001)

The only blocking third-party call is the LLM request made by the AI platform. Bound it with the bundle client and keep each outer layer strictly larger, innermost first:

| Layer | Default / demo | Role |
| ----- | -------------- | ---- |
| `nowo_wiki.ai.http_timeout` (`nowo_wiki.ai.http_client`: `timeout` + `max_duration`) | 30 s | Fires first; the transport error is logged and the page shows `wiki.ai.error` |
| PHP `max_execution_time` / `max_input_time` | 60 s in the demo | Must be greater than `http_timeout` |
| Caddy `servers.timeouts.write` (FrankenPHP) | 75 s in the demo | Must be greater than PHP |
| FrankenPHP `max_wait_time` | 30 s in the demo | Caps the wait for a free PHP thread (504) so slow LLM calls do not queue forever |

When you raise `http_timeout`, raise the PHP and HTTP write deadlines in the same change. Without `http_client: 'nowo_wiki.ai.http_client'` on the platform, the call only has the timeouts of `framework.http_client` (Symfony default: none beyond `default_socket_timeout`). Imports and exports work on local files and are bounded by the upload cap (`import_export.max_upload_bytes`), the ZIP entry/size limits of `WikiArchiveHelper` and PHP `max_execution_time`. The demo stack is documented in [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md#timeouts-req-runtime-001).

## Logging (REQ-OBS-001)

The bundle logs through PSR-3 `Psr\Log\LoggerInterface` (`@?logger`, tagged `monolog.logger` channel **`nowo_wiki`**; nothing is logged when no logger service exists):

| Event | Level | Context |
| ----- | ----- | ------- |
| Import started / finished / failed | info / info / error | `space`, `format`, `update_existing`, `dry_run`, counters, `exception_class` |
| Export started / finished / failed | info / info / error | `space`, `format`, `pages_exported`, `exception_class` |
| Page archived | info | `space`, `page` (slugs) |
| AI agent call started / succeeded / failed / non-text result | info / info / error / error | `space`, `question_length`, `context_pages`, `answer_length`, `exception_class`, `result_type` |

Never logged: page content, question or answer text, user identifiers, API keys or exception messages (which may contain provider URLs). Route the channel with Monolog, e.g. `monolog.handlers.wiki: { type: stream, path: '%kernel.logs_dir%/wiki.log', channels: [nowo_wiki] }`.

See `src/DependencyInjection/Configuration.php` for the full tree.
