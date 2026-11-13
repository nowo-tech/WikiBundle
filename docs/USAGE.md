# Usage — WikiBundle

## Table of contents

- [Manage UI routes (defaults)](#manage-ui-routes-defaults)
- [Import / export](#import--export)
- [Symfony AI](#symfony-ai)
- [Search](#search)
- [Application integration](#application-integration)
- [Using it from a DDD / hexagonal application](#using-it-from-a-ddd--hexagonal-application)
- [Pagination and list limits (REQ-PERF-001)](#pagination-and-list-limits-req-perf-001)
- [Space icon (1.32)](#space-icon-132)
- [Twig overrides (REQ-TWIG-001)](#twig-overrides-req-twig-001)
- [Theming: classes, tokens and blocks (REQ-UI-003)](#theming-classes-tokens-and-blocks-req-ui-003)
  - [Page layout (1.29)](#page-layout-129)
- [Translations (REQ-I18N-001)](#translations-req-i18n-001)
- [Events](#events)
- [Links to host objects (1.9)](#links-to-host-objects-19)
- [Attachments (1.9)](#attachments-19)
- [Markdown pages (1.11)](#markdown-pages-111)
- [Milkdown editor (1.13)](#milkdown-editor-113)
  - [Co-editing (1.13)](#co-editing-113)
  - [Conversion report (1.13)](#conversion-report-113)
- [Comments (1.16)](#comments-116)
  - [Endpoints](#endpoints)
  - [Notifications](#notifications)
  - [Beacon](#beacon)

## Manage UI routes (defaults)

| Route name | Path |
|------------|------|
| `nowo_wiki_index` | `/tools/wiki` |
| `nowo_wiki_space` | `/tools/wiki/spaces/{spaceSlug}` |
| `nowo_wiki_page_view` | `/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}` |
| `nowo_wiki_page_edit` | `…/edit` |
| `nowo_wiki_page_history` | `…/history` |
| `nowo_wiki_page_diff` | `…/diff?from=N&to=M` |
| `nowo_wiki_search` | `/tools/wiki/search?q=…&space={slug}` (space optional — searches all accessible spaces) |
| `nowo_wiki_ai_ask` | `/tools/wiki/ask` (requires `nowo_wiki.ai.enabled`) |
| `nowo_wiki_space_export` | `/tools/wiki/spaces/{spaceSlug}/export` |
| `nowo_wiki_space_import` | `/tools/wiki/spaces/{spaceSlug}/import` (POST, multipart upload) |
| `nowo_wiki_page_delete` / `nowo_wiki_space_trash` / `nowo_wiki_trash_restore` / `nowo_wiki_trash_purge` | `…/pages/{pageSlug}/delete`, `…/spaces/{spaceSlug}/trash`, `…/trash/{batchId}/restore`, `…/trash/{batchId}/purge` (1.14, only with `trash.enabled`) |
| `nowo_wiki_page_move` | `…/pages/{pageSlug}/move` (1.14, only with `tree.enabled`) |
| `nowo_wiki_page_version_save` / `nowo_wiki_page_version_copy` | `…/pages/{pageSlug}/versions/new`, `…/pages/{pageSlug}/revisions/{revisionNumber}/copy` (1.15, only with `history.timeline`) |
| `nowo_wiki_space_tree` | `/tools/wiki/spaces/{spaceSlug}/tree/{move\|state}` (1.14, POST JSON, only with `tree.enabled`) |

## Import / export

Import Outline or Notion Markdown exports into a space (directory or ZIP). Export a space back to Markdown (directory or ZIP).

**Console commands:**

```bash
bin/console wiki:import {spaceSlug} /path/to/export [--format=auto|outline|notion] [--update-existing] [--dry-run]
bin/console wiki:export {spaceSlug} /path/to/output [--format=outline|notion] [--zip]
```

Every export holds a `README.md` index at its root (space title, export date, links to the exported pages); imports skip it.

Configure limits and ACL in `nowo_wiki.import_export` and `nowo_wiki.security.roles.{import,export}`.

## Symfony AI

When `nowo_wiki.ai.enabled` is `true` and `symfony/ai-bundle` is installed, the manage UI exposes `/tools/wiki/ask`. Answers use wiki search hits injected into the agent prompt (and optionally `WikiKnowledgeSearchTool` in the agent toolbox).

## Search

The manage UI includes a **full-text search** over:

- Page **titles**
- **Current revision** body: its plain text (1.16, the `content_text` column: the sanitized HTML without markup, so
  Markdown punctuation, directive markers and attribute values are never hits). Revisions written before 1.16 are
  matched on their Markdown source or HTML until `wiki:search:backfill-text` fills their text.

Search respects ACL: only spaces returned by `WikiSpaceAccessResolver` are queried. Leave `space` empty to search **across all spaces** you can access.

Results show a **snippet** centered on the first match in the body (or a title fallback), plus the space badge when searching globally.

Example: `/tools/wiki/search?q=deploy&space=engineering`

With `search.scope: space` (1.21) the search is always inside one space: a request without a valid `?space=` is 404
and the form has no space choice ([CONFIGURATION › Host hooks](CONFIGURATION.md#host-hooks-121)).

## Application integration

1. Implement `WikiAccessCheckerInterface` (or use role-based defaults).
2. For team spaces, register `WikiTeamMembershipResolverInterface`.
3. Set **`nowo_ui_kit.layouts.admin`** (once for every UiKit bundle) to your project layout or a one-file bridge that renders the UiKit canonical blocks — every wiki page extends a UiKit scaffold that extends it and stacks `wiki.css` with **`{{ parent() }}`** (REQ-UI-001 / REQ-UI-003). `nowo_wiki.web_ui.layout_template` overrides it for the wiki only. Do not fork wiki pages; see [TEMPLATES.md](TEMPLATES.md).
4. Seed `WikiSpace` rows per team (or expose a create UI in your app).

## Using it from a DDD / hexagonal application

WikiBundle is a **bounded context with its own model and persistence** (spaces, pages, revisions, links, attachments,
comments, trash; Doctrine ORM) plus its own UI and REST API. Treat it as a separate "wiki" context: your domain refers
to wiki pages by id, and the wiki refers to your objects by string ids.

**Keep your domain free of the bundle**

- Do not hold `WikiPage` / `WikiSpace` entities in your aggregates; store the page or space id (UUID string).
- Spaces are owned by `owner_scope_type` (`team` | `user`) and an `owner_scope_id` string — no relation to your
  team or user tables.
- Link wiki pages to your objects with `WikiPageLinkService::link($page, $objectType, $objectId)`; the wiki only keeps
  the strings and asks your `WikiObjectLinkResolverInterface` what the viewer may see.
- Call the ports `Service\WikiPageServiceInterface` / `Service\WikiSpaceServiceInterface` /
  `Link\WikiPageLinkServiceInterface` (public aliases of the services, 1.27) from an infrastructure adapter, not from
  domain code; replace them with doubles in your tests.
- Your **security user does not have to be the `user_class` entity** (1.27), but the wiki only trusts the classes
  you list: `$author` / `$actor` arguments and the token user go through `User\WikiUserReferenceResolverInterface`.
  The default keeps `user_class` instances and turns an instance of a class listed in
  `nowo_wiki.accepted_user_classes` (a DTO, a read model carrying the `user_class` id) into
  `getReference(user_class, id)` on the wiki entity manager, without a query. Any other user is refused
  (`AccessDeniedException`) and comment ownership never matches it by id, so a second identity domain with overlapping
  ids (Admin#5 next to Customer#5) fails closed. Ids of 0, negative ids and empty ids are refused. The comment privacy
  export / erasure (`exportAuthorData()`, `exportAuthor()`, `eraseAuthor()`) throw `AccessDeniedException` for a user
  of a class that is not accepted. Alias the interface to your own resolver when the ids differ.

        nowo_wiki:
            user_class: App\Entity\User
            accepted_user_classes: [App\Security\SecurityUser]   # default: [] = user_class only

**Ports you implement** (alias the interface to your service, or set the config key)

| Concern | Interface | Config |
|---|---|---|
| Who may use the wiki | `Security\WikiAccessCheckerInterface` | `security.access_checker` |
| Per-space / per-page rights | `Security\WikiSpaceAccessCheckerInterface` | service alias |
| Team membership | `Security\WikiTeamMembershipResolverInterface` | `team_membership_resolver` |
| Your objects shown on a page | `Link\WikiObjectLinkResolverInterface` | autoconfigured |
| Avatars | `Avatar\WikiUserAvatarResolverInterface` | service alias |
| Security user → stored user relation | `User\WikiUserReferenceResolverInterface` | service alias (1.27) |
| File storage / scanning | `Attachment\WikiAttachmentStorageInterface`, `WikiAttachmentScannerInterface` | `attachments.storage`, `attachments.scanner` |
| REST API | `Api\Hook\*Interface` (authenticator, directory, users, plan gate, ...) | `api.hooks.*` |

**Events you listen to** (`Event\WikiEvents`, dispatched after the flush): `PAGE_SAVED`, `PAGE_CREATED`,
`PAGE_ARCHIVED`, `PAGE_MOVED`, `PAGE_TRASHED`, `TRASH_PURGED`, `PAGE_FILES_PURGED`, `PAGE_LINKED`, `ATTACHMENT_*`,
`COMMENT_*`, `API_WRITE`; `QUOTA_CHECK` runs **before** a metered write and can `deny()` it. Translate them into your
own messages in one listener:

    #[AsEventListener(WikiEvents::PAGE_SAVED)]
    final readonly class WikiPageSavedToDomain
    {
        public function __construct(private MessageBusInterface $bus) {}

        public function __invoke(WikiPageSavedEvent $event): void
        {
            $this->bus->dispatch(new KnowledgePageChanged(
                $event->page->getId(),
                $event->revision->getRevisionNumber(),
                $event->actorId(), // 1.27: the actor's getId() as a string, whatever its class
            ));
        }
    }

**Known limitations**

- `user_class` is required: revision authors, link creators, uploaders, comment authors and reactions are Doctrine
  `ManyToOne` relations to that class, remapped at runtime by `WikiMetadataListener`. The host needs a Doctrine user
  class in the wiki's entity manager (an infrastructure persistence model is enough); the security user may be another
  class with the same `getId()` (see above). There is no plain-id mode.
- Deleting a user row keeps their page revisions without an author (1.26, `ON DELETE SET NULL`) and their comments
  without an author; their reactions go with them.
- Event payloads still carry wiki entities; the actor is `?object` plus `actorId(): ?string` (1.27). Page, space and
  revision ids are read from the entities (`$event->page->getId()`).
- `WikiCommentService` is final and has no interface (it is the UI / REST comment engine; call its privacy methods
  from your adapter).
- The bundle maps its entities in the default entity manager; with another `database.entity_manager`, add the
  `WikiBundle` mapping to that manager yourself.

## Pagination and list limits (REQ-PERF-001)

List pages of the manage UI are bounded by default (`nowo_wiki.pagination`, see [CONFIGURATION.md](CONFIGURATION.md#pagination-req-perf-001)):

| Page | Limit | Navigation |
| ---- | ----- | ---------- |
| Wiki index (spaces) | `spaces_per_page` (24) | `?page=N`, UiKit pagination |
| Page history (revisions) | `revisions_per_page` (25), one extra row is read to link the last "Compare" button; the total is the current revision number (history is append-only) | `?page=N`, UiKit pagination |
| Sidebar page tree (space + page view) | `max_tree_pages` (500) pages loaded with one query | a notice links to search when more pages exist |
| Search results | 25 hits | — |

Each list is one query (no N+1): spaces through `WikiSpaceRepositoryInterface::findAccessible()`, the tree through `findActiveBySpace($space, $max + 1)`, history through `findByPage($page, $limit, $offset)`. Exports (`/export`, `wiki:export`) are synchronous, write to a temp directory and stream the ZIP; they are bounded by PHP `max_execution_time` — use the `wiki:export` console command for very large spaces.

## Space icon (1.32)

A space has an optional `icon`: a UX icon name such as `tabler:book` or `tabler:rocket` (`WikiSpace::getIcon()` /
`setIcon()`).

- Rendering (1.32): a `<prefix>:<name>` value is drawn only when the host sets `nowo_ui_kit.icon_set: ux_icon` (Symfony UX
  Icons) and the icon set is installed (`tabler` and `bi` are the ones the UiKit icon component maps); with another icon
  set the UiKit component falls back to its own icon names. A name that matches the format but does not exist makes
  `ux_icon()` throw `IconNotFoundException` (HTTP 500) in the list and the sidebar: set `ux_icons.ignore_not_found: true`
  in the host. When `symfony/ux-icons` is installed, `WikiSpaceIconType` also rejects an icon that does not exist.
- The icon shows in the space list (a book when null) and before the "Pages" title of the sidebar in the space view and
  the page view (nothing when null).
- Format `^[a-z0-9-]+:[a-z0-9-]+$` (a trailing newline is rejected), at most 64 characters, prefix in
  `nowo_wiki.space_icon.allowed_prefixes` (default `[tabler]`). `setIcon()` throws `InvalidArgumentException`; pass the
  configured prefixes as the second argument when a host allows more than `tabler`.
- `WikiSpaceType` (name + icon) shows the icon field only with the option `icon_field: true` (off by default).
- A host form adds the field with `WikiSpaceIconType`; on a form whose data is a `WikiSpace` it writes through
  `setIcon()` with the configured prefixes, on a DTO or an array through the property path (the field name or `property_path`).

```php
$form = $this->createForm(WikiSpaceType::class, $space, ['icon_field' => true]);
// or in your own form type:
$builder->add('icon', WikiSpaceIconType::class);
```

Space exports carry no space metadata beyond the title (the pages are exported), so the icon is not part of them.

## Twig overrides (REQ-TWIG-001)

The bundle registers **`@NowoWikiBundle/`**. Application files under **`templates/bundles/NowoWikiBundle/`** always win over vendor: `TwigPathsPass` prepends that directory (when it exists) and appends the bundle views after it. Registration is unconditional; the routes follow `web_ui.enabled` (see [CONFIGURATION.md](CONFIGURATION.md)). Controllers render the logical names of `nowo_wiki.web_ui.templates.<page>` (default: the bundle page), so you can also point one page at another template from configuration.

**Freeze rule:** an application file at an override path always wins and will **not** pick up vendor changes for that path until you remove or merge it. Prefer the UiKit theme (`nowo_ui_kit.layouts`, `nowo_ui_kit.theme`, UiKit scaffold overrides) and `web_ui.templates.<page>` with a thin `{% extends '@NowoWikiBundle/…' %}` over copying wiki pages, so `composer update` keeps shipping UI fixes.

**Procedure:**

1. Pick the `<subpath>` from the table below.
2. Create `templates/bundles/NowoWikiBundle/<subpath>` in your application (same relative path and filename).
3. Clear the cache in dev if needed: `php bin/console cache:clear`.
4. Treat the file as a fork: it no longer follows bundle upgrades for that `<subpath>`.

Example: `templates/bundles/NowoWikiBundle/manage/_page_tree_item.html.twig` replaces one item of the sidebar tree only.

| Prefer | Notes |
|--------|--------|
| `nowo_ui_kit.layouts.admin` | Host chrome for every UiKit bundle (wiki pages included) |
| `nowo_ui_kit.css_framework` / `nowo_ui_kit.theme` | CSS stack, class / attribute / partial maps and `--nowo-ui-*` tokens, once |
| `nowo_wiki.web_ui.templates.<page>` | One wiki page: a thin template that extends `@NowoWikiBundle/manage/<page>.html.twig` and overrides one data block |
| Override one wiki partial | Surgical freeze when config is not enough |

```yaml
nowo_ui_kit:
    css_framework: bootstrap5
    layouts:
        admin: 'layouts/admin.html.twig'
```

### Overridable templates

Paths are relative to `src/Resources/views` (`@NowoWikiBundle/<subpath>`):

| `<subpath>` | Purpose |
| ----------- | ------- |
| `manage/index.html.twig` | Wiki index (UiKit list scaffold): spaces, global search filters, AI dialog |
| `manage/space.html.twig` | Space page (UiKit show scaffold): page tree, in-space search, import, archived pages |
| `manage/page_view.html.twig` | Page view (UiKit show scaffold): sanitized body (`nowo_wiki_safe_html`), tree, "Used in" |
| `manage/page_edit.html.twig` | Create / edit (UiKit form scaffold, `WikiPageFormType`, Tiptap editor script) |
| `manage/page_history.html.twig` | Paginated revisions (UiKit list scaffold) with Compare / Restore |
| `manage/page_diff.html.twig` | Diff of two revisions (UiKit show scaffold) |
| `manage/search.html.twig` | Search (UiKit list scaffold: filters + results) |
| `manage/ai_ask.html.twig` | AI question (UiKit form scaffold) with the answer and sources |
| `manage/_sidebar.html.twig` | Sidebar card with the page tree and the "tree truncated" notice |
| `manage/_page_tree.html.twig` | One level of the tree (`<ul class="nowo-wiki-tree">`) |
| `manage/_page_tree_item.html.twig` | One page of the tree: link, depth class, `aria-current`, its children |
| `manage/_ai_dialog.html.twig` | AI question `<dialog>` of the index (UiKit `_dialog`) |
| `manage/_legacy_layout.html.twig` | Deprecated bridge around a pre-1.10 layout (removed in 2.0) |

Page header, pagination, flashes, empty states, confirm dialogs and row actions are UiKit partials: re-skin them in UiKit (`templates/bundles/NowoUiKitBundle/…` or `nowo_ui_kit.theme.partials`), once for every bundle.

Renaming or removing one of these `<subpath>` values (or a stable `{% block %}`) is a breaking change and is announced in [UPGRADING.md](UPGRADING.md).

### Recovering upstream after a freeze

Delete (or rename aside) the application override, clear the Twig cache, then re-apply only the surgical changes you still need. Diff your copy against the new vendor file under `vendor/nowo-tech/wiki-bundle/src/Resources/views/<subpath>` before re-copying.

### Forms in templates (REQ-TWIG-005)

Every form in the manage views is a PHP Symfony Form Type (Form Kit `wiki` profile) built by `WikiManageController` and rendered with `form_start` / the child loop / `form_end`:

| View | Variable | Form Type |
| ---- | -------- | --------- |
| `manage/page_edit.html.twig` | `page.form` | `WikiPageFormType` — its own CSRF field (token id `wiki_edit_<page id>` / `wiki_new_<space id>`) replaces the former top-level `_token` |
| `manage/index.html.twig`, `manage/search.html.twig` (`page.filters`), `manage/space.html.twig` (`search_form`) | `page.filters` / `search_form` | `WikiSearchType` (GET, flat `space` / `q`) |
| `manage/space.html.twig` | `import_form` | `WikiImportType` (multipart, flat `format` / `archive` / `update_existing` / `_token`) |
| `manage/ai_ask.html.twig` (`page.form`), `manage/_ai_dialog.html.twig` (`ai_form`) | `page.form` / `ai_form` | `WikiAiAskType` (flat `space` / `q` / `_token`, token id `wiki_ai_ask`) |

Render custom children first, then the canonical loop so remaining children (CSRF, hidden fields) still appear:

```twig
{{ form_start(search_form, {attr: {class: nowo_ui_class('form')}}) }}
    {% for child in search_form %}
        {% if not child.rendered %}
            {{ form_row(child) }}
        {% endif %}
    {% endfor %}
    <button type="submit" class="{{ nowo_ui_class('btn_primary') }}">…</button>
{{ form_end(search_form) }}
```

Template overrides and demos **must not** reintroduce raw `<form>`, `<input>`, `<textarea>` or `<select>` markup; `make check-no-raw-html-form` (part of `release-check`) fails when they do.

## Theming: classes, tokens and blocks (REQ-UI-003)

Since 1.10 the wiki pages are UiKit pages: the host themes them through UiKit (`nowo_ui_kit.css_framework`,
`nowo_ui_kit.theme` classes / attributes / partials / `--nowo-ui-*` tokens, scaffold overrides) once for every bundle.
The page → scaffold map, the view models, the data blocks and the value-free page data are in
[TEMPLATES.md](TEMPLATES.md).

The wiki-specific regions carry semantic hooks styled by `wiki.css` with the UiKit tokens (no `--nowo-wiki-*` token
tree any more; 1.21 adds a few `--nowo-wiki-tree-*` tokens for the nested list, see
[CONFIGURATION › Tree tokens](CONFIGURATION.md#tree-tokens)):

| Block | Elements and modifiers |
|-------|------------------------|
| `nowo-wiki-layout` | `__aside`, `__main` (sidebar + content grid, one column under 62 rem) |
| `nowo-wiki-sidebar` | `__header`, `__body` (`data-wiki-sidebar`) |
| `nowo-wiki-tree` | `--nested`, `__item`, `__item--depth-0` … `__item--depth-6` (capped; `data-wiki-depth` has the real depth), `__item--parent`, `__link`, `__link--current` (`aria-current="page"`), `__truncated`; root `data-wiki-tree` |
| `nowo-wiki-import-export` | `__export`, `__import` (1.21, `web_ui.import_export: dialog`) |
| `nowo-wiki-moderation` | `__summary`, `__list`, `__item`, `__text`, `__label`, `__meta`, `__confirm` (1.21, `comments.moderation_panel`); `nowo-wiki-comments__notice` (`comments.error_notices`) |
| `nowo-wiki-content` | the sanitized page body (`data-testid="wiki-content"`) |
| `nowo-wiki-diff` | `__lines`, `__line`, `__line--add`, `__line--remove`, `__line--same` (`data-wiki-diff`) |
| `nowo-wiki-archived`, `nowo-wiki-used-in` | `__list`, `__item`, `__title` / `__link` |
| `nowo-wiki-ai` | `__answer`, `__text`, `__sources`; `nowo-wiki-sources`, `__item` |
| others | `nowo-wiki-region-title` (card titles of these regions), `nowo-wiki-link`, `nowo-wiki-results__excerpt` |

**Blocks.** Use `nowo_wiki.web_ui.templates.<page>` with a template that extends the bundle page and overrides one
UiKit data block (`nowo_ui_list_row`, `nowo_ui_show_sections`, `nowo_ui_show_after`, `nowo_ui_form_body`,
`nowo_ui_form_aside`, `nowo_ui_modals`, …, or 1.21 `wiki_sidebar`, `wiki_space_intro`, `wiki_page_header_extra`):

```twig
{# templates/wiki/page_view.html.twig, set as nowo_wiki.web_ui.templates.page_view #}
{% extends '@NowoWikiBundle/manage/page_view.html.twig' %}

{% block nowo_ui_show_after %}
    {{ include('wiki/_linked_issues.html.twig') }}
{% endblock %}
```

Templates carry no `style="…"`, no `on*="…"` handlers, no `<style>` block, no CDN asset and no `confirm()`; the editor
`<script>` prints the request attribute `csp_nonce` as `nonce="…"` when the host sets it (see
[SECURITY](SECURITY.md#content-safety)). `tests/Integration/Contract/PageContractTest.php` (UiKit `PageContractTestCase`)
enforces it for every bundle page.

### Page layout (1.29)

Four opt-in layout options for a three-column page (tree + page + comments), see
[CONFIGURATION › Page layout](CONFIGURATION.md#page-layout-129):

- **Tree rail** (`web_ui.tree_rail: true`): a chevron button in the tree card header ("Collapse Pages") folds the
  card to a 2.75rem rail; the button then reads "Expand Pages" and shows "Pages" vertically. Keyboard: Enter / Space;
  the tree leaves the tab order while folded. Without JavaScript the button is a link to `?tree=rail` / `?tree=full`.
- **Reading column** (`web_ui.reading_width: readable`): the page and its "Used in" / "Files" cards centred at
  `--nowo-wiki-reading-width`:

  ```css
  .my-wiki { --nowo-wiki-reading-width: 52rem; }
  ```

- **Wide content** (always): code blocks and tables scroll sideways inside the column and get a tab stop while they
  overflow (UiKit `js/nowo-ui-scroll-region.js`).
- **Compact header** (`web_ui.page_header.layout: compact`): title, then one row with the tabs (*Page* / *Edit* /
  *History*) at the start and the actions at the end (icon buttons, a "more" menu, the comments toggle); the row wraps
  under the tabs on a phone. `title: dedupe` stops a body that starts with `# <page title>` from repeating it.
- **Narrow screens** (`web_ui.narrow_tree_position: after`): under 62rem the page comes first and its tree after it.

New Twig blocks of the page view: `wiki_page_body` (the body card) and `wiki_page_after_body` (empty, after the body:
backlinks, related objects) — override one block instead of copying `nowo_ui_show_sections`.

#### 1.30 additions

- **Host toolbar buttons.** Instead of overriding `nowo_ui_page_header`, fill the block `wiki_page_header_toolbar` in a
  `web_ui.templates.page_view` (and `page_edit`) that extends the bundle page, or point
  `web_ui.slots.page_header_toolbar` at a template. It is printed where the toolbar is (compact header, end of the
  stacked tab row, or the content card), before the tree and comments toggles, with the page variables (`page`,
  `can_edit`, `comments`, …):

  ```twig
  {# templates/wiki/manage/page_view.html.twig (nowo_wiki.web_ui.templates.page_view) #}
  {% extends '@NowoWikiBundle/manage/page_view.html.twig' %}

  {% block wiki_page_header_toolbar %}
      {%- if can_edit|default(false) %}{{ include('wiki/_copy_markdown_button.html.twig', {markdown: wiki_page_markdown()}, false) }}{% endif -%}
      {{ parent() }}{# the web_ui.slots.page_header_toolbar template, if any #}
  {% endblock %}
  ```

  Use toolbar-sized buttons (UiKit `btn_icon`) and print escaped values only: the block is trusted host code.
- **Tree that disappears** (`web_ui.tree_collapsed_mode: hidden`): collapsing the tree removes it; the expand button
  is in the toolbar.
- **Reading column at the start** (`web_ui.reading_align: start`).
- **Title in the body** (`web_ui.page_header.title: body`): pages written as `# <title>` drop the header title.
- **Tabs as buttons** (`web_ui.page_header.tabs: toolbar`) and **toolbar in the content card**
  (`web_ui.page_header.toolbar_position: card`), Notion / Confluence style. With all of them:

  ```yaml
  nowo_wiki:
      web_ui:
          tree_rail: true
          tree_collapsed_mode: hidden
          reading_width: readable
          reading_align: start
          page_header:
              layout: compact
              title: body
              tabs: toolbar
              toolbar_position: card
  ```

  A host that overrides `wiki_page_body` keeps the card toolbar: it wraps the read-only editor and that block.
- **Seamless read view** (`web_ui.read_view.seamless_swap: true`): commenters no longer see the page change style or
  jump when the read-only editor is ready. Map your Markdown typography onto the read tokens, for example:

  ```css
  .nowo-wiki-read-swap {
      --nowo-wiki-read-font-size: 1rem;
      --nowo-wiki-read-line-height: 1.75;
      --nowo-wiki-read-h1-size: 1.875rem;
      --nowo-wiki-read-h2-size: 1.5rem;
      --nowo-wiki-read-h3-size: 1.25rem;
      --nowo-wiki-read-heading-margin: 2rem 0 0.75rem;
      --nowo-wiki-read-block-margin: 0 0 1rem;
      --nowo-wiki-read-code-background: var(--surface-2);
      --nowo-wiki-read-link-color: var(--link);
  }
  ```

  A `wiki_page_body` override keeps working (the box wraps the block); give its card the same UiKit `card` /
  `card_body` classes as the bundle's, and drop typography rules of your own class there (`.md-content`), or set them
  with the same values, so both bodies match.
- **Path and sub-pages** (`web_ui.page_header.path: card`, `web_ui.children: auto`): "Space › Parent" above the
  body, a "N subpages" chip and a "Pages in this section" card, permission-filtered (a hidden ancestor shows as "…").
  With `title: body` and `toolbar_position: card` the page starts with the card: toolbar at the top right, the path,
  then the body's `<h1>`.
- **BreadcrumbKit** (`web_ui.breadcrumb_current_label: true`, BreadcrumbKit >= 2.17 optional): the host's breadcrumb
  ends with the page title instead of the route's static label.

#### 1.31 additions

From the Symfony Beacon wiki UX review (content first, nothing repeated above the content). Every option is off by
default; the fixes apply to everyone.

- **Title in the card** (`web_ui.page_header.title: card`): the page view header never shows the title. A page that
  does not start with `# <title>` gets the title as the content card's `<h1>` (`.nowo-wiki-page-title`, after the path
  and the sub-pages chip, before the body, `--nowo-wiki-read-h1-size`); a page that does keeps its own heading, as
  with `body`. With `tabs: toolbar` and `toolbar_position: card` the header is not printed at all and that `<h1>` is
  the page's only one; otherwise the header keeps its visually hidden `<h1>` and the card line is visual only
  (`aria-hidden`).
- **Revision line in the card** (`web_ui.page_meta: card`): "v3 · Updated Nov 11, 2026, 9:40 PM" as the card's muted
  footer (`.nowo-wiki-page-meta`, `<time datetime>`), in the reader's locale (`IntlDateFormatter` medium date + short
  time with the request locale; `Y-m-d H:i` without ext-intl), instead of the scaffold's Revision / Updated list.
- **Comments as a drawer** (`comments.layout: overlay`): the UiKit side panel with `layout: overlay` at every width
  (full height, backdrop, Escape, focus trap from UiKit); the text keeps its width. It starts closed on every page
  (the remembered state is not applied); `?comments=1` (notification links) still opens it. A host partial that forced
  the overlay (`theme.bundles.NowoWikiBundle.partials.side_panel`) can go.
- **UiKit thread list** (`comments.thread_list: uikit`): the thread component's list in UiKit list rows, the count as a
  neutral badge with a real plural ("1 comment", "3 comments"; `wiki.comments.list.count_one|many|other`, `#` is the
  number, chosen with the browser's CLDR plural rules for the page `lang`), "Show resolved" as a UiKit checkbox and
  "Comment thread" for a thread without a title.
- **Edit form with `tabs: toolbar`**: no "‹ Back" link and no space name under the title (the toolbar has "Back to
  page"); "Reset collaboration" is the last item of the ⋮ menu, in the danger style, still behind its confirmation.
- **BreadcrumbKit on the child pages** (`web_ui.breadcrumb_current_label: true`): the edit, history, compare and move
  pages name their page crumb after the page too ("… › Wiki › Deploy runbook › Edit" instead of "… › Page › Edit"),
  through a `BreadcrumbTrailBuiltEvent` listener (`Ui\WikiBreadcrumbParentLabel`, registered only with BreadcrumbKit).
- **Read tokens for h4–h6 and callouts** (seamless swap): `--nowo-wiki-read-h4-size` (1em), `-h5-size` (0.875em),
  `-h6-size` (0.85em); `--nowo-wiki-read-callout-margin|padding|radius|gap|icon-size` for `.nowo-md-callout`.

Beacon, everything on:

```yaml
nowo_wiki:
    web_ui:
        page_meta: card
        breadcrumb_current_label: true
        page_header:
            title: card
            tabs: toolbar
            toolbar_position: card
    comments:
        layout: overlay
        thread_list: uikit
```

Fixes in 1.31 (no option): Crepe's leading `.ProseMirror-widget` no longer pushes the title down at the seamless swap
(nor breaks `title: dedupe`); the toolbar's tree button chevron points into the page; the page head takes the full
width under 40rem; search excerpts leave out a leading heading equal to the title; the shared `content_html` cache
stores hidden references as an empty, language-neutral marker (see [UPGRADING](UPGRADING.md#to-1310)); the sanitizer
keeps the disabled task checkboxes of MilkdownEditorBundle >= 1.17.

## Translations (REQ-I18N-001)

Every UI string uses the **`NowoWikiBundle`** domain (`src/Resources/translations/NowoWikiBundle.<locale>.yaml`). Shipped locales: `en` (reference), `es`, `it`, `fr`, `pt`, `de`, `nl`; `make validate-translations` checks YAML syntax and key parity across them.

Application catalogues take precedence over the bundle, which only provides fallbacks for keys you do not define:

1. Use the same domain: `NowoWikiBundle`.
2. Create `translations/NowoWikiBundle.<locale>.yaml` (or `.xlf`) in your application.
3. Override only the keys you need; missing keys fall back to the bundle value.
4. Clear the cache in dev if needed: `php bin/console cache:clear`.

Example `translations/NowoWikiBundle.en.yaml`:

```yaml
wiki:
  index:
    title: Knowledge base
  space:
    new_page: Add article
```

To add a locale the bundle does not ship (e.g. `ca`), create `translations/NowoWikiBundle.ca.yaml` with the keys of `vendor/nowo-tech/wiki-bundle/src/Resources/translations/NowoWikiBundle.en.yaml`.

## Events

Dispatched after the change is flushed, except `nowo_wiki.quota.check`.

- `nowo_wiki.quota.check` — `WikiQuotaCheckEvent` (`action`, `space`, `user`, `count`, `bytes`, `page`, `deny(reason)`; 1.21): **before** a new page, unarchive, trash restore, import or attachment upload; a listener may refuse the write ([CONFIGURATION › Quota event](CONFIGURATION.md#quota-event))
- `nowo_wiki.page.saved` — `WikiPageSavedEvent` (`page`, `revision`, `previousRevision`, `actor`; also after create and restore)
- `nowo_wiki.page.archived` — `WikiPageArchivedEvent` (`page`, `actor`)
- `nowo_wiki.page.created` / `.unarchived` — `WikiPageCreatedEvent` / `WikiPageUnarchivedEvent` (1.9)
- `nowo_wiki.page.restored` — `WikiPageRestoredEvent` (`restoredFrom`, `revision`, 1.9)
- `nowo_wiki.page.linked` / `.unlinked` — `WikiPageLinkEvent` (`objectType`, `objectId`, 1.9)
- `nowo_wiki.attachment.uploaded` / `.deleted` — `WikiAttachmentEvent` (1.9)
- `nowo_wiki.page.trashed` / `nowo_wiki.trash.restored` / `nowo_wiki.trash.purged` — `WikiTrashEvent` (`space`, `batchId`, `rootTitle`, `pageIds`, `actor`; 1.14, `trash.enabled`; after a purge the pages no longer exist)
- `nowo_wiki.page.files_purged` — `WikiPageFilesPurgedEvent` (`space`, `batchId`, `pageIds`, `files` = list of `PageFile\WikiPageFile`, `actor`; 1.20, `page_files.purge_event`): the files every revision of the purged pages referred to, for the host to delete from its storage (see [CONFIGURATION › Page files](CONFIGURATION.md#page-files-120))
- `nowo_wiki.page.moved` — `WikiPageMovedEvent` (`page`, `fromParent`, `toParent`, `index`, `actor`, `movedAt`; 1.14, `tree.enabled`): the audit entry of a move
- `nowo_wiki.editor.options` — `WikiEditorOptionsEvent`: add TiptapEditorType options (e.g. the suggestion hook's candidate URLs) for the space and page being edited (1.9)

## Links to host objects (1.9)

```php
final class IssueLinkResolver implements WikiObjectLinkResolverInterface   // autoconfigured
{
    public function supports(string $objectType): bool { return $objectType === 'issue'; }

    // One query for every id; leave out what the viewer may not open.
    public function resolveVisible(UserInterface $user, string $objectType, array $objectIds): array
    {
        $objects = [];
        foreach ($this->issues->findVisible($user, $objectIds) as $issue) {
            $objects[(string) $issue->getId()] = new WikiLinkedObject('issue', (string) $issue->getId(), $issue->getTitle(), $this->url($issue), $issue->getKey());
        }

        return $objects;
    }
}

$linkService->link($page, 'issue', '42', $user);   // authorize first: the bundle does not know who may link
$linkService->usedIn($page, $viewer);              // ['issue' => [WikiLinkedObject, …]]
```

## Attachments (1.9)

With `attachments.enabled`, the editor uploads dropped / pasted files to `attachment_upload` (POST multipart `file`,
header `X-CSRF-Token`, token id `WikiAttachmentController::csrfTokenId($page)`); the answer is
`{"id", "url", "name", "mime", "size", "image"}` (201) or `{"error": "too_large|quota_exceeded|type_not_allowed|type_mismatch|scanner_rejected|empty"}` (422).
Downloads (`attachment_download`) stream the file to users who may view its page and answer 404 otherwise.

Nightly cleanup (attachments never referenced by a revision 24 h after upload, files without a row):

```php
$attachments->purgeOrphans(new DateTimeImmutable('-24 hours'));
$attachments->purgeStrayFiles();
```

## Markdown pages (1.11)

With `nowo_wiki.editor.type: markdown` ([CONFIGURATION](CONFIGURATION.md#markdown-pages-111)) pages are written in Markdown (GFM: tables, task lists, strikethrough, autolinks). Raw HTML is shown as text and `javascript:` / `data:` links are dropped.

**Convert existing HTML pages** (one new revision per page, archived pages included, history untouched; pages already in Markdown are skipped, so it can run again):

```bash
bin/console wiki:convert-markdown {spaceSlug} [{spaceSlug}...] --author-id={user id or email} [--dry-run] [-v]
    [--page={pageSlug}]... [--report=conversion.json] [--skip-lossy] [--fail-on-loss]
```

1.13: `--page` limits the run to those pages of the given spaces (an unknown slug fails before anything is written). Every HTML page gets a fidelity entry, printed as a summary (pages with losses or approximations) and written with `--report` as JSON — see [Conversion report](#conversion-report-113). **Without `--dry-run` the command writes.** Every page is analysed before anything is written. `--skip-lossy` leaves pages whose conversion loses something (a lost construct or a changed word) in HTML; `--fail-on-loss` exits with 1 when any page loses something and then **writes nothing**. A page whose current revision changed after the analysis is skipped (`skipped_changed`); run the command again for it.

**Add Markdown syntax** (an editor bundle's dialect: page links, mentions, icons, directives) with a league/commonmark extension:

```php
final class WikiDialectExtension implements WikiMarkdownExtensionInterface   // autoconfigured
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addInlineParser(new PageLinkParser());   // [[wiki:<uuid>]] → link
    }
}
```

The output still goes through `WikiHtmlSanitizerInterface` before it is stored: declare the extra markup the extension emits in `nowo_wiki.sanitizer` ([CONFIGURATION](CONFIGURATION.md#sanitizer-allowlist-111)).

**Per-viewer rendering stays on the host:** the stored `content_html` is the same for every reader. Resolve page links, mentions and live blocks when the page is shown (current title and URL for a page the viewer may read, a neutral "not visible" placeholder otherwise).

**Conversion events:** `wiki:convert-markdown` dispatches `nowo_wiki.page.saved` with `$event->conversion === true`. Keep indexing (search, backlinks) on it, but do not notify watchers or log it as an edit:

```php
if ($event->conversion) {
    return;   // bulk HTML → Markdown conversion, not an edit
}
```

**Page references and backlinks:** the bundle's `<prefix>_page_links` table holds host objects linked by hand, so page-to-page references are not written there. A host keeping a backlink index reads them on save:

```php
#[AsEventListener(WikiEvents::PAGE_SAVED)]
public function onSaved(WikiPageSavedEvent $event): void
{
    $ids = $this->extractor->pageIds($event->revision->getContentMarkdown());   // WikiMarkdownReferenceExtractor
    $this->backlinks->replace($event->page, $event->revision, $ids);
}
```

`[[wiki:<uuid>]]` and `[[wiki:<uuid>#anchor]]` are found outside code spans and code blocks; `pageReferences()` also returns the anchors.

**Search, export and import** use the Markdown when a revision has it: search matches the source, export writes it unchanged, and imports store the Markdown bodies as Markdown revisions when `editor.type` is `markdown`.

## Milkdown editor (1.13)

With `nowo_wiki.editor.type: milkdown` ([CONFIGURATION](CONFIGURATION.md#milkdown-editor-113)) the page body is `MilkdownEditorType` and pages are written in the [Milkdown dialect](https://github.com/nowo-tech/MilkdownEditorBundle/blob/main/docs/USAGE.md#markdown-dialect): `[[wiki:<uuid>]]` / `[[wiki:<uuid>#anchor]]` page links, `@handle` mentions, `:tabler-<name>:` icons, `:::callout`, `:::details`, `::: {align=…}`, `:mark[…]`, `::name{…}` host blocks, `$…$` / `$$` math and Mermaid fences. Everything of [Markdown pages](#markdown-pages-111) applies, and the **bridge** makes every wiki surface understand the dialect — no raw token is ever shown:

| Surface | What it gets |
|---------|--------------|
| Page view | The Markdown rendered **for the viewer** on each request (`WikiRevisionBodyRenderer`): page links show the current title and URL of pages the viewer may read and *Content not visible* otherwise, mentions link through your `UserResolverInterface`, host blocks render through your `DirectiveRendererInterface` services; then sanitized, foreign attachment URLs dropped, icons drawn from the allowlisted sprite. `nowo_wiki_rich_content_assets()` loads the Milkdown read-side script and `wiki.js` draws Mermaid (strict) and math (bounded KaTeX) in `[data-wiki-rich-content]`. |
| `content_html` cache, search excerpts, AI context | A **viewer-neutral** render: no page, user or host-block resolution (page links read *Content not visible*, mentions stay `@handle`), so the cache shared by every reader never holds a title or a block of another viewer. |
| Link index | `WikiMarkdownReferenceExtractor::pageReferences()` / `pageIds()` / `mentions()` read the document with the renderer's own parser: exactly the links and mentions a reader sees (never inside code, never an e-mail address), at most `max_references` of each. |
| Export | Page links to pages in the export become ordinary Markdown links to the exported files (`[Title](setup.md#step-1)`, Outline and Notion layouts); links to other pages stay `[[wiki:<uuid>]]` (no title of a page outside the export is written). Icons stay shortcodes, emoji stay Unicode. |
| Editing an HTML page / `wiki:convert-markdown` | HTML is converted to the dialect: `[[wiki:…]]` and `@handle` typed in Tiptap stay tokens, `<mark>` → `:mark[…]`, centred / right-aligned blocks → `::: {align=…}`, embeds → links. |

**Limits** are Milkdown's (`nowo_milkdown_editor.markdown`, `%env()%` values are read at runtime): a document over `max_input_length` bytes or `max_inline_markers` inline delimiters is shown as escaped plain text, unparsed, and `MilkdownEditorType` refuses to save it; at most `max_references` distinct page ids and handles reach the resolvers.

**File previews (1.28, spec 212 block B).** With `editor.milkdown.file_cards: card`, a file card in *preview* view
(`::file[report.csv]{href="/files/<uuid>/original" view="preview"}`) shows the preview your Milkdown
`FilePreviewResolverInterface` approves for the viewer, in a sandboxed, lazy, no-referrer frame on the page view and in
comments. Nothing to wire: the wiki passes Milkdown's resolver alias (call it inside the render: `WikiRenderContext::current()`
names the space, page and API reach) and Milkdown's `file_preview_sandbox`. Allow the preview origin in your CSP
`frame-src`. Turn it off with `editor.milkdown.file_previews: false` ([CONFIGURATION](CONFIGURATION.md)).

**Page links in wiki pages** are resolved by `WikiMilkdownPageResolver` (one query per document, each space decided once): not archived, the general list gate, and `WikiAction::View` on the space, the page and each ancestor. Point `editor.milkdown.page_resolver` at your own service to change it (e.g. Beacon's resolver that also links issues and tasks).

### Co-editing (1.13)

Several people edit one Milkdown page at once (FR-076): Yjs in the browser (`@milkdown/plugin-collab` in nowo-tech/milkdown-editor-bundle ^1.2), Mercure for the relay, the wiki for permissions and the stored Yjs log.

```yaml
nowo_wiki:
    editor: { type: milkdown }
    collaboration:
        enabled: true
        hub_url: '%env(MERCURE_PUBLIC_URL)%'      # the browser subscribes here
        topic_prefix: 'https://wiki.example.com/pages/'
        rate_limiter: limiter.wiki_collab         # framework.rate_limiter.wiki_collab (required)
```

With `symfony/mercure-bundle` 0.6.1 or 0.7.0, whose default protocol is `1.0`, `jwt.secret` alone fails at compile time asking for `iss`, `sub` and `client_id`; set `mercure.hubs.<name>.protocol_version: '0.x'` (needed with `dunglas/mercure` v0.24.2).

The page edit form passes MilkdownEditorType the `collaboration` option (base URL, hub URL, topic, user name and colour) — only on the edit form, so readers never get it. The browser then calls, all under `routes.page_collab` (`…/pages/{pageSlug}/collab/{action}`):

| Request | Answer |
|---------|--------|
| `POST …/collab/token` | 204; sets the `mercureAuthorization` cookie (subscriber JWT for this page's topic only); drops a stale log and claims the seeding of an empty one for this user |
| `GET …/collab/state` | `{"state": "<base64 snapshot>", "updates": ["<base64>", …], "seq": 12, "epoch": "<name of the log>", "compact": false, "seed": true}` — read only |
| `POST …/collab/update` `{c, d}` | 200 with `{"s": n, "p": m}`, the update's number and the one before it (204 with a store that has no numbers); the update is appended to the log and published (`{"t":"u","c":…,"d":…,"s":n,"p":m}`, private) |
| `POST …/collab/awareness` `{c, d}` | 204; presence published (`{"t":"a",…}`), never stored |
| `POST …/collab/compact` `{c, d, s}` | 204; the log up to `s` is replaced with the snapshot `d` |
| `POST …/collab/corrupt` `{c, s}` | 204; the log `s` could not be applied: dropped (capped), editors reload |
| `POST …/collab/reset` (form, `_token`) | 302 to the edit form; the editor's explicit reset |

Every request checks a firewall-trusted user, `canEdit()` and `WikiAction::View` + `WikiAction::Edit` on the space and the page (404 when the page is not readable, 403 when not editable); POSTs must be same-origin and JSON. Every request is rate-limited per user and page. Errors answer `{"error": "<reason>"}`: 400 `invalid_payload`, 409 `log_reset` (the log went over `max_log_bytes` and was dropped; editors reload), 413 `too_large`, 415 `json_required`, 429 `rate_limited` / `log_full`.

**Seeding:** the token request of the first editor of an empty log claims the seeding for that client (user + the tab's Yjs client id, header `X-Nowo-Collab-Client`: one tab seeds even when the same user opened two); `GET state` then answers `seed: true` to that client, which fills the document from the field's Markdown. A claim not fulfilled within `seed_timeout` seconds may be taken again.

**Reset only when needed:** a save made from the live session — the Milkdown client adds `_nowo_milkdown_collab` (the page topic) to the form, and the log's base is the revision being replaced — keeps the log and records the new revision; nobody reloads. The log is dropped and `{"t": "r"}` published (live editors show a notice and reload the saved content) only for:

- a save outside the live session (no session field, e.g. an editor whose collaboration is off or offline) or with a stale base;
- a restore, an import, a conversion or any other save (console, host API);
- a client that cannot apply the log: it posts `POST …/collab/corrupt` `{c, s}` with the sequence number it got; idempotent (a report about a log already reset or grown since changes nothing) and capped at `corrupt_resets` per page per `corrupt_window` seconds (429 `reset_capped` beyond);
- an editor's **Reset collaboration** button on the edit form (POST + CSRF behind a confirmation, edit permission; rate-limited and counted in the same `corrupt_resets` cap; a log that is already empty is not broadcast again);
- a log over `max_log_bytes` (409 `log_reset`).

**Footprint and purge:** per page the log holds at most `max_log_entries` updates (each ≤ `max_update_bytes` decoded) and `max_log_bytes` in total (default 16 MiB), plus one snapshot (≤ `max_snapshot_bytes`); one editor typing produces small updates (tens of bytes each), presence is never stored. Logs of idle pages are deleted by `bin/console wiki:collab:purge --older-than=7` (days; schedule it daily) — nothing is lost, the next editor seeds a new log from the saved Markdown.

As a safety net, a log whose base revision is not the current one is dropped on the next token when it is empty or idle for `reset_after_idle` seconds. **Names:** co-editors see an opaque "Editor xxxx" label by default (1.13.1). Set `collaboration.show_names: true` to show each other's display name (`getDisplayName()` / `getName()` of your user class); a value equal to the login identifier or shaped like an e-mail address still falls back to the opaque label.

Your own endpoints (e.g. Beacon routes) can call `WikiCollabService` directly — check the edit permission first, as `WikiCollabController` does. Replace the storage, the publisher or the cookie with `collaboration.store`, `publisher`, `subscriber_authorizer` (`WikiCollabStateStoreInterface`, `WikiCollabPublisherInterface`, `WikiCollabSubscriberAuthorizerInterface`).

### Conversion report (1.13)

`--report=<file>` writes:

```json
{
    "format": "nowo-wiki-conversion-report",
    "version": 1,
    "generated_at": "2026-11-02T20:00:00+00:00",
    "dry_run": true,
    "dialect": "milkdown",
    "totals": {"pages": 3, "converted": 2, "skipped_markdown": 1, "skipped_empty": 0, "skipped_lossy": 0, "skipped_changed": 0, "lossless": 1, "with_approximations": 0, "with_losses": 1},
    "pages": [
        {
            "space": "eng", "page": "runbook", "page_id": "0b6f4f0e-…", "title": "Runbook",
            "outcome": "would_convert", "from_revision": 3, "to_revision": null,
            "lost": {"underline": 2}, "approximated": {"table_cell_span": 1},
            "text": {"preserved": true, "similarity": 1.0, "words_missing": 0, "words_added": 0}
        }
    ]
}
```

`outcome`: `converted` (`would_convert` in a dry run or when `--fail-on-loss` blocked the writes; then `dry_run` is true), `skipped_markdown`, `skipped_empty`, `skipped_lossy`, `skipped_changed` (edited during the run). `lost` / `approximated` / `text` are present for HTML pages only. `text` compares the words of the HTML with the words of the converted Markdown once rendered (page links and icons left out on both sides).

| Lost | Meaning |
|------|---------|
| `underline`, `subscript`, `superscript` | The text stays, the formatting is dropped |
| `highlight` | `<mark>` without the dialect (`markdown` editor) |
| `alignment` | Justified text; centred / right-aligned text without the dialect |
| `image_size`, `column_width` | Image width / height, table column widths |
| `list_style` | Letter or Roman numbering (`<ol type="a">`) |
| `embed` | An embedded video without the dialect |
| `element:<tag>` | An element the converter drops (its text is kept) |
| `render_limit` | The Markdown is over the renderer's limits and would show as plain text |

| Approximated | Meaning |
|--------------|---------|
| `table_cell_span` | `colspan` / `rowspan` (GFM cells do not span) |
| `table_cell_blocks` | Paragraphs, lists or code inside a cell (flattened to one line) |
| `table_without_header` | GFM tables need a header row: the first row becomes it |
| `nested_table` | A table inside a table |
| `table_caption`, `figure_caption` | The caption becomes text |
| `embed_as_link` | An embedded video becomes a link to it (dialect) |

## Comments (1.16)

With `comments.enabled` (see [CONFIGURATION](CONFIGURATION.md#comments-116)), viewers with
`WikiAction::ViewComments` see a **comments toggle** at the end of the page tab row — an icon button whose badge is
the number of open threads they may read — on the read and edit views. It opens a right-side panel (the UiKit side
panel: an inline column from 768 px, a full-height drawer below) holding the Milkdown 1.6 thread component: inline
threads anchored in the text and page-level threads, replies, reactions, resolve / reopen, edit / delete. A highlight
in the text opens the panel on its thread; a thread scrolls its text into view. The open state is remembered per user;
`?comments=1` / `?comments=0` open or close it for one request (use `?comments=1` in notification links).

**1.29, dock breakpoint.** With the page tree beside the content, the panel squeezes the page long before 768 px:
`comments.dock_min_width: lg` docks it only from 1200 px (`xl`: 1440 px) and makes it the overlay drawer below
(UiKit 2.48 `drawer_below`: focus moves into the drawer and back to the toggle, Escape and the backdrop close it, the
page is inert behind it). A remembered "open" panel starts closed under that width, so laptops and tablets never open
a modal drawer on load; `?comments=1` still opens it (as a drawer there). Without a session the open state is kept in
`localStorage`.

On the read view, **editors** get the page as a read-only Milkdown editor (needed for the highlights and the thread
component). Other viewers with `ViewComments` keep the server-rendered page and never receive its Markdown source:
their panel lists the open threads rendered by the server (inline threads show their quote; at most 50, read only when
the panel is opened) with a form for a page comment. The list offers no reply, reaction or resolve buttons: that is a
user-interface limit, not an authorization one: the JSON endpoints accept those writes from any viewer with the
matching right (`Comment`, `ResolveComment`), from the API or the edit view. `comments.read_view_source: true` gives them the read-only editor too, with the source (see SECURITY).
Without JavaScript the toggle is a link, the panel lists the page-level threads and has the same form.

### Endpoints

Base URL: the `page_comments` route with an empty `path` (`/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/comments`),
the Milkdown `comments.url`. JSON, same origin, header `X-CSRF-Token` (token id
`WikiCommentService::csrfTokenId($page)`) on writes; answers are `Cache-Control: no-store, private`.

| Method + path | Right | Answer |
|---|---|---|
| `GET /threads` | ViewComments | `{threads, page, truncated?}` (at most 2000) |
| `GET /threads/{id}` | ViewComments | Thread |
| `POST /threads` `{anchor \| null, body}` | Comment | 201 Thread (422 for a malformed anchor) |
| `POST /threads/{id}/comments` `{body}` | Comment | 201 Thread |
| `PATCH /threads/{id}` `{resolved}` | ResolveComment | Thread |
| `PATCH /threads/{id}` `{anchor, base}` | Comment | Thread; 409 when `base` is not the thread's `updatedAt` |
| `DELETE /threads/{id}` (1.16.1) | ModerateComments | 204 (the thread, its comments and reactions) |
| `PATCH /comments/{cid}` `{body}` | own + Comment, or ModerateComments | Thread |
| `DELETE /comments/{cid}` | own + Comment, or ModerateComments | Thread, or 204 when the thread is gone |
| `PUT` / `DELETE /comments/{cid}/reactions/{emoji}` | Comment | Thread |

Errors: `{"error": reason}` with 400 (JSON), 403 (right), 404 (page or thread not visible), 409, 413 (body over 16 KiB
or more than 10 000 characters), 415, 422 (`invalid`, `invalid_anchor`, `limit_reached`), 429 (rate limit). Viewers
who may not edit the page get anchors reduced to `{v: 1, quote: {exact}}`, with `exact` blank once the quoted text is no
longer in the page. A CSRF or origin failure is a 403 from the firewall
layer. `author.name` follows the history name policy; `author.avatarUrl` / `resolvedBy.avatarUrl` only beside a real
name ([Avatars](CONFIGURATION.md#avatars-116)).

### Notifications

```php
use Nowo\WikiBundle\Event\WikiCommentEvent;
use Nowo\WikiBundle\Event\WikiEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(WikiEvents::COMMENT_CREATED)]
#[AsEventListener(WikiEvents::COMMENT_REPLIED)]
final class NotifyOnComment
{
    public function __invoke(WikiCommentEvent $event): void
    {
        // $event->page, $event->thread, $event->comment (null for resolve / reopen / a deleted thread),
        // $event->actor, $event->mentions (handles). Check each recipient's ViewComments right on the page, link to
        // the page with ?comments=1 and use WikiCommentService::renderBodyForNotification() (viewer-neutral: this runs
        // in the actor's request). Never serialise $event itself (it carries entities); copy the ids.
    }
}
```

Events: `nowo_wiki.comment.created`, `.replied`, `.edited`, `.deleted`, `.resolved`, `.reopened` and (1.16.1)
`.thread_deleted`, after the flush; `nowo_wiki.comment.author_erased` (`WikiCommentAuthorErasedEvent`) after a privacy
erasure. Show quotes with `WikiCommentService::visibleQuote($event->thread)` (blank once the text left the page),
never `$event->thread->getQuote()`.

### Beacon

What a host such as symfony-beacon configures:

0. **Before enabling:** add the four new `WikiAction` cases to `BeaconWikiAccessChecker` (its exhaustive `match`
   would otherwise throw a 500 on every page view).
1. `nowo_wiki.comments: {enabled: true, rate_limiter: limiter.wiki_comments}` and the rate limiter (per user, across
   pages); `editor.type:
   milkdown` with `nowo-tech/milkdown-editor-bundle` ^1.6 and `nowo-tech/ui-kit-bundle` ^2.26.
2. The migration of the three comment tables and of `content_text`, then `wiki:search:backfill-text` once.
3. Its `WikiSpaceAccessCheckerInterface` maps `ViewComments` → `project.wiki.view` / `wiki.view`, `Comment` → edit,
   `ResolveComment` and `ModerateComments` → manage (B-Q3).
4. The two routes (`page_comments`, `space_panel_state`) behind the wiki firewall; tenant paths get them automatically.
5. 1.21: `comments.moderation_panel: true` and `comments.error_notices: true` replace a forked panel body and Stimulus
   controller.
5. Optional: `comments.state_store` → its user-preferences store (`WikiPanelStateStoreInterface`), `comments.shortcut`.
   Keep `comments.read_view_source: false` (the default) while `ViewComments` maps to view: readers then never get the
   page source; editors keep inline highlights on the read view.
6. Live updates come with co-editing (the comments topic shares the co-editing subscriber cookie).
7. Notifications from `nowo_wiki.comment.*`; deep links with `?comments=1`.
8. Avatars: alias `WikiUserAvatarResolverInterface` to its `WikiAvatarUrls` adapter of the core `AvatarUrls`
   service; keep `history.show_names` as it is (pictures follow names).
9. Search: switch `WikiPageSearchProvider`'s `LIKE` from `content_markdown` to `content_text`.
10. Privacy: call `WikiCommentService::exportAuthor()` / `eraseAuthor()` (or `wiki:comments:erase-user`) from its
    data-subject flows. Pass the `user_class` entity (or an instance of an `accepted_user_classes` entry): since 1.27
    `exportAuthorData()` / `exportAuthor()` / `eraseAuthor()` with a user of another class throw
    `AccessDeniedException` instead of matching a `user_class` row by its id. Since 1.17 also call `eraseMentions([$handle], $erasedLabel)` from the same personal-data
    eraser: every `@handle` of the erased person in **other people's** comments (body, quoted Markdown) and in inline
    thread quotes becomes the label (`@…` by default), without marking the comments as edited. Matching is
    case-insensitive, accepts a Markdown-escaped `\_`, and never touches a longer handle (`@ana` leaves `@ana.b`) or
    an e-mail address. `nowo_wiki.comment.mentions_erased` (`WikiCommentMentionsErasedEvent`: handles, comment ids,
    thread ids) tells search indexes and notification history what to refresh. A host comment store must implement
    `WikiCommentMentionRepositoryInterface` (the Doctrine store does), else the call throws `LogicException`.

    - **Only comments are covered.** Mentions in the page content stay: the current and past revisions
      (`content_markdown` / `content_html` / `content_text`), the page search index and the mention references the
      extractor reports are editorial content. Edit the pages (or purge their history) if they must go too.
    - **The label must not be a mention itself.** A label such as `@deleted-user` is a valid handle, so the viewer
      would link every erased mention to whoever owns that handle. `eraseMentions()` throws
      `InvalidArgumentException` for a label with `@` followed by a handle character, or starting with a handle
      character (an `@@handle` would complete it). Keep the default `@…` or use a text such as `[deleted user]`.
    - **Result.** `eraseMentions()` returns a `WikiCommentMentionErasure` (countable: the rewritten comments and
      threads; `commentIds`, `threadIds`). A row whose text cannot be scanned (invalid UTF-8, or another regex
      failure) is **never overwritten**: it is left as it is and listed in `skippedCommentIds` /
      `skippedThreadIds` (`skipped()`), also on the event. It still holds the mention: fix or delete it, then run the
      erasure again.
    - **Large wikis.** Candidates are read in batches of `WikiCommentService::ERASE_BATCH` (200), each flushed, inside
      one transaction: a failure rolls the whole call back (a failed commit is rethrown as it is, never hidden by the
      rollback), and a call that succeeded leaves nothing for a second one. Only the comments and threads the
      erasure loaded itself are released from the entity manager after each batch; instances the caller already
      had (for example comments kept by `eraseAuthor()`) stay managed with their pending changes. The flushes write
      the entity manager's other pending changes too (as `eraseAuthor()` does).
      If the call throws after a batch was flushed, the database is rolled back but instances the caller still holds
      keep the redacted text: clear (or close) the entity manager after a failed call, as after any Doctrine rollback.
      Instances the caller already held are scrubbed from their in-memory state, so run the erasure on a fresh entity
      manager or with entities that are not stale.
    - **No row locks.** The erasure runs online: a comment edited at the same time can bring the mention back (or
      lose the concurrent edit), and a comment posted during the run may be missed. Both are picked up by running
      the erasure again; run it when nobody is writing comments for the strictest result.
    - **Host stores.** A store that replaces the Doctrine one must honour the paging contract of
      `WikiCommentMentionRepositoryInterface`: at most `$limit` rows, only ids strictly after `$afterId`, ascending id
      order, and a superset (never a subset) of the matching rows. A store that ignores `$afterId` makes the erasure
      loop; one that skips rows leaves mentions behind.
    - **Outer transactions.** When the caller already holds a transaction, the erasure's own transaction is nested
      and `nowo_wiki.comment.mentions_erased` fires after the nested commit, **before** the outer commit: a listener
      may refresh indexes for changes that the outer transaction later rolls back. Listeners that must only see
      committed data should defer their work (for example to `kernel.terminate` or a message).
    - **CLI.** `wiki:comments:erase-user <user> --handle=<handle>` (repeatable; the user's `getHandle()` is added when
      the user class has one, `--mention-label` changes the label) erases the mentions before the user's own
      comments. Without any handle it erases the comments only and warns that mentions were kept. Rows that could not
      be scanned are listed and the command exits with a failure. Each step commits on its own: if the comments step
      fails after the mentions were erased, the erasure is partial, and running the same command again completes it
      (both steps are idempotent).

    ```php
    #[AsTaggedItem(priority: 91)] // before the eraser that rewrites the handle / display name
    final readonly class WikiCommentsEraser implements PersonalDataEraserInterface
    {
        public function erase(ErasureRequest $request): void
        {
            // Mentions first, as the CLI does. Not "@deleted-user": that is a handle someone could own (refused).
            $mentions = $this->comments->eraseMentions([$request->user->getHandle()], '[deleted user]');
            if ($mentions->skipped() > 0) { // text that could not be scanned: still holds the mention, review by hand
                $this->logger->warning('Wiki rows to review', ['comments' => $mentions->skippedCommentIds, 'threads' => $mentions->skippedThreadIds]);
            }
            $this->comments->eraseAuthor($request->user);
        }
        // getKey(), supports() …
    }
    ```
11. No Twig override is needed: the tab row, the toggle and the panel are UiKit partials themed by Beacon's UiKit
    theme (`tabs`, `side_panel`, `side_panel_toggle`, `side_panel_container`, `_avatar`); `nowo-wiki-*` classes are
    hooks only.

