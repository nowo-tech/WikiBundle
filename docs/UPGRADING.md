# Upgrading

## Table of contents

- [Unreleased](#unreleased)
- [To 1.33.0](#to-1330)
- [To 1.32.0](#to-1320)
- [To 1.31.1](#to-1311)
- [To 1.31.0](#to-1310)
- [To 1.30.0](#to-1300)
- [To 1.29.1](#to-1291)
- [To 1.29.0](#to-1290)
- [To 1.29.0](#to-1290-1)
- [To 1.28.0](#to-1280)
- [To 1.27.0](#to-1270)
- [To 1.27.0](#to-1270-1)
- [To 1.26.0](#to-1260)
- [To 1.25.1](#to-1251)
- [To 1.25.0](#to-1250)
- [To 1.24.1](#to-1241)
- [To 1.24.0](#to-1240)
- [To 1.23.0](#to-1230)
- [To 1.22.0](#to-1220)
- [To 1.21.1](#to-1211)
- [To 1.21.0](#to-1210)
- [To 1.20.0](#to-1200)
- [To 1.19.1](#to-1191)
- [To 1.19.0](#to-1190)
- [To 1.18.0](#to-1180)
- [To 1.17.0](#to-1170)
- [To 1.16.2](#to-1162)
- [To 1.16.1](#to-1161)
- [To 1.16.0](#to-1160)
- [To 1.15.1](#to-1151)
- [To 1.15.0](#to-1150)
- [To 1.14.1](#to-1141)
- [To 1.14.0](#to-1140)
- [To 1.13.2](#to-1132)
- [To 1.13.1](#to-1131)
- [To 1.13.1](#to-1131-1)
- [To 1.13.0](#to-1130)
- [To 1.12.0](#to-1120)
- [To 1.11.1](#to-1111)
- [To 1.11.0](#to-1110)
- [To 1.10.0](#to-1100)
  - [Configuration (deprecated keys, removed in 2.0)](#configuration-deprecated-keys-removed-in-20)
  - [Template contract](#template-contract)
- [To 1.9.0](#to-190)
- [To 1.8.1](#to-181)
- [To 1.8.0](#to-180)
- [To 1.7.2](#to-172)
- [To 1.7.1](#to-171)
- [To 1.7.0](#to-170)
  - [Forms rendered through Form Types (REQ-TWIG-005)](#forms-rendered-through-form-types-req-twig-005)
- [To 1.6.4](#to-164)
- [To 1.6.3](#to-163)
- [To 1.6.2](#to-162)
- [To 1.6.1](#to-161)
- [To 1.6.0](#to-160)
- [To 1.5.0](#to-150)
- [To 1.4.1](#to-141)
- [To 1.4.0](#to-140)
- [To 1.3.5](#to-135)
- [From 1.3.3 to 1.3.4](#from-133-to-134)
- [To 1.3.3](#to-133)
- [To 1.3.2](#to-132)
- [To 1.3.1](#to-131)
- [To 1.3.0](#to-130)
  - [Twig Extra Bundle (REQ-TWIG-004)](#twig-extra-bundle-req-twig-004)
  - [Twig-CS-Fixer (maintainers)](#twig-cs-fixer-maintainers)
- [To 1.2.0](#to-120)
  - [Behaviour](#behaviour)
- [To 1.1.1](#to-111)
- [To 1.1.0](#to-110)
- [To 1.0.5](#to-105)
- [To 1.0.4](#to-104)
- [To 1.0.3](#to-103)
- [To 1.0.2](#to-102)
- [To 1.0.1](#to-101)
- [To 1.0.0](#to-100)
  - [Requirements](#requirements)
  - [Install / upgrade steps](#install--upgrade-steps)
  - [Optional features](#optional-features)
  - [Breaking changes](#breaking-changes)
  - [FormKitBundle (admin forms)](#formkitbundle-admin-forms)

## Unreleased

## To 1.33.0

From **1.32.0**:

```bash
composer update nowo-tech/wiki-bundle
```

No schema change; every default keeps the 1.32 pages.

- **Edit form in the read layout** (opt-in):

  ```yaml
  nowo_wiki:
      web_ui:
          edit_view:
              layout: read
          page_header:
              edit_toolbar:
                  current_tab: hidden   # optional: no Edit tab while editing
                  back: view            # optional: a "View page" eye button
  ```

  The edit form then uses the page view's layout (tree, reading column, card toolbar, path) with the form in place of
  the body. A host template that extends `page_edit.html.twig` keeps working: `nowo_ui_form_body` (the form fields),
  `wiki_page_header_toolbar` and the header blocks are unchanged; the new block `wiki_page_form_body` holds the whole
  form (default: the UiKit form, `{{ parent() }}` in an override). A host that built its own read-like edit layout
  around the form (a `nowo_ui_content` override with its own `.nowo-wiki-layout`, e.g. a `.wiki-edit-layout` class)
  can drop it: set `edit_view.layout: read` and keep only what is specific to the host. If you style the editor with
  your own Markdown typography, set the `--nowo-wiki-read-*` tokens instead (they now also apply in
  `.nowo-wiki-edit-body`).
- **Page view template**: `nowo_ui_show_sections` now renders through `manage/_read_scaffold.html.twig`; the markup and
  the blocks (`wiki_sidebar`, `wiki_page_body`, `wiki_page_after_body`, `wiki_page_header_toolbar`) are the same. A
  host that overrides `nowo_ui_show_sections` itself is not affected.
- **Breadcrumbs** (`web_ui.breadcrumb_current_label` with tenant routes): the page crumb of the edit, history, compare
  and move pages now carries the page title on tenant routes too. A host that called
  `WikiBreadcrumbParentLabel::remember($request, $title)` itself can pass the route key as the third argument
  (`page_edit`, `page_history`, `page_diff` or `page_move`); without it only the bundle route names are recognised,
  as before.
- Routes loaded by `nowo_wiki` now carry the option `nowo_wiki_route` (`WikiRouteLoader::ROUTE_KEY_OPTION`) with
  their `routes.<key>`; the route cache is rebuilt by `cache:clear` as usual.

## To 1.32.0

From **1.31.1**:

```bash
composer update nowo-tech/wiki-bundle
```

`WikiSpace` gets a nullable `icon` column (`VARCHAR(64)`). The bundle ships no migrations: generate one
(`doctrine:migrations:diff`) or run the statement for your database (adjust the table name if you changed
`table_prefix`):

```sql
-- MySQL / MariaDB
ALTER TABLE wiki_spaces ADD icon VARCHAR(64) DEFAULT NULL;
-- PostgreSQL
ALTER TABLE wiki_spaces ADD icon VARCHAR(64) DEFAULT NULL;
-- SQLite
ALTER TABLE wiki_spaces ADD COLUMN icon VARCHAR(64) DEFAULT NULL;
```

Index rows now show a book icon before the space name by default (`row.icon` is null: the UiKit `book` icon). A
`<prefix>:<name>` icon renders only with `nowo_ui_kit.icon_set: ux_icon` (Symfony UX Icons, `tabler` or `bi` sets); set
`ux_icons.ignore_not_found: true` in the host, because an icon that matches the format but does not exist makes
`ux_icon()` throw `IconNotFoundException` (HTTP 500) in the list and the sidebar. With `symfony/ux-icons` installed,
`WikiSpaceIconType` also rejects such an icon. The sidebar shows the icon (space and page views) only when it is set.

Nothing else changes unless you use the field: `WikiSpaceType` shows it only with `icon_field: true`, and
`nowo_wiki.space_icon.allowed_prefixes` (default `[tabler]`) lists the accepted icon sets. Existing spaces keep a null
icon (the default book icon in the list). Old exports stay importable (the icon is not part of them).

## To 1.31.1

From **1.31.0**:

```bash
composer update nowo-tech/wiki-bundle
```

Target **1.31.1** (patch, security; house rule LQ). From **1.31.0**: `composer update nowo-tech/wiki-bundle`.

**Look-alike owner ids.** Spaces are now matched to their owner exactly (`===` in PHP after every SQL lookup), so this
applies to every host; on MySQL / MariaDB the column also needs the new collation.

1. **Owner ids that are refused.** A team or user id with leading or trailing whitespace, a control or format
   character (zero-width space, bidi marks, BOM), invalid UTF-8 or more than 64 characters owns no space:
   `WikiSpaceAccessResolver` lists nothing for it and `WikiSpaceService::create()` throws `InvalidArgumentException`.
   Integer and UUID ids, e-mails and usernames without such characters are unaffected. Look for existing rows that
   are now unreachable:

   ```sql
   SELECT id, owner_scope_type, owner_scope_id FROM wiki_spaces
    WHERE CAST(owner_scope_id AS BINARY) <> CAST(TRIM(owner_scope_id) AS BINARY)
       OR owner_scope_id REGEXP '[[:cntrl:]]' OR CHAR_LENGTH(owner_scope_id) = 0;
   ```

   The comparison is done on bytes because a PAD SPACE collation (MariaDB's default `utf8mb4_uca1400_ai_ci`,
   `utf8mb4_unicode_ci`, `utf8mb4_general_ci`) calls `alice ` and `alice` equal, so a plain
   `owner_scope_id <> TRIM(owner_scope_id)` misses every id with trailing spaces there.

   Move them to the owner's real id (`UPDATE wiki_spaces SET owner_scope_id = ... WHERE id = ...`); format characters
   such as U+200B are not matched by `[[:cntrl:]]`: compare `CHAR_LENGTH()` with what you expect, or list them in PHP.

2. **Exact matching is case- and accent-sensitive.** Before, on a `*_ci` collation, a space stored for `alice` was
   also listed for the principal `Alice`. Now only the exact id counts. If your user or team resolver returns ids in a
   form that differs from the stored one (case, accents, Unicode normalisation), make it return the stored form, or
   update the rows to the form it returns.

3. **Collation (MySQL / MariaDB only).** `doctrine:schema:update --dump-sql` / `doctrine:migrations:diff` now generate
   the change; by hand (table `<table_prefix>_spaces`, default `wiki_spaces`):

   ```sql
   -- MySQL 8.0.17+ / 8.4
   ALTER TABLE wiki_spaces MODIFY owner_scope_id VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_bin NOT NULL;
   -- MariaDB 10.2+ / 11.x
   ALTER TABLE wiki_spaces MODIFY owner_scope_id VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_nopad_bin NOT NULL;
   -- MySQL 5.7 (PAD SPACE; the PHP re-check covers trailing spaces)
   ALTER TABLE wiki_spaces MODIFY owner_scope_id VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL;
   ```

   The unique key `wiki_space_scope_slug (owner_scope_type, owner_scope_id, slug)` is rebuilt with it and becomes
   exact, so the `ALTER` never fails on existing data (a binary collation only separates values, it never merges
   them). Before running it, check for look-alike owners that already share rows:

   ```sql
   SELECT owner_scope_type, LOWER(TRIM(owner_scope_id)) AS folded,
          GROUP_CONCAT(DISTINCT CONCAT('[', owner_scope_id, '] ', HEX(owner_scope_id)) SEPARATOR ', ') AS spellings
     FROM wiki_spaces GROUP BY owner_scope_type, folded
   HAVING COUNT(DISTINCT CAST(owner_scope_id AS BINARY)) > 1;
   ```

   Each spelling is listed between brackets with its bytes in hex (`[alice ] 616C69636520, [Alice] 416C696365, ...`):
   the hex keeps `DISTINCT` from folding `Alice`, `álice` or `alice ` back into `alice` under the column's collation,
   and shows trailing spaces and invisible characters. Each group lists ids that the old collation treated as one owner: their principals could see each other's spaces.
   After the upgrade each id sees only its own spaces; decide per group which principal owns each space and fix
   `owner_scope_id` (and review what was exposed). PostgreSQL and SQLite need no change. Without the `ALTER` the
   bundle is still safe (the PHP re-check), but two owners that differ only in case cannot both create a space with
   the same slug (the folding unique key refuses the second one).

## To 1.31.0

From **1.30.0**:

```bash
composer update nowo-tech/wiki-bundle
```

Target **1.31.0** (Symfony Beacon wiki UX review). The new options are off by default (`web_ui.page_header.title:
card`, `web_ui.page_meta: card`, `comments.layout: overlay`, `comments.thread_list: uikit`; with
`page_header.tabs: toolbar` the edit form drops its back link and space intro and moves Reset collaboration into the
"more" menu). See [USAGE › 1.31 additions](USAGE.md#131-additions). Changes that apply to everyone:

- **Hidden references in the shared cache.** The viewer-neutral `content_html` of a Milkdown revision (search, excerpts,
  AI context, the API's `content_html`) now stores a hidden page link or host file as an empty
  `<span class="nowo-md-ref nowo-md-ref--hidden"></span>` instead of "Content not visible" translated in the saver's
  locale. Readers of a page are unaffected (the page view renders per viewer, in their locale). Plain text drops the
  placeholder whatever its language, so refresh the search column of older revisions once:
  `bin/console wiki:search:backfill-text --all`. Revisions saved before 1.31 keep the old text in `content_html`
  until they are saved again; an API client that showed that text should treat the hidden span as the marker.
  HTML revisions lose the `nowo-md-ref*` classes on save (an author cannot hide text from search with a forged
  marker); stored ones are cleaned on their next save, and `--all` only drops spans whose class has the exact token.
- **Search excerpts** leave out a leading heading equal to the page title (a page whose body is only that heading has
  an empty excerpt).
- **Task checkboxes.** The sanitizer keeps the disabled checkbox of MilkdownEditorBundle >= 1.17 task items (only as
  the first child of `li.nowo-milkdown-task`, rebuilt from a fixed attribute set). Stored HTML is sanitized again at
  render time (`nowo_wiki_safe_html`) and the page view renders Milkdown revisions per viewer, so nothing needs
  re-saving; caches never contained the input (1.30 stripped it). The bundle's own Markdown render still writes
  task lists as `data-type="taskItem"` items without an input (unchanged).
- **CSS fixes** (wiki.css / wiki.ts; hosts with `web_ui.assets: false` rebuild from the sources): the seamless swap and
  `title: dedupe` skip Crepe's leading `.ProseMirror-widget`; the toolbar tree button's chevron points into the page;
  the card page head has no end padding under 40rem; new read tokens h4–h6 and callouts.
- MilkdownEditorBundle stays `^1.13` (suggested); 1.17 is needed only for its read-only task and callout markup.

## To 1.30.0

From **1.29.1**:

```bash
composer update nowo-tech/wiki-bundle
```

Target **1.30.0**. Nothing to change: every new option is off by default and the 1.29 pages render the same markup.

- A host that overrides `nowo_ui_page_header` of the page view / edit form only to add toolbar buttons can drop the
  override and fill the block `wiki_page_header_toolbar` (or `web_ui.slots.page_header_toolbar`); see
  [USAGE › 1.30 additions](USAGE.md#130-additions).
- Opt in to `web_ui.tree_collapsed_mode`, `web_ui.reading_align`, `web_ui.page_header.title: body`,
  `web_ui.page_header.tabs` and `web_ui.page_header.toolbar_position` as needed
  ([CONFIGURATION](CONFIGURATION.md#page-layout-129)). A host sidebar (`wiki_sidebar` / `web_ui.slots.sidebar`) that
  keeps the rail markup (`id="nowo-wiki-tree"`, `data-nowo-ui-rail-host`, the body `id="nowo-wiki-tree-body"`) works
  with `tree_collapsed_mode: hidden` unchanged.
- A host page repository (not `DoctrineOrmWikiPageRepository`) may implement the new optional
  `WikiPageChildrenFinderInterface` for `web_ui.children`; without it the sub-pages come from `findActiveBySpace()`.
- `web_ui.breadcrumb_current_label` needs BreadcrumbKit >= 2.17 (suggested, not required).
- `title: dedupe` now hides the read-only editor's heading through wiki.css: a host that bundles the assets
  (`web_ui.assets: false`) rebuilds from `wiki.css` / `wiki.ts`.

## To 1.29.1

From **1.29.0**:

```bash
composer update nowo-tech/wiki-bundle
```

Target **1.29.1** (security patch, no configuration change). Update with `composer update nowo-tech/wiki-bundle`.

- The default space checker (`LegacyWikiSpaceAccessChecker`) keeps the memberships it read for one main request only;
  outside a request (console, Messenger) it reads them on every call. A host that changes memberships inside one request
  still calls `reset()` on it, as before.
- If you implement `WikiTeamMembershipResolverInterface`, your resolver decides how teams are read. Where the services
  resetter does not run between requests (a host that removes or bypasses it, `messenger:consume --no-reset`,
  long-running commands), prefer a query over a collection loaded on the user entity: the host EntityManager can keep
  the user, and its loaded collection, across requests. The stock kernel in FrankenPHP worker mode still runs the
  resetter before every request.
- Outside a request (console, Messenger) the default checker keeps no memo, so a loop over many pages pays one lookup
  per check; call it with the page set you need, or keep your own per-run cache.

## To 1.29.0

From **1.28.0**:

```bash
composer update nowo-tech/wiki-bundle
```

## To 1.29.0

From **1.28.x**:

```bash
composer update nowo-tech/wiki-bundle nowo-tech/ui-kit-bundle   # UiKit ^2.48 is required
php bin/console assets:install                                   # wiki.css / wiki.js, UiKit nowo-ui-rail.js, nowo-ui-layout-state.js
```

Every new layout option is **off by default**. Two default changes apply to every host:

1. **Wide content stays in the column.** The page view's main column no longer grows to its widest item: long code
   lines (also in the read-only Milkdown view of editors) and wide tables scroll sideways inside it. Drop host
   workarounds such as `.nowo-wiki-comments-view { min-width: 0 }` / `.nowo-wiki-comments-view .cm-editor { max-width:
   100% }` (Beacon 1.148).
2. **Tables are as wide as their content** instead of `width: 100%`, and each table of the page body is wrapped in a
   scroll box: `<div class="nowo-wiki-table-scroll" data-nowo-ui-scroll-region data-nowo-ui-scroll-label="Table">`
   (Twig filter `nowo_wiki_scroll_tables`, label `wiki.table_scroll`), added after sanitizing. The box scrolls
   sideways; the `<table>` keeps `display: table`, so screen readers still announce a table (audit MA-L1). The body
   carries `data-nowo-ui-rendered-content` and the read view loads UiKit `js/nowo-ui-scroll-region.js`, which makes an
   overflowing box (and code block) a focusable, labelled region (`tabindex="0"`, `role="region"`, `aria-label`).
   To stretch narrow tables again, add `.nowo-wiki-content table { width: 100%; }` (they still scroll in the box). Host
   CSS that targets `.nowo-wiki-content > table` must use `.nowo-wiki-content table` (or
   `.nowo-wiki-table-scroll > table`); host templates that print the body themselves get the box with
   `|nowo_wiki_safe_html|nowo_wiki_scroll_tables('wiki.table_scroll'|trans({}, 'NowoWikiBundle'))`.

Host `page_view.html.twig` overrides that copy `nowo_ui_show_sections` keep working but do not get the new markup
(rail, reading column, rendered-content marker): prefer the new blocks `wiki_page_body` / `wiki_page_after_body` and
`wiki_sidebar`. Host overrides of `_page_tabs.html.twig` still render in the default (`stacked`) header.

Optional — what to adopt (Beacon 1.149):

```yaml
nowo_wiki:
    web_ui:
        tree_rail: true                  # literal boolean
        narrow_tree_position: after      # the tree after the page under 62rem (drop the host `order: 2` rule)
        reading_width: readable          # set --nowo-wiki-reading-width (e.g. 52rem) to keep a host measure
        page_header: { layout: compact, title: dedupe }
    comments:
        dock_min_width: lg               # dock only from 1200 px with the tree; a drawer below
```

- `comments.dock_min_width: lg | xl`: a remembered "open" panel starts closed under the breakpoint (it opens as a
  drawer from the toggle); `?comments=1` links still open it.
- The comments panel state is stored only from the column (desktop) layout: toggling the drawer on a phone or tablet
  no longer changes what a host store (or the session) remembers. A host store that relied on drawer toggles being
  posted sees fewer writes.
- `page_header.layout: compact` moves Move, Delete and Archive into the "More actions" menu (danger items, last);
  end-to-end tests that clicked those buttons must open the menu first.
- `web_ui.narrow_tree_position: after` reorders with CSS `order` under 62rem: the page shows first, but the DOM, the
  focus and the screen-reader order stay tree first (audit MA-I2, WCAG 2.4.3 trade-off; the same as a host
  `order: 2` rule). Keep `before` where that matters; a skip link to the main content helps keyboard users.
- `web_ui.page_header.title: dedupe` keeps a heading that holds more than text (an image, media, math) and keeps the
  `id`s of a dropped heading on empty `<sup class="nowo-wiki-anchor">` elements, so in-page `#…` links still land
  (audit MA-I1).
- `web_ui.tree_rail` uses the same panel state store as the comments (`comments.state_store`, default the session)
  and the same `space_panel_state` route, now registered with the comments **or** the rail. A host store receives the
  new panel name `tree_rail` (`WikiPanelStateStoreInterface::TREE_RAIL`; "open" means collapsed): stores that keep any
  panel name need no change. Firewall / CSRF rules for the route are unchanged.
- Without a session (stateless firewall) the panel and the rail fall back to `localStorage`; the blocking UiKit
  `js/nowo-ui-layout-state.js` is printed in `nowo_ui_styles` (keep that block in the `<head>` of a custom layout).
- Hosts with `web_ui.assets.scripts: false` bundle these UiKit scripts themselves: `js/nowo-ui-rail.js` (rail),
  `js/nowo-ui-scroll-region.js` (read view), `js/nowo-ui-layout-state.js` (blocking, in the head).
- `web_ui.page_header.layout: compact` replaces the tab row (and a host `_page_tabs.html.twig` override) on the read
  and edit views; the comments toggle moves to the end of the header row.

## To 1.28.0

From **1.27.0**:

```bash
composer update nowo-tech/wiki-bundle
```

Target **1.28.0**, from **1.27.0**:

```bash
composer update nowo-tech/wiki-bundle
```

Nothing to do unless you use `editor.milkdown.file_cards: card` **and** your host answers previews (a
`FilePreviewResolverInterface` of your own, or a `preview` template in `nowo_milkdown_editor.markdown.host_schemes`).
Then `view="preview"` cards now show their preview frame on the page view and in comments (Symfony Beacon spec 212
block B). Before updating:

- allow the preview origin in the page's CSP `frame-src` (`'self'` when same-origin); without it the browser blocks
  the frame and the card's *Open* / *Download* links stay usable;
- make sure the preview endpoint itself checks the viewer and answers with `Content-Security-Policy: sandbox`,
  `X-Content-Type-Options: nosniff` and `Content-Disposition: inline` (see MilkdownEditorBundle USAGE, *Previews*);
- a resolver that depends on the API's reach can read it from `WikiRenderContext::current()` (it is called inside the
  render's frame); under a reach the wiki only asks about host-reference cards.

To keep 1.27's plain cards, set `editor.milkdown.file_previews: false`. The REST API's reader Markdown is unchanged
(`[name](url)`, no frame, no *Open* / *Download*). The comment thread JSON `html` carries an empty
`<span class="nowo-wiki-file-frame …">` placeholder where the panel shows the frame (only `nowo_wiki_safe_html`
restores frames). A resolver that returns another `https://` origin is framed: keep `frame-src` strict.

## To 1.27.0

From **1.26.0**:

```bash
composer update nowo-tech/wiki-bundle
```

## To 1.27.0

From **1.26.0**: nothing to change; no schema change. A security user that is your `user_class` entity is stored
exactly as before.

- **Security user that is no Doctrine entity:** it works now when it exposes the persisted user's id through `getId()`;
  otherwise alias `Nowo\WikiBundle\User\WikiUserReferenceResolverInterface` to your own resolver.
- **Adapters:** type-hint `WikiPageServiceInterface`, `WikiSpaceServiceInterface`, `WikiPageLinkServiceInterface`
  instead of the final classes; read `$event->actorId()` instead of casting `$event->actor`.
- **Comment ownership** now also matches a user of a class listed in `accepted_user_classes` whose `getId()` equals
  the author's (before: the user identifier only). Unlisted classes keep the identifier rule only.
- **Fail closed (audit JX-W1):** a security user that is neither `user_class` nor listed in `accepted_user_classes` is
  refused (`AccessDeniedException`) when it writes a revision, link, attachment or comment. Before 1.27 such a user
  failed in Doctrine (no entity) or, if it was another entity class, was silently stored under the same id.

## To 1.26.0

From **1.25.1**:

```bash
composer update nowo-tech/wiki-bundle
```

Target: **1.26.0**. From **1.25.x**:

```bash
composer update nowo-tech/wiki-bundle
```

> **Upgrade before you delete any user.** Until 1.25, `<prefix>_page_revisions.author_id` was `NOT NULL ... ON DELETE
> CASCADE`: deleting a host user deleted every revision they wrote, and pages whose current revision was theirs lost it.
> From 1.26 the column is nullable with `ON DELETE SET NULL`: the revisions stay and the history shows **Former person**
> (no name, no picture; the revision keeps no other trace of them). The code change alone does not protect you: **the foreign key
> must be changed in your database**.

**Code (BC note).** `WikiPageRevision::getAuthor()` returns `?object` (was `object`): null for a revision whose author
was deleted. Code of yours that calls it (listeners of `nowo_wiki.page.saved`, exports, reports) must handle null. The
REST API already declared `author` nullable and answers `null` for such revisions. A custom `page_history.html.twig`
should print `'wiki.history.former_person'|trans` when `row.author_deleted` is true.

**Schema (required).** One column and its foreign key, nothing else. `bin/console doctrine:migrations:diff` generates it
on MySQL / MariaDB and PostgreSQL; or apply the SQL below. **On SQLite, do not use a generated migration nor
`doctrine:schema:update`:** use only the SQLite script below (see there why). Apply the schema in the same deployment
as the code: 1.25 code keeps working on the new schema only until the first user deletion (see *Rolling back*). Use
your `table_prefix`, your user table and the type of its id, and your foreign key's name.

The name Doctrine generates for the default table `wiki_page_revisions` is **`FK_6C9030C0F675F31B`** (it depends on the
table name only). If your schema was written by hand it may differ; read it first:

```sql
-- MySQL, MariaDB
SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wiki_page_revisions' AND COLUMN_NAME = 'author_id'
   AND REFERENCED_TABLE_NAME IS NOT NULL;
-- PostgreSQL
SELECT c.conname FROM pg_constraint c
  JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = ANY (c.conkey)
 WHERE c.conrelid = 'wiki_page_revisions'::regclass AND c.contype = 'f' AND a.attname = 'author_id';
```

MySQL / MariaDB, user table `user` with `INT` ids (for example Symfony Beacon, whose name is the Doctrine default):

```sql
ALTER TABLE wiki_page_revisions DROP FOREIGN KEY FK_6C9030C0F675F31B;
ALTER TABLE wiki_page_revisions CHANGE author_id author_id INT DEFAULT NULL;
ALTER TABLE wiki_page_revisions ADD CONSTRAINT FK_6C9030C0F675F31B FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE SET NULL;
```

The `CHANGE` line must repeat the exact type of your user id: `INT UNSIGNED`, `BIGINT`, `VARCHAR(36)` (string / UUID
ids, e.g. `CHANGE author_id author_id VARCHAR(36) DEFAULT NULL`), `BINARY(16)`… (`SHOW CREATE TABLE wiki_page_revisions`
shows it). The demo ships it as `demo/symfony8/migrations/Version20261109120000.php` (table `users`, its own key name
`FK_WIKI_REVISION_AUTHOR`).

PostgreSQL (the type is kept; quote `"user"`, a reserved word there):

```sql
ALTER TABLE wiki_page_revisions DROP CONSTRAINT FK_6C9030C0F675F31B;
ALTER TABLE wiki_page_revisions ALTER author_id DROP NOT NULL;
ALTER TABLE wiki_page_revisions ADD CONSTRAINT FK_6C9030C0F675F31B FOREIGN KEY (author_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE;
```

SQLite cannot alter a foreign key: the table is rebuilt. **Turn `foreign_keys` off first, outside any transaction**:
with it on, `DROP TABLE` itself clears every page's current revision and deletes every named version. That is why a
`doctrine:migrations:diff` migration or `doctrine:schema:update` must not be used here: they issue the same rebuild
inside a transaction, where `PRAGMA foreign_keys` has no effect, so with foreign keys enabled they destroy those rows
(audit JT-L2). Run this script as is (e.g. with the `sqlite3` shell), not from a transactional migration. Replace `users` with your user table and `VARCHAR(36)` in `author_id` with your id type (`INTEGER` for
integer ids):

```sql
PRAGMA foreign_keys = OFF;
BEGIN;
CREATE TEMPORARY TABLE __temp__wiki_page_revisions AS SELECT id, created_at, format, content_text, revision_number, content_html, content_markdown, page_id, author_id FROM wiki_page_revisions;
DROP TABLE wiki_page_revisions;
CREATE TABLE wiki_page_revisions (id VARCHAR(36) NOT NULL, created_at DATETIME NOT NULL, format VARCHAR(16) DEFAULT 'html' NOT NULL, content_text CLOB DEFAULT NULL, revision_number INTEGER NOT NULL, content_html CLOB NOT NULL, content_markdown CLOB DEFAULT NULL, page_id VARCHAR(36) NOT NULL, author_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY (id), CONSTRAINT FK_6C9030C0C4663E4 FOREIGN KEY (page_id) REFERENCES wiki_pages (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6C9030C0F675F31B FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE);
INSERT INTO wiki_page_revisions (id, created_at, format, content_text, revision_number, content_html, content_markdown, page_id, author_id) SELECT id, created_at, format, content_text, revision_number, content_html, content_markdown, page_id, author_id FROM __temp__wiki_page_revisions;
DROP TABLE __temp__wiki_page_revisions;
CREATE UNIQUE INDEX wiki_revision_page_number ON wiki_page_revisions (page_id, revision_number);
CREATE INDEX IDX_6C9030C0C4663E4 ON wiki_page_revisions (page_id);
CREATE INDEX IDX_6C9030C0F675F31B ON wiki_page_revisions (author_id);
PRAGMA foreign_key_check;
COMMIT;
PRAGMA foreign_keys = ON;
```

`PRAGMA foreign_key_check` must return no rows **before you `COMMIT`**: if it reports any (for example `users` not
replaced by your user table), run `ROLLBACK;` instead, fix the script and run it again. With `wiki_pages` renamed by your prefix, rename it in the `page_id`
constraint too.

**Pages that already lost their current revision (optional repair).** Revisions deleted by an earlier user deletion are
gone (only a backup brings them back). A page whose current revision was deleted that way has `current_revision_id`
`NULL` while older revisions of it remain; this points it at its newest remaining revision (same SQL on MySQL, MariaDB,
PostgreSQL and SQLite; a page legitimately has a current revision from its creation on, so no other page matches):

```sql
UPDATE wiki_pages SET current_revision_id = (
    SELECT r.id FROM wiki_page_revisions r WHERE r.page_id = wiki_pages.id ORDER BY r.revision_number DESC LIMIT 1
) WHERE current_revision_id IS NULL
  AND EXISTS (SELECT 1 FROM wiki_page_revisions r2 WHERE r2.page_id = wiki_pages.id);
```

The page then shows that older text until someone saves it again. List the pages it will touch first:

```sql
SELECT p.id, p.title FROM wiki_pages p
 WHERE p.current_revision_id IS NULL AND EXISTS (SELECT 1 FROM wiki_page_revisions r WHERE r.page_id = p.id);
```

**Rolling back** to 1.25 code is safe **only until the first user deletion** under the new schema. After that, 1.25
cannot load a revision without an author (its `$author` is a non-nullable typed property): the history, the REST
revision list and head, and a save conflict on such a page answer 500 (audit JT-L1). Once a user was deleted, stay on
1.26 or later. Reverting the schema itself also needs every `author_id` set: a revision of a deleted author blocks
`NOT NULL` (the demo's `down()` refuses then).

**Known limitation (fixed in 1.26.1).** With co-editing on, `<prefix>_page_collab_documents.seeded_by` holds the user
identifier (usually the e-mail) of the tab that seeded a page's shared log, and deleting the user does not clear it: it
stays until the next seed or a co-editing reset (audit JT-L3, older than 1.26). For an erasure now, clear it by hand:
`UPDATE wiki_page_collab_documents SET seeded_by = NULL WHERE seeded_by = '<identifier>' OR seeded_by LIKE '<identifier>#%';`.

**Other relations to the user** are unchanged: links, attachments, comment threads and comments already keep their row
with the user `SET NULL`; a comment reaction is deleted with its user (it is the user's own mark).

## To 1.25.1

From **1.25.0**:

```bash
composer update nowo-tech/wiki-bundle
```

## To 1.25.0

From **1.24.1**: `composer update nowo-tech/wiki-bundle`. Milkdown stays at **^1.13**.

- **Edit and History buttons (WK-6).** `page.header.actions` of the page view no longer has Edit (`data-testid`
  `wiki-edit`) and History (`wiki-history`): they are the tabs of `page.header.tabs` (`wiki-tab-edit`,
  `wiki-tab-history`), with the same rights. Nothing to do if your page view prints the tab row (the bundle's does). If
  you filtered them out of the header yourself, the filter can go; if your own page view template prints the header
  but not `page.header.tabs`, print the tab row (`@NowoWikiBundle/manage/_page_tabs.html.twig`) or your own Edit /
  History links.
- **File cards drawn by Milkdown (WK-8, opt-in).** With `editor.type: milkdown`, set

  ```yaml
  nowo_wiki:
      editor:
          milkdown:
              file_cards: card   # default: link (as 1.24)
  ```

  to draw `::file` cards in the page body, comments and the API reader view as Milkdown's file card, linked to the URL
  of Milkdown's host file resolution (`host_files`; refused files read *Content not visible*).

  - **`card` protects nothing by itself** (audit IK-I4). Refused files are hidden only while host files are resolved:
    `host_files` is `auto` with Milkdown's `host_url_patterns` set, or `on`. Without them, a card links its written
    `href` with the author's label, exactly like `link`.
  - **Before you delete your own `file` renderer** (audit IK-L1): if you registered a `nowo_milkdown_editor.directive_renderer`
    for `file` to get the card and to check file access, it may match more URL forms than Milkdown does (any host,
    explicit default ports, userinfo, dot segments). Remove it only once **both** hold:
    1. you run nowo-tech/milkdown-editor-bundle **≥ 1.14**, which reads browser-normalised forms (`:443` / `:80`,
       trailing dot, `user@`, `/./`, `/../`, `%2e`, repeated slashes) as the own URL (audit IJ-I10). 1.13 does not;
    2. every host your file links use is in `nowo_milkdown_editor.markdown.host_url_hosts` (1.14 accepts
       `%env(key:host:url:DEFAULT_URI)%`), in addition to the request's own host. A link to a host not listed there
       stays an ordinary link.

    As long as your renderer supports `file`, it still draws every card and Milkdown does not resolve the card's `href`.
    WikiBundle itself still requires only Milkdown `^1.13`; the ≥ 1.14 requirement applies to removing your renderer.
  - Your `HostFileResolverInterface` (a `ContextualHostFileResolverInterface` under the API's reach) decides who sees
    each card, and Milkdown's `host_url_patterns` must cover the URLs your cards use (for example
    `/files/<uuid>/(original|preview|thumb|s128|s64)`).
  - **What a visible card shows** (audit IK-I5): with a `url: written` pattern, the author's label and URL, as with
    `link`. With a `url: resolved` pattern or a scheme value (`app-file:<id>`), the resolver's URL and the file's real
    name and type from the resolver (`ResolvedFile`), in the UI and in the API reader view; this differs from `link` mode
    and from `content_html`, which keep the author's label.
  - The neutral `content_html` render keeps the plain link. Previews (`view="preview"`) read as the card: the wiki
    render has no preview resolver.

## To 1.24.1

From **1.24.0**. Needs `nowo-tech/milkdown-editor-bundle` **^1.13**: `composer update nowo-tech/wiki-bundle nowo-tech/milkdown-editor-bundle`.
Nothing changes until Milkdown's `host_url_patterns` is set, or `editor.milkdown.host_files` is `on`.

**Hosts whose pages reference files by URL (Beacon):**

1. Declare the file URLs in Milkdown so they are host references:

   ```yaml
   nowo_milkdown_editor:
       markdown:
           host_schemes:
               beacon-file: { file: /files/{id}/original, image: /files/{id}/preview }
           host_url_patterns:
               # Every variant Beacon's file route serves (FileDownloadController): a form no pattern covers stays a plain link.
               - { pattern: '^/files/(?<id>[0-9a-f-]{36})/(?:original|preview|thumb|s128|s64)$', scheme: beacon-file }   # url: written (default)
           # Absolute and protocol-relative URLs of these hosts (and of the request's host) are matched as their path, so
           # https://beacon.example.com/files/<uuid>/original pasted from the address bar, and its GFM autolink, are covered.
           host_url_hosts: [beacon.example.com]
           host_refused_links: hidden   # refused beacon-file: links lose their text too (audit IJ-I8)
   ```

   Milkdown also drops the query and fragment, percent-decodes the path once and lower-cases uuids before matching
   (`/files/<UUID>/original?download=1` matches). It does not match file URLs written as plain text or inside escaped raw
   HTML: those are the author's text, not references.

2. Point Milkdown's `HostFileResolverInterface` at a resolver that implements `ContextualHostFileResolverInterface`.
   In `resolveFilesInContext($references, $context)`, answer only files the current person may read. When `$context`
   is a `WikiRenderFrame`, also drop every file whose wiki space fails `$context->reaches($space)`. A missing answer
   reads *Content not visible*.
3. Without step 2, the default template resolver resolves every file in the UI (unchanged output) and **none** under
   an API reach.

**What changes in the UI once host files are on** (`auto` with patterns, or `on`); a person who may see every file of
a page sees the same page as before (audit IJ-I9):

- Files the resolver refuses for the person (if your resolver checks the person, as it should) read *Content not
  visible*, without alt text, link text or card name, where they used to show as broken images or links.
- A resolver that throws hides every host file of that page (logged by class); more than Milkdown's `max_references`
  (500) distinct files on one page hide the rest.
- `beacon-file:<id>` links and images are resolved to their template URLs; before, the wiki's renderer left them
  unresolved and the sanitizer dropped them to text.
- Leaf directives whose `url`, `href` or `link` names a host file (`::download{href=…}`, `::file{url=…}`) go through the
  resolver too and read *Content not visible* when refused; a `::file` whose `href` is a host file links only that
  `href` (audit IJ-L2).
- A host `DirectiveRendererInterface` for `file` (or any directive) takes over: Milkdown then skips card resolution, and
  your renderer must check the person and `WikiRenderFrame::reaches()` itself.

## To 1.24.0

From **1.23.0**:

```bash
composer update nowo-tech/wiki-bundle
```

Nothing to do for most hosts: the REST API is off by default and every other change is additive.

- **Co-editing hosts:** listeners of `WikiEvents::PAGE_SAVED`, `PAGE_CREATED` and `PAGE_RESTORED` now run after the co-editing log's transaction committed (and not at all when it rolled back). A listener that relied on running inside it should listen to the new `WikiEvents::PAGE_WRITTEN` (same event object), which keeps the old timing. A listener that writes to the database no longer shares the save's transaction: if it fails, the save stays and its own data (backlinks, file references) is stale until the next save; make it idempotent and retry-safe.
- **API hosts:** the build refuses `api.enabled` with `AllowAllWikiAccessChecker` as the access checker or a page repository without `WikiPageLockInterface`. JSON writes need `Content-Type: application/json`. Create the `<table_prefix>_api_idempotency` table (`doctrine:migrations:diff`) unless you set `api.hooks.idempotency_store`; schedule `nowo:wiki:api:purge-idempotency`; call `forget(WikiApiIdempotencyListener::scope($principal))` on token revocation and account erasure.

- `WikiPageService::saveRevision()` and `restore()` have a new optional last argument. A host that extends neither is unaffected. A host that passes an expected revision needs a page repository implementing `WikiPageLockInterface` (the bundle's Doctrine repository does); otherwise the call throws a `LogicException`.
- `WikiRevisionBodyRenderer`, `WikiCommentService` and `WikiMilkdownPageResolver` take an optional `WikiRenderContext` (wired by the bundle). Host resolvers and directive renderers can read `WikiRenderContext::current()` for the space and page being rendered instead of the request's `spaceSlug` / `pageSlug`.
- A host page-link resolver (`editor.milkdown.page_resolver`) should honour `WikiRenderContext::current()?->reaches($space)` and may record resolved links with `recordPageLink()`; the REST API relies on both to keep a token within its reach.

## To 1.23.0

From **1.22.0**:

```bash
composer update nowo-tech/wiki-bundle
```

> **Upgrade if you use co-editing.** In every earlier version, with every Milkdown, a tab that came back after the shared log was dropped could undo newer text, including a confirmed "Replace for everyone", and nobody was asked. Every revision stays in the history. This release closes it, with Milkdown 1.12 or later (audit HV-M1). No GHSA.

**Before you deploy the code, with co-editing on, add the column:**

```sql
-- MySQL, MariaDB, SQLite
ALTER TABLE wiki_page_collab_documents ADD log_epoch VARCHAR(32) DEFAULT NULL;
-- PostgreSQL
ALTER TABLE wiki_page_collab_documents ADD log_epoch VARCHAR(32) DEFAULT NULL;
-- SQL Server
ALTER TABLE wiki_page_collab_documents ADD log_epoch NVARCHAR(32);
-- Oracle
ALTER TABLE wiki_page_collab_documents ADD (log_epoch VARCHAR2(32) DEFAULT NULL);
```

Use your `table_prefix`. Existing logs are named the first time a client reads their state. Rolling back is safe: the old code ignores the column.

- **Milkdown:** `conflict` on `<1.12.0`. A tab still open with older JavaScript behaves as before until it reloads.
- **`nowo_wiki.page.saved` listeners** now also run inside the transaction of a session save from Milkdown 1.12, with the log locked, and of a confirmed Replace. Keep them fast. If one throws, nothing is saved.
- **`state`** answers `epoch`, and hub messages `u` and `r` carry `e`. A custom relay that rebuilds messages must keep it.
- **A host's own `collaboration.store`** keeps today's behaviour. Implement `WikiCollabEpochStoreInterface` to close it.
- **Residual:** a tab that never bound (hub down, standalone), opened before a Replace, or reloaded in Firefox, which restores its old text, with nothing unsaved on the new log, saves with no ask: plain last-write-wins, to be closed by `base_revision` in the REST API.

With co-editing enabled:

- **`POST <url>/update`** answers `200` with JSON `{"s": n, "p": m}` instead of `204` with the bundled store, and each published update carries `s` and `p`. Milkdown before 1.11 ignores both. A host that checks for `204`, or a custom publisher or relay that rebuilds the message, must allow them.
- **To close the window** where an update landing during a session save counts as saved, use Milkdown 1.11 or later. Without it nothing changes. A save made right after typing, before the server has answered the last update, counts that update as unsaved, so the next save outside the session asks once: a false alarm that loses nothing.
- **A host's own `collaboration.store`** keeps working, with `204` and the window open. Implement `WikiCollabNumberedStoreInterface` to close it.
- **`state.seq`** after a compaction is now the last update's number, not the snapshot row's. Nothing to do; a client reloads its state on connect.

## To 1.22.0

From **1.21.1**:

```bash
composer update nowo-tech/wiki-bundle
```

With co-editing enabled:

- **Schema:** one column, `saved_seq`, on `<prefix>_page_collab_documents`. Run `doctrine:migrations:diff`. Until a log is saved from a session or reset, its updates count as unsaved, which errs towards asking.
- **Behaviour:** a page form saved outside the live session while the shared log holds updates no version has now answers 409 and saves nothing, with the text kept in the form and a way to resolve it. A log that only holds the seed counts as unsaved, because its update cannot be told apart from text typed with it; the person chooses "Replace for everyone" and nothing is lost. `collaboration.save_conflict: false` keeps the old behaviour, which loses those updates.
- **Templates:** a host template that replaces `nowo_ui_content` without `parent()` must print `collab_conflict` (the banner and the "Replace for everyone" button: include `@NowoWikiBundle/manage/_collab_conflict.html.twig` with `collab_conflict` and `bundle`) and pass `page.formAttributes` to `form_start`; otherwise the person gets a 409 with no message beyond the flash and no way out.
- **Order:** add the column before deploying the code.
- **Listeners:** with co-editing on, a `nowo_wiki.page.saved` listener runs inside the transaction of a save outside the session or a restore, with the page's log locked. Keep it fast. If it throws, nothing is saved.
- **Exceptions:** a host calling `WikiPageService::saveRevision()`, `restore()` or `convertToMarkdown()` and catching `UniqueConstraintViolationException` or `RetryableException` for a lost race must catch `Nowo\WikiBundle\Service\WikiRevisionCollisionException` too. Nothing was written, and the database error is its `getPrevious()`. A failure after the revision is written, or in a listener, keeps its own type.
- **Restore:** a restore over unsaved updates now asks on a page of its own, also a 409, and `restoreRevision()` returns `Response`.
- A host with its own `collaboration.store` needs nothing: without `savedSeq` every update counts as unsaved. To avoid asking after a session save, fill `savedSeq` in `WikiCollabState` and record it in `markSaved()`; implement `WikiCollabLockingStoreInterface` so an update cannot land between the check and the drop.

## To 1.21.1

From **1.21.0**:

```bash
composer update nowo-tech/wiki-bundle
```

**Security (audit GV-M2).** Every published version with `WikiArchiveHelper`, from 1.0.0 on, writes what a ZIP really holds, not what it declares (measured on 1.0.0, 1.4.1, 1.5.0, 1.20.0 and 1.21.0). A user with the `Import` right can write about 1000 times the upload to the temp dir. Until you upgrade:

- Limit who has the `Import` right, or set `import_export.enabled: false`.
- Lower `import_export.max_upload_bytes`, which lowers the amount in proportion.
- Give `sys_temp_dir` its own volume or quota, so it cannot fill the disk the rest of the host needs, and watch it for `wiki-import-*`.

What changes:

- An import now refuses a Markdown document over 8 MiB, with the usual import error and nothing written. To accept
  larger ones, raise `import_export.max_document_bytes`. A larger document needs far more memory than its size: 8 MiB
  peaked at about 130 MiB and 16 MiB at about 200 MiB, with `editor.type: milkdown`.
- An archive whose Markdown documents, read twice over, do not fit in what is left of `memory_limit` is refused the same
  way. An import that writes those documents could not finish; raise `memory_limit` if the archive holds Markdown the
  import does not need.
- A front matter over 64 KiB that starts like YAML is refused the same way. Two `---` rules further apart than that are
  a long body, as before. A file opening with `---`, then a line like `Note: …`, and another `---` over 64 KiB later
  looks like YAML, so it refuses the whole archive; a blank line after the first `---` makes it a body.
- An archive naming more than 10 000 distinct directories is refused the same way, before anything is written.
- A ZIP entry that cannot be read (encrypted, corrupt) or written is an import error, no longer an exception.
- `WikiArchiveHelper::extractZip()` now extracts only `.md` files and the directories the archive names. A host calling
  it directly for other files must extract them itself.
- Nothing else to do. A fatal error leaves nothing in the temp dir, and each import removes what a process killed
  outright left there (`wiki-import-<hex>`, `wiki-upload-<hex>.zip`, `wiki-export-*`, older than one hour).

## To 1.21.0

From **1.20.0**:

```bash
composer update nowo-tech/wiki-bundle
php bin/console assets:install
```

- No BC break: every new option defaults to the 1.20 behaviour, `nowo_wiki.quota.check` changes nothing without a
  listener, and the new tree tokens default to the 1.20 values. Refresh the installed `wiki.js` and `wiki.css`.
- A host overriding `manage/space.html.twig` or `manage/_comments_panel_body.html.twig` under
  `templates/bundles/NowoWikiBundle/` does not get the new parts until it merges them. Prefer the new blocks and
  `web_ui.slots` over a copy ([CONFIGURATION › Host hooks](CONFIGURATION.md#host-hooks-121)).
- Plan limits: replace a `kernel.controller` listener with one on `nowo_wiki.quota.check`. It also covers `trash_restore`
  and the copy of a version (`page_create`); an import reports its Markdown documents as `count`. The CLI commands
  (`wiki:import`) do not dispatch it, and page edits, revision restore and comments are not metered.
- The Twig global `nowo_wiki_web_ui` is now an `ArrayObject` service with the same keys. Templates read it as before,
  except `|slice`, which loses the keys; a host passing it to PHP code that type-hints `array` must convert it. It is one
  shared object: do not write to it, since in worker mode the change stays across requests.
- A route guard that hides the index or the search without a space: replace it with `web_ui.space_index: false` and
  `search.scope: space`. `space_index` must be a literal boolean (an env placeholder is refused at compile time).
- A forked comments panel: delete it and set `comments.moderation_panel: true` and `comments.error_notices: true`.
- Hosts that bundle the assets (`web_ui.assets: false`) must add `wiki-comments-moderation.ts` through `wiki.ts`, and the
  UiKit scripts the pages no longer print (see [CONFIGURATION › Host hooks](CONFIGURATION.md#host-hooks-121)).

## To 1.20.0

From **1.19.1**:

```bash
composer update nowo-tech/wiki-bundle
```

- No BC break; the new options are off by default and the page view is unchanged without them.
- To list a page's files (Milkdown 1.8 file cards and images) set `page_files.enabled: true`; the card uses the
  existing "Used in" list classes plus `.nowo-wiki-files*` (copy `wiki.css` with `assets:install`).
- To delete stored files when pages leave the trash for good, set `page_files.purge_event: true` (with
  `trash.enabled`) and listen to `nowo_wiki.page.files_purged`. The event lists every URL the purged pages' revisions
  referred to: **check that no other page, template or object still refers to a file** before deleting it (copied
  pages and pasted blocks keep the same URL), and map URLs to your storage keys yourself.
- Hosts that override `page_view.html.twig` and want the list: render `page_files` like `used_in` (see the bundle
  template).

## To 1.19.1

From **1.19.0**:

```bash
composer update nowo-tech/wiki-bundle
```

## To 1.19.0

From **1.18.0**:

```bash
composer update nowo-tech/wiki-bundle nowo-tech/ui-kit-bundle   # ui-kit-bundle ^2.31 is required
php bin/console assets:install
```

- **Visual change, no configuration needed.** UiKit 2.31 gives the wiki's confirm dialogs (delete, archive, restore,
  co-editing reset, purge) the dialog anatomy and every dialog its content's height (capped). The markup of the wiki
  is unchanged. A host that wants the 2.29 / 2.30 fixed height back sets `nowo_ui_kit.theme.dialog.height: size`
  (UiKit UPGRADING).

## To 1.18.0

From **1.17.0**; no breaking PHP API change, no migration.

```bash
composer update nowo-tech/ui-kit-bundle nowo-tech/wiki-bundle   # UiKit ^2.29 is required
php bin/console assets:install
```

- **Visual change:** the AI question dialog is the medium dialog (it was `lg`, now the UiKit 2.29 medium size). A host
  that styled `#nowo-wiki-ai-dialog` (width / height) should drop that and set the shared tokens
  `--nowo-dialog-width-md` / `--nowo-dialog-height-md` instead.
- **Page tab icons:** the Page / Edit / History tabs now carry icons; set `nowo_wiki.web_ui.tab_icons.<tab>` to rename
  them or to `~` to drop one. A host that overrode `manage/_page_tabs.html.twig` only to add icons can remove its copy
  (Symfony Beacon: drop its `_page_tabs` override once it requires this release).
- **Custom `WikiPageFactory` wiring:** the constructor has a new last argument `$tabIcons` (defaults to
  `WikiPageFactory::TAB_ICONS`); a host that builds the service by hand needs no change.

## To 1.17.0

From **1.16.2**:

```bash
composer update nowo-tech/wiki-bundle
```

No breaking change, no migration. Run `assets:install` (new `wiki.js`).

- **Mentions on erasure:** add `WikiCommentService::eraseMentions([$handle], $label)` to the personal-data eraser that
  already calls `eraseAuthor()` (call it first), before the eraser that rewrites the person's handle. It returns a
  countable `WikiCommentMentionErasure`; rows listed in `skippedCommentIds` / `skippedThreadIds` could not be scanned
  and still hold the mention. A host comment store that
  replaces the Doctrine one must implement `WikiCommentMentionRepositoryInterface` to use it. The label must not be
  able to render as a mention (`@deleted-user` is refused; keep `@…` or use `[deleted user]`). Only comments are
  covered: mentions in page content and history stay.
- **CLI erasure:** `wiki:comments:erase-user` now also erases the user's mentions: pass `--handle=<handle>` (or give the
  user class a `getHandle()`); without a handle it warns that mentions were kept.
- **Code blocks:** a host script that made wiki `<pre>` elements focusable can be dropped; `wiki.js` now does it
  (attributes the markup already sets are kept).

## To 1.16.2

From **1.16.1**:

```bash
composer update nowo-tech/wiki-bundle
```

No configuration or schema change. With `editor.type: milkdown` the read view now renders footnotes, heading ids
(`nowo-h-<slug>`), table alignment classes (`nowo-wiki-align--*`) and a text / link fallback for leaf directives no
host renders; a template that ships its own CSS instead of `wiki.css` may want to style `.nowo-wiki-align--*`,
`.nowo-md-footnotes` and `.nowo-wiki-directive`. The sanitizer keeps an `id` only when it is one of those anchors: an
`id` a host allowed through `sanitizer.extra_allowed_attributes` (or `extra_allowed_elements`) is now dropped, and
listing it logs a deprecation at container build: remove it. `name` can no longer be allowed at all (the container
refuses to compile, like `style` or `on*`): remove it from `sanitizer.extra_allowed_*`.

The viewer-neutral `content_html` (search excerpts, AI context) now holds the fallback text of leaf directives no host
renders (the label, or `name · key: value`; URL, `id` and `class` attributes excluded) where it had an empty `div`:
keep secrets out of directive attributes. The wiki registers its own `LeafDirective` renderer (priority 10): a host
`nowo_wiki.markdown_extension` that rendered `LeafDirective` itself needs a higher priority (or a
`nowo_milkdown_editor.directive_renderer`).

## To 1.16.1

From **1.16.0**: `composer update nowo-tech/wiki-bundle`. No schema change.

- **Caps:** `comments.limits.threads_per_page` now counts open threads only, the new
  `comments.limits.user_threads_per_page` (default 50) caps the open threads one user starts on one page, and the new
  `comments.limits.total_threads_per_page` (default 1000) caps every thread of a page, resolved ones included. Beyond
  them a new thread answers 422 `limit_reached`; reopening a thread checks the two open-thread caps too. Pages that
  already hold more threads than the total keep them (moderators delete threads to make room).
- **Custom comment stores:** the three per-author queries added in 1.16.1 (`countOpenBy()`, `reactionsBy()`,
  `threadsBy()`) live in a separate `WikiCommentAuthorRepositoryInterface`, so a host implementation of
  `WikiCommentRepositoryInterface` written for 1.16.0 keeps working: without them the per-user thread cap is not
  applied and `exportAuthorData()` holds the comments only. Implement the new interface too to get both. `threads()`
  now returns open threads first (then resolved ones), each group oldest first: custom stores should do the same.
- **Moderation:** `DELETE …/comments/threads/{id}` deletes a thread with every comment and reaction for viewers with
  `WikiAction::ModerateComments` (204) and dispatches `nowo_wiki.comment.thread_deleted` (`WikiCommentEvent`, comment
  null). Add it to notification listeners that react to deletions.
- **Notification quotes:** show `WikiCommentService::visibleQuote($event->thread)`, never `$event->thread->getQuote()`:
  the stored quote keeps text that may have left the page since (`visibleQuote()` blanks it).
- **Privacy:** `exportAuthorData($user)` (comments, reactions, threads started or resolved) completes `exportAuthor()`;
  `wiki:comments:erase-user --export` prints it. Mentions of the user in other people's comments are those people's
  text: they are neither exported nor erased. Listen to `nowo_wiki.comment.author_erased`
  (`WikiCommentAuthorErasedEvent`) to drop your own copies (indexes, caches, notification history). The command now
  asks for confirmation; scripts pass `--force` or `--no-interaction`.
- **Server-rendered list:** each listed thread shows its opening comment and the latest 19; an override of
  `_comments_panel_body.html.twig` gets `thread.more` (the number of earlier comments).

## To 1.16.0

From **1.15.1**:

```bash
composer require nowo-tech/ui-kit-bundle:^2.26 nowo-tech/wiki-bundle:^1.16
```

**Always (no switch):**

- **The read and edit views have a tab row** (*Page* / *Edit* / *History*) under the title (B-Q1). If you override
  `page_view.html.twig` or `page_edit.html.twig`, compare them with the bundle's: both now fill
  `nowo_ui_page_header` (the UiKit header without its tabs, then `manage/_page_tabs.html.twig`) and, with comments,
  `nowo_ui_content`. The header actions (Edit, History…) are unchanged. New strings `wiki.tabs.*`.
- **Search matches plain text.** `<prefix>_page_revisions` gains a nullable `content_text` column (public API: filter
  on it in SQL instead of `content_markdown`). Generate the migration, run it, then fill the column once:

  ```bash
  bin/console doctrine:migrations:diff && bin/console doctrine:migrations:migrate
  bin/console wiki:search:backfill-text          # --dry-run to count, --batch-size=500, --all to recompute
  ```

  Until a revision is backfilled it is matched the 1.15 way (its Markdown or HTML). Searching for Markdown
  punctuation or tags (`**bold**`, `<strong>`) or for directive attributes (`bulb`, `type="info"`) no longer finds
  pages. Hosts with their own `LIKE` on `content_markdown` (Beacon's `WikiPageSearchProvider`) should switch to
  `content_text`; `Nowo\WikiBundle\Search\WikiPlainText::fromMarkdown()` reduces Markdown the same way.
- **Avatars.** Alias `Nowo\WikiBundle\Avatar\WikiUserAvatarResolverInterface` to your service to show pictures
  (history authors, comment authors). URLs must be `https://` without userinfo or a same-origin path starting with
  one `/`, stable and access-checked when fetched (the UiKit avatar URL policy drops anything else). Pictures appear
  only where the real name is shown (`history.show_names: true` and a name that passes `isSafeName()`); otherwise the
  resolver is never called. If you override `page_history.html.twig`, the rows gain `author_named` / `author_avatar`.
- UiKit `^2.26` is required (side panel, avatar partial and URL policy).

**Comments (opt-in, `nowo_wiki.comments.enabled: true`, a literal boolean):**

> [!WARNING]
> **Exhaustive `match` checkers fail with a 500.** `WikiAction` gains four cases (`ViewComments`, `Comment`,
> `ResolveComment`, `ModerateComments`). A host `WikiSpaceAccessCheckerInterface` whose `isGranted()` uses an exhaustive
> `match ($action)` without a `default` arm (Beacon's `BeaconWikiAccessChecker`) throws `UnhandledMatchError` as soon
> as comments are enabled — on every page view, since the page asks for `ViewComments`. Add the four cases (or a
> `default => false` arm) **before** turning comments on. With comments off they are never asked.

- Needs `editor.type: milkdown` and `nowo-tech/milkdown-editor-bundle` `^1.6` (the container refuses to build
  otherwise), and a rate limiter:

  ```yaml
  framework:
      rate_limiter:
          wiki_comments: { policy: sliding_window, limit: 60, interval: '1 minute' }
  nowo_wiki:
      comments:
          enabled: true
          rate_limiter: limiter.wiki_comments   # per user, across all pages
          # page_rate_limiter: limiter.wiki_comments_page   # optional: a second budget per user and page
          # limits: {threads_per_page: 500, comments_per_thread: 500, reactions_per_comment: 20, user_reactions_per_comment: 10}
          # state_store: app.wiki_panel_state   # a WikiPanelStateStoreInterface (default: the session)
          # shortcut: 'Alt+Shift+C'              # none by default
  ```

- **Schema:** three tables, `<prefix>_comment_threads`, `<prefix>_comments` and `<prefix>_comment_reactions`
  (`doctrine:migrations:diff` picks them up; they are not in the schema while comments are off).
- **Routes:** two route keys, `page_comments` (`…/pages/{pageSlug}/comments/{path}`; a custom path must keep the
  trailing `/{path}`) and `space_panel_state` (`…/spaces/{spaceSlug}/panel-state`). Put them behind the same firewall
  and access rules as the other wiki routes.
- **Rights (default deny):** nobody comments until your `WikiSpaceAccessCheckerInterface` grants
  `WikiAction::ViewComments` (see the toggle, the panel, the threads), `Comment` (start, reply, react; edit / delete
  own), `ResolveComment` and `ModerateComments`. The 1.x legacy mapping denies all four, and `WikiSpaceGuard::grants()`
  never asks for them, so an existing checker is not asked until you turn comments on.
- **Read view and the page source:** by default only viewers who may **edit** the page get the read-only Milkdown
  mount on the read view (inline highlights, the thread component; the response carries the Markdown source and is
  `Cache-Control: private, no-store`). Other viewers with `ViewComments` never receive the source: their panel lists
  the open threads rendered by the server (inline threads with their quote, no highlight in the text) with the
  page-comment form. That is a **user-interface** limit only: replying, reacting and resolving from the read view need
  the editor mount, but the JSON endpoints still accept those writes from any viewer with the matching right
  (`Comment`, `ResolveComment`), as the contract says; the server-rendered list simply offers no buttons for them
  until the anchored read view of 1.17. `comments.read_view_source: true` (a literal boolean, default false) gives every commenter the mount,
  **and the full Markdown source** (HTML comments, link targets, reference definitions, hidden blocks, front matter):
  enable it only if everyone with `ViewComments` may read the source.
- **Live updates** need co-editing: the thread component listens on `<collab topic>/comments`, granted in the
  co-editing subscriber cookie. A custom `collaboration.subscriber_authorizer` must implement
  `WikiCollabTopicsAuthorizerInterface` to grant both topics (otherwise comments refresh on the viewer's own actions
  only).
- **Notifications:** listen to `nowo_wiki.comment.*` (`WikiCommentEvent`). Check each recipient's `ViewComments` on
  the page first, then use `WikiCommentService::renderBodyForNotification()`: it renders **viewer-neutral** (page links
  show *Content not visible*, mentions stay `@handle`), because the listener runs in the actor's request and the
  actor's rights are not the recipient's. To show titles, render the comment Markdown yourself in the recipient's
  security context (e.g. a Messenger worker authenticated as the recipient). Do not serialise the event: it carries
  entities (the comment holds its body).
- **Rate limits and caps:** `comments.rate_limiter` is keyed **per user, across all pages**; add
  `comments.page_rate_limiter` for a second budget per user and page. Writes beyond `comments.limits` (500 threads per
  page, 500 comments per thread, 20 distinct emoji per comment, 10 reactions of one user per comment) answer 422
  `limit_reached`.
- **Anchors and quotes:** viewers who may edit the page (or everyone, with `read_view_source`) get the full anchor in
  the thread JSON. Others get only `quote.exact`, and an empty one once that text is no longer in the page's current
  revision (`content_text`). The stored `quote` is page text that outlives later edits of the page: treat it as
  confidential page content in exports and erasure.
- **Privacy:** `WikiCommentService::exportAuthor($user)` / `eraseAuthor($user, $deleteBodies)` and the command
  `wiki:comments:erase-user <id> [--export] [--keep-bodies]`: erasure unlinks the user from their comments, threads
  and resolutions, deletes their reactions and (by default) blanks their bodies into "Comment deleted" placeholders.
  Ownership of a comment follows `getUserIdentifier()`: do not recycle identifiers.
- **Deleting pages or spaces yourself:** the trash purge deletes comment rows explicitly. If your host deletes wiki
  pages or spaces outside the trash, call `WikiCommentRepositoryInterface::deleteForPages($pageIds)` first (MySQL and
  PostgreSQL cascade through the foreign keys; SQLite does not unless `foreign_keys` is on).
- **Turning comments off later:** the comment tables leave the generated schema, so a `doctrine:migrations:diff`
  run afterwards proposes `DROP TABLE` for them. Remove those lines from the migration unless you mean to delete the
  comments.
- **CSP:** the panel needs UiKit's `js/nowo-ui-side-panel.js` (loaded by the wiki pages that show the panel) and the
  Milkdown module; no inline style or handler. Avatar origins must be in `img-src`.
- New strings `wiki.comments.*`; new templates `manage/_page_tabs.html.twig`, `_comments_panel.html.twig`,
  `_comments_panel_body.html.twig`, `_comments_view.html.twig`.

## To 1.15.1

From **1.15.0**:

```bash
composer update nowo-tech/wiki-bundle
```

- **Exports include `README.md`.** Web (`nowo_wiki_space_export`) and console (`wiki:export`) exports now write a
  `README.md` index at the archive / target directory root, listing the exported pages (or saying there are none).
  A `wiki:export` into a directory that holds a `README.md` of its own now fails (exit 1) before writing any page;
  the index of an earlier export is replaced. A page with the slug `readme` is written as `readme-1.md` (Outline), a
  top-level Notion page titled `README.md` as `README.md-1/`. The bundle's importer skips the index (and reports it
  as skipped); other tools that read the export see one more Markdown file.
- **Outline / Notion exports from earlier versions** have front matter that does not parse (`wiki_parent: ''---`), so
  re-importing them yields pages without their titles, slugs and parents. Export again with 1.15.1 before
  re-importing. Strings: `wiki.export.readme.*` in the
  `NowoWikiBundle` domain (override them in your app translations if needed).
- `WikiExportReport` gains `$pages` (title, relative path, depth of each exported page) and `WikiDocumentExporter`
  an optional 6th constructor argument (`?WikiExportIndexWriter`); both are additive.

## To 1.15.0

From **1.14.1**:

```bash
composer update nowo-tech/wiki-bundle
```

- **"Copy as Markdown" (opt-in).** `editor.milkdown.copy_markdown: true` needs `nowo-tech/milkdown-editor-bundle` ^1.5
  (the container refuses to build otherwise). Editors of a Markdown page then see the button on the page view. If you
  override `page_view.html.twig`, add the `copy_markdown` include. Those responses are `Cache-Control: private,
  no-store`: a reverse proxy or CDN in front of the wiki must not strip that header.
- **History timeline (opt-in).** Nothing changes until you set `nowo_wiki.history.timeline: true` (a literal
  boolean). Then:
  - Add the `<prefix>_revision_labels` table (named versions): `doctrine:migrations:diff` picks it up.
  - Two route keys are registered (`page_version_save`, `page_version_copy`); add them to your firewall and access
    rules like the other wiki routes.
  - The history shows author labels derived from `kernel.secret` (it must not be empty).
  - Schedule the retention if you want it: `wiki:history:thin` (daily, from a single scheduler). It deletes revisions
    for good; try `--dry-run` first and raise `history.retention_days` if you need a longer full history.
  - If you override `page_history.html.twig` or `page_view.html.twig`, compare them with the bundle's (new row
    fields `label`, `author`, `session`; the *Save version* action). New template key `page_version`; new strings
    `wiki.history.*`.

## To 1.14.1

```bash
composer update nowo-tech/wiki-bundle
```

From **1.14.0**, with co-editing enabled:

- **Schema:** four counter columns on `<prefix>_page_collab_documents`. Run `doctrine:migrations:diff`, then add the
  backfill to the generated migration so existing logs keep their size:

  ```sql
  UPDATE wiki_page_collab_documents d SET
      update_count = (SELECT COUNT(*) FROM wiki_page_collab_updates u WHERE u.page_id = d.page_id AND u.kind = 'update'),
      log_bytes    = (SELECT COALESCE(SUM(LENGTH(u.data)), 0) FROM wiki_page_collab_updates u WHERE u.page_id = d.page_id),
      last_seq     = (SELECT COALESCE(MAX(u.id), 0) FROM wiki_page_collab_updates u WHERE u.page_id = d.page_id),
      snapshot_seq = (SELECT COALESCE(MAX(u.covers_seq), 0) FROM wiki_page_collab_updates u WHERE u.page_id = d.page_id AND u.kind = 'snapshot');
  ```

  **The backfill is required, and `doctrine:migrations:diff` does not generate it**: add it by hand to the migration
  that adds the columns. It reads every log once and holds locks on the documents table while it runs, so run the
  migration in a quiet window (or with co-editing briefly off). Without co-editing the collaboration tables are not in
  your schema and nothing changes. Without the backfill, live logs restart counting from zero (at worst a log grows
  past its budget once before the next compaction resets it).
- **Run `wiki:collab:purge` from a single scheduler** (one cron entry or one Scheduler worker): two purges at the same
  time only wait for each other's locks, but there is nothing to gain from running it on several hosts.
- **Rate limiters (optional):** split the co-editing budget so that typing and presence do not starve each other, e.g.

  ```yaml
  framework:
      rate_limiter:
          wiki_collab_update: { policy: sliding_window, limit: 1200, interval: '1 minute' }
          wiki_collab_awareness: { policy: sliding_window, limit: 600, interval: '1 minute' }
  nowo_wiki:
      collaboration:
          update_rate_limiter: limiter.wiki_collab_update
          awareness_rate_limiter: limiter.wiki_collab_awareness
  ```

  `rate_limiter` stays required and keeps covering token, state, compaction and reset (and updates / presence when
  their own limiter is not set). Beacon splits its `rate_limiter.yaml` the same way.
- A custom `collaboration.store` keeps working. Implement `WikiCollabCountingStoreInterface` to get the one-write
  update path.

## To 1.14.0

From **1.13.2**:

```bash
composer update nowo-tech/wiki-bundle
```

- The bundle requires the PHP `iconv` extension (enabled in nearly every PHP build).
- `nowo-tech/ui-kit-bundle` ^2.24 is required (`composer update nowo-tech/ui-kit-bundle`).
- **Page tree (opt-in).** Nothing changes until you set `nowo_wiki.tree.enabled: true` (a literal boolean). Then the
  sidebar becomes the UiKit tree, `js/nowo-ui-tree.js` is loaded on the space and page views, and two route keys are
  registered (`page_move`, `space_tree`); add them to your firewall and access rules like the other wiki routes. No
  schema change: moves reuse the `position` column. The fold state lives in the session by default; set
  `tree.state_store` to keep it per user in your own storage. If you override `_sidebar.html.twig`, `space.html.twig`
  or `page_view.html.twig`, compare them with the bundle's (`tree_ui` variable). New strings: `wiki.tree.*`.
- **Trash (opt-in).** Nothing changes until you set `nowo_wiki.trash.enabled: true` (a literal boolean). Then:
  - Add the `<prefix>_page_trash` table: `doctrine:migrations:diff` picks it up.
  - Four route keys are registered: `page_delete`, `space_trash`, `trash_restore`, `trash_purge`.
  - Schedule `bin/console wiki:trash:purge` daily (the retention is `trash.retention_days`, 30 by default).
  - Your `WikiSpaceAccessCheckerInterface` is asked `WikiAction::Delete` and `WikiAction::Purge`, only with the trash
    on. A checker with an exhaustive `match` on `WikiAction` must handle the two new cases before you enable it, and
    **a checker that grants actions it does not know (a default `true`) would give everyone Delete and Purge**: deny
    unknown actions, or decide the two explicitly (audit DO-I4). `WikiSpaceGuard::grants()` leaves them out.
  - New strings: `wiki.trash.*`.

## To 1.13.2

From **1.13.1**:

```bash
composer update nowo-tech/wiki-bundle
```

- **Page visibility follows the ancestors on every route.** If your `WikiSpaceAccessCheckerInterface` denies `View` on
  a page, its sub-pages now answer 404 on the page view, history, diff, attachments, co-editing, search and AI
  answers too (they were already hidden from the tree, the export and page links). Grant `View` on the parent if
  those users should read the sub-page.
- **Web import (space page).** A document whose `wiki_parent` is neither in the archive nor a page the importing user
  may read now fails with `Parent "x" is not available.` (it was attached at the top level). Pages the user may not
  read are never matched or updated: their slug is "not available". The `wiki:import` command keeps attaching such
  documents at the top level, as before (it runs without a user).

## To 1.13.1

From **1.13.0**:

```bash
composer update nowo-tech/wiki-bundle
```

## To 1.13.1

From **1.13.0**: `composer update nowo-tech/wiki-bundle`. Changes to check:

- **Schema listener and DoctrineBundle:** the bundle registers `WikiMetadataListener` (table names, and leaving the
  collaboration tables out without co-editing) through DoctrineBundle's `doctrine.event_listener` tag. With a bare
  Doctrine ORM setup, add it to your EventManager for `loadClassMetadata` and `postGenerateSchema` yourself (DA-I6).
- **No collaboration tables without co-editing.** With `collaboration.enabled: false` (the default) the
  `<prefix>_page_collab_documents` / `_updates` tables are no longer part of the generated schema, so
  `doctrine:migrations:diff` / `schema:update` stop asking for them. If you already created them and do not use
  co-editing, your next diff drops them (they hold only transient Yjs logs). Hosts with co-editing see no change.
- **`collaboration.enabled` must be `true` or `false`** written in the configuration; `%env(bool:…)%` is refused at
  compile time (DA-M1).
- **Co-editor names are opaque by default.** Co-editors now see "Editor xxxx" unless you set
  `nowo_wiki.collaboration.show_names: true` (the 1.13.0 behaviour); even then a display name equal to the login
  identifier or shaped like an e-mail address is replaced by the opaque label (CW-I2).
- **`wiki:collab:purge --older-than`** must be at least `1`; purged pages broadcast a reset so open editors reload
  (CW-I10). Custom stores may implement `WikiCollabPagePurgeInterface` to get that broadcast.
- **Optional per-user limiter:** `collaboration.user_rate_limiter` (CW-I9), e.g.
  `framework.rate_limiter.wiki_collab_user: {policy: sliding_window, limit: 1200, interval: '1 minute'}` →
  `user_rate_limiter: limiter.wiki_collab_user`.
- **Edit page scripts:** `manage/page_edit.html.twig` loads the TiptapEditorBundle script only with
  `editor.type: tiptap`. If you overrode `nowo_ui_scripts` to remove it for Milkdown, you can drop the override.
- **Mercure default claims (CW-I12):** never put `mercure` or `authorization_details` keys in the hub's default JWT
  claims — symfony/mercure merges them into the wiki subscriber cookie.

## To 1.13.0

From **1.12.0**:

```bash
composer update nowo-tech/wiki-bundle
```

From **1.12.x**: nothing to do while `nowo_wiki.editor.type` stays `tiptap` (the default) or `markdown`. The only schema change is two tables for co-editing (below), needed only with `nowo_wiki.collaboration.enabled: true`.

**Switching to the Milkdown editor:**

1. `composer require nowo-tech/milkdown-editor-bundle` (^1.2; the bundle conflicts with older versions) and register `Nowo\MilkdownEditorBundle\NowoMilkdownEditorBundle`; publish its assets (`assets:install`).
2. Set `nowo_wiki.editor.type: milkdown`; configure `nowo_milkdown_editor.markdown` (limits, `icon_sprite_url`, `icons`) and alias `Nowo\MilkdownEditorBundle\Markdown\Resolver\UserResolverInterface` to your mention resolver. Point `editor.milkdown.page_resolver` at your own page resolver if wiki page links must resolve differently.
3. Allow the Milkdown read-side script in your CSP (`script-src 'self' 'nonce-…'`, `style-src-attr 'unsafe-inline'` for KaTeX / Mermaid) — see the Milkdown SECURITY.md.
4. Convert existing HTML pages when you are ready (pages also convert one by one when edited):

   ```bash
   bin/console wiki:convert-markdown eng ops --author-id=admin@example.com --dry-run --report=var/conversion.json
   bin/console wiki:convert-markdown eng ops --author-id=admin@example.com --skip-lossy --report=var/conversion.json
   ```

   Each converted page gets one new revision; history is never rewritten and pages already in Markdown are skipped. Review `lost` in the report before converting lossy pages (`--page=<slug>` converts them one at a time).
5. Re-index backlinks / search from `nowo_wiki.page.saved` (`$event->conversion === true` for converted pages) with `WikiMarkdownReferenceExtractor::pageReferences()` / `mentions()`.

**Behaviour with `milkdown`:** the page view renders Markdown for each viewer (one render per view instead of the stored cache); `content_html` is viewer-neutral, so page links read *Content not visible* in search excerpts and AI context. Exports write links to exported pages as relative `.md` links.

**Co-editing (optional):** `composer require symfony/mercure-bundle:^0.6.1 symfony/rate-limiter` (symfony/mercure ^0.8 / ^0.9: with the Mercure protocol 1.0 configure the hub's default JWT claims `iss`, `aud`, `sub`, `client_id`) (or provide `collaboration.publisher` / `subscriber_authorizer`), then `nowo_wiki.collaboration.enabled: true` with `collaboration.rate_limiter` (required: the container refuses to compile without it) and a short `mercure.cookie_lifetime`. Schema: two new tables — generate the migration (`doctrine:migrations:diff`) or create them:

```sql
CREATE TABLE wiki_page_collab_documents (page_id VARCHAR(36) NOT NULL, base_revision_id VARCHAR(36) DEFAULT NULL, seeded_at DATETIME DEFAULT NULL, seeded_by VARCHAR(180) DEFAULT NULL, recent_resets INT DEFAULT 0 NOT NULL, last_reset_at DATETIME DEFAULT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(page_id));
CREATE TABLE wiki_page_collab_updates (id INT AUTO_INCREMENT NOT NULL, page_id VARCHAR(36) NOT NULL, kind VARCHAR(16) NOT NULL, data LONGTEXT NOT NULL, covers_seq INT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX wiki_collab_update_page (page_id, id), PRIMARY KEY(id));
```

(`wiki` is your `table_prefix`.) The tables exist only for the log; without `collaboration.enabled` nothing reads them.

**Host implementations:** `WikiPageRepositoryInterface` is unchanged; implement the new `WikiPageBatchFinderInterface` on a custom repository to resolve page links in one query.

## To 1.12.0

From **1.11.x**:

```bash
composer update nowo-tech/wiki-bundle
```

No schema change. Check these if your app customises the wiki:

- **Overrides that extend the original page** can use `{% extends '@!NowoWikiBundle/manage/<page>.html.twig' %}` (new); `@!Wiki/…` keeps working.
- **FormKit `wiki` profile:** when your app does not define `nowo_form_kit.profiles.wiki`, the seeded profile no longer adds `form-control` / `form-select` / `form-check-input` / `form-check` / `mb-2` to the wiki fields: your form theme styles them (`bootstrap_5_layout.html.twig` adds the Bootstrap classes itself). To keep the old classes, define the profile:

  ```yaml
  nowo_form_kit:
      profiles:
          wiki:
              alias: wiki
              translation_domain: NowoWikiBundle
              defaults: { attr: { class: 'nowo-ui-input form-control' }, row_attr: { class: 'mb-2' } }
              field_types:
                  checkbox: { attr: { class: form-check-input }, row_attr: { class: 'form-check mb-2' } }
                  choice: { attr: { class: form-select } }
  ```

- **Flashes:** with the translator (the usual setup) flashes are still translated strings. Without it they are `TranslatableMessage` objects: print them with `{{ message|trans }}`. The page-create errors and the import failure / report are now catalogue keys (`wiki.page.error.*`, `wiki.import.failed`, `wiki.import.report`): override them in `translations/NowoWikiBundle.<locale>.yaml` instead of matching the English texts.
- **Search form:** the `q` label is `wiki.search.query` (was `wiki.search.placeholder`). With `tenant_only: true` the `space` field is hidden (no `wiki.search.all_spaces` option).
- **Space home intro:** `wiki.space.pick_page` is shown only to users who may create pages; others get `wiki.space.pick_page_read_only`.
- **Pagination:** `WikiPageFactory::index()` / `history()` accept `?Pagination`; the controller passes `null` when everything fits on one page. A `page_history` / `index` override that reads `page.pagination` must allow `null` (the UiKit list scaffold does).
- **Diff:** the screen-reader prefix of an added / removed line carries `nowo-wiki-sr-only` (styled by `wiki.css`) and the `+` / `-` is wrapped in `nowo-wiki-diff__marker` (`aria-hidden`).

## To 1.11.1

From **1.11.0**:

```bash
composer update nowo-tech/wiki-bundle
```

No action needed when you import the `nowo_wiki` route loader (`resource: .`, `type: nowo_wiki`, the documented setup). If on Symfony >= 7.3 the wiki pages answered on `/tools/wiki/...` without that import (through the default `controllers: { resource: routing.controllers }` of `config/routes.yaml`), they are gone now: import the loader (see [INSTALLATION](INSTALLATION.md)).

## To 1.11.0

From **1.10.0**:

```bash
composer update nowo-tech/wiki-bundle
```

- **Schema (required, even if you keep Tiptap):** `<prefix>_page_revisions` gains two columns. The bundle ships the mapping only: generate a migration (`bin/console doctrine:migrations:diff`, then `doctrine:migrations:migrate`) or apply the SQL by hand (MySQL / MariaDB; the demo ships `Version20261031090000`):

  ```sql
  ALTER TABLE wiki_page_revisions
      ADD content_markdown LONGTEXT DEFAULT NULL,
      ADD format VARCHAR(16) DEFAULT 'html' NOT NULL;
  ```

  PostgreSQL: `ALTER TABLE wiki_page_revisions ADD content_markdown TEXT DEFAULT NULL, ADD format VARCHAR(16) DEFAULT 'html' NOT NULL;`. Existing rows become `format = 'html'` with no Markdown: nothing changes for them.
- **Nothing else changes with the default `editor.type: tiptap`.** `WikiMarkdownConverter::markdownToHtml()` now reads GFM (Outline / Notion imports keep their tables, task lists and strikethrough).
- **Moving to Markdown:** set `nowo_wiki.editor.type: markdown` (optionally `editor.markdown_form_type` with your Markdown editor's form type), then convert the existing pages — each gets one new revision, history untouched, safe to run again:

  ```bash
  bin/console wiki:convert-markdown docs handbook --author-id=admin@example.com --dry-run
  bin/console wiki:convert-markdown docs handbook --author-id=admin@example.com
  ```

  The conversion dispatches `nowo_wiki.page.saved` for every converted page with `WikiPageSavedEvent::$conversion = true`: update indexes from it, but skip notifications, activity feeds and "page edited" e-mails (nobody edited the page). Pages you do not convert are converted when they are next edited.
- **Rendering:** a Markdown revision's `content_html` is rendered by `WikiMarkdownRendererInterface` (raw HTML escaped, unsafe links dropped) and then sanitized by `WikiHtmlSanitizerInterface`: markup your extensions emit must be allowed by the sanitizer — declare it in `nowo_wiki.sanitizer` ([CONFIGURATION](CONFIGURATION.md#sanitizer-allowlist-111)); `script`, `style`, `iframe`, forms, embeds, URL attributes, `style=` and `on*` stay refused.
- **Per-viewer content is the host's job:** `content_html` is one cache for every reader. Page links (`[[wiki:<uuid>]]`), mentions and live blocks must be resolved at read time for the viewer (current title and URL, or a neutral "not visible" placeholder) by the host or the editor bundle, never baked into the cache.
- **Code calling the page service:** `WikiPageService::create()` / `saveRevision()` keep their signatures with a new optional last `$format` argument (HTML by default); `WikiPageRevision` has a new optional last constructor argument `$contentMarkdown`.

## To 1.10.0

From **1.9.0**:

```bash
composer update nowo-tech/wiki-bundle
```

From **1.9.0**:

```bash
composer update nowo-tech/wiki-bundle nowo-tech/ui-kit-bundle
```

- **Requirements:** `nowo-tech/ui-kit-bundle` **^2.17** (was `^1.4 || ^2.2`) and `symfony/security-csrf` (now a direct requirement).
- **CSRF fails closed:** with `web_ui.enabled: true` the container no longer compiles without `framework.csrf_protection` and `framework.form.csrf_protection`. Enable them (Symfony's default) or set `nowo_wiki.web_ui.enabled: false`, which now also registers no wiki route.
- **`security.access_roles` is a real gate:** every permission of the default checker now needs one of the access roles **and** one of the roles of that action (the admin roles still grant everything). With the defaults (`access_roles: [ROLE_USER]`) nothing changes for users holding `ROLE_USER`; a host with `access_roles: [ROLE_ADMIN]` and per-action lists of `ROLE_USER` now really restricts the wiki to admins, as documented. When `access_roles` and every action list are empty (no `access_checker`, `allow_unauthenticated: false`) the container refuses to compile.
- **Hidden pages stay hidden everywhere (security fix for 1.9.0):** pages the user may not view (`WikiAction::View` on the page) are no longer listed in the sidebar tree (with their sub-pages), the archived list, search results, the space export ZIP, AI sources or the AI context. Space lists (index, search and AI choices) only show spaces with `view`; a question across spaces only uses spaces with `ask_ai`. `WikiContextRetriever` and `WikiKnowledgeSearchTool` take an optional `WikiSpaceAccessCheckerInterface` (autowired), `WikiDocumentExporter::export()` an optional page filter (the CLI export stays complete). A host checker that hides a whole space should keep denying `view` on it.
- **Attachments:** downloads need `canList()` and uploads `canEdit()` of `WikiAccessCheckerInterface` (so `access_roles` applies to them); `WikiAttachmentController` takes it as a new optional last argument.
- **`~` on a role list means no role:** `security.roles.<action>: ~` and a deprecated `<action>_roles: ~` grant nothing (as `[]`); leave the key out to keep the default.
- **`allow_unauthenticated`:** must be a literal `true` / `false` (no env placeholder) and is refused in the `prod` environment and in non-debug kernels (`test*` environments excepted). The same literal rule applies to `web_ui.enabled`.

### Configuration (deprecated keys, removed in 2.0)

| Deprecated | Use | Notes |
| --- | --- | --- |
| `route_prefix` | `web_ui.path_prefix` | `web_ui.path_prefix` wins when both are set |
| `templates.layout` | `web_ui.layout_template` | default `null` = `nowo_ui_kit.layouts.admin`; the old default `@NowoWikiBundle/layout.html.twig` is read as `null` |
| `templates.<page>` | `web_ui.templates.<page>` | pages: `index`, `space`, `page_view`, `page_edit`, `page_history`, `page_diff`, `search`, `ai_ask` |
| `web_ui.css_framework` | `nowo_ui_kit.css_framework` | default `null` now; when set it only seeds `nowo_ui_kit.css_framework` / `icon_set` if the host left them unset (1.9 seeded `tabler` by default) |
| `security.admin_roles`, `list_roles`, `create_roles`, `edit_roles`, `history_roles`, `archive_roles`, `ai_roles`, `import_roles`, `export_roles` | `security.roles.{admin,list,create,edit,history,archive,ai,import,export}` | same defaults; `security.roles.<action>` wins |

```yaml
nowo_ui_kit:
    css_framework: bootstrap5
    layouts:
        admin: 'layouts/admin.html.twig'     # your layout or a bridge that renders the UiKit canonical blocks

nowo_wiki:
    web_ui:
        path_prefix: null
        layout_template: null
        templates: {}
    security:
        access_roles: [ROLE_USER]
        roles:
            import: [ROLE_ADMIN]
```

### Template contract

Every wiki page now extends a UiKit page scaffold (REQ-UI-003); see [TEMPLATES.md](TEMPLATES.md) for the page → scaffold map, the view models and the data blocks.

- **Removed templates:** `layout.html.twig` (Tabler / Bootstrap CDN shell), `base.html.twig`, `manage/_page_header.html.twig`, `manage/_pagination.html.twig`, `manage/_flashes.html.twig`, `manage/_ai_ask_modal.html.twig`, `manage/_ai_ask_form_fields.html.twig`, `manage/_page_tree_list.html.twig`. The UiKit page header, pagination, flashes, empty state, confirm and `<dialog>` partials replace them (theme them in UiKit, once). New: `manage/_sidebar.html.twig`, `manage/_ai_dialog.html.twig`, `manage/_legacy_layout.html.twig` (deprecated bridge).
- **Removed blocks:** `title`, `body`, `container_class`, `wiki_editor_scripts`, `wiki_header`, `wiki_flashes`, `space_sidebar`, `space_sidebar_header`, `page_tree`, `page_main`, `page_header`, `page_actions`, `page_body`, `page_aside`, `space_main`, `space_search`, `space_import_export`, `space_archived`, `history_list`, `history_pagination`, `history_footer`, `diff_body`, `diff_footer`, `search_form`, `search_results`, `page_form`, `page_form_fields`, `page_form_actions`, `wiki_search_highlight`, `wiki_ai_highlight`, `wiki_ai_modal`, `space_list`, `ai_error`, `ai_answer`, `ai_sources`, `ai_follow_up`, `ai_form`. Pages fill the UiKit blocks (`nowo_ui_styles`, `nowo_ui_scripts`, `nowo_ui_list_row`, `nowo_ui_show_sections`, `nowo_ui_form_body`, `nowo_ui_form_aside`, `nowo_ui_modals`).
- **Page variables:** `page` is now the UiKit view model (`ListPage`, `ShowPage`, `FormPage`); the `WikiPage` / `WikiSpace` / `WikiPageRevision` entities and the 1.9 variables `layout`, `space`, `spaces`, `space_page`, `pagination` (array), `revision`, `revisions`, `older_revision`, `restore_forms`, `archived_pages`, `unarchive_forms`, `results`, `from_revision`, `to_revision`, `form` (page edit), `search_form` (index, search), `ai_form` (AI page) are gone. Tree nodes, archived pages, search hits, AI sources and "Used in" entries are value-free arrays (TEMPLATES.md, "View models"); the page body is `content_html`.
- **Markup:** no Tabler / Bootstrap classes in the bundle markup any more (only `nowo-ui-*` / theme slots and the `nowo-wiki-*` hooks listed in USAGE); the 1.x hooks `wiki-tree`, `wiki-page-content`, `wiki-diff-*`, `#wikiAiAskModal` and the `data-nowo-wiki-skin` attribute are gone. `wiki.css` dropped the `--nowo-wiki-*` token tree: remap the UiKit `--nowo-ui-*` tokens (`nowo_ui_kit.theme.tokens`) instead. The AI modal is a native `<dialog>` (`#nowo-wiki-ai-dialog`); Archive (new header action on the page view) and Restore ask for confirmation in the UiKit confirm `<dialog>`.
- **Form fields:** `WikiSearchType`, `WikiAiAskType` and `WikiImportType` no longer set `form-control` / `form-select` / `form-label` / `form-check-*`: style fields with your form theme (`twig.form_themes`) or the FormKit `wiki` profile. `WikiPageFormType` shows the editor help as the `content` field help.
- **Flashes** are translated (domain `NowoWikiBundle`) when they are added, so any layout prints them as given.
- **Layouts:** a 1.9 layout (`web_ui.layout_template` / `templates.layout` rendering `{% block body %}`, `stylesheets`, `javascripts`) still works through the deprecated bridge, with a deprecation notice; move it to the UiKit canonical blocks (one-file bridge: `{% use '@NowoUiKit/layout/_bridge.html.twig' %}`, see the demo `templates/layouts/admin.html.twig`).
- **Overrides** under `templates/bundles/NowoWikiBundle/` written for 1.9 must be redone: prefer theming UiKit, else `web_ui.templates.<page>` with a thin `{% extends '@NowoWikiBundle/manage/<page>.html.twig' %}` that overrides one UiKit data block.
- **Schema reminder:** 1.9 added `<prefix>_page_links` and `<prefix>_attachments`; the demo now ships the matching migration.

## To 1.9.0

From **1.8.1**:

```bash
composer update nowo-tech/wiki-bundle nowo-tech/tiptap-editor-bundle
```

- **Requirement:** `nowo-tech/tiptap-editor-bundle` **^1.6** (upload and suggestion hooks of the editor).
- **Schema:** two new tables, `<prefix>_page_links` and `<prefix>_attachments` (`bin/console doctrine:migrations:diff` then `doctrine:migrations:migrate`, or `doctrine:schema:update --force` without migrations). Nothing else changes in the existing tables.
- **New configuration keys** (all optional, defaults keep 1.8 behaviour):

  ```yaml
  nowo_wiki:
      tenant_paths: []           # e.g. [{ path: '/projects/{spaceSlug}/wiki', name_prefix: 'app_project_', slug_requirement: '[0-9a-f-]{36}' }]
      tenant_only: false         # true drops the tenant-less copies of the space-scoped routes
      attachments:
          enabled: false
          storage_dir: '%kernel.project_dir%/var/wiki-attachments'
          storage: null          # service id of a WikiAttachmentStorageInterface
          scanner: null          # service id of a WikiAttachmentScannerInterface
          max_file_bytes: 10485760
          max_space_bytes: 524288000
          allowed_types: { … }   # extension => sniffed MIME types (no SVG / HTML / XML / scripts)
      routes:
          page_unarchive:      { path: '/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/unarchive', name: nowo_wiki_page_unarchive }
          page_restore:        { path: '/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/revisions/{revisionNumber}/restore', name: nowo_wiki_page_restore }
          attachment_upload:   { path: '/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/attachments', name: nowo_wiki_attachment_upload }
          attachment_download: { path: '/tools/wiki/spaces/{spaceSlug}/attachments/{attachmentId}', name: nowo_wiki_attachment_download }
  ```

  Hosts that list routes in a firewall / `access_control` or a breadcrumb map should add the four new route names (and their tenant copies, `<name_prefix><route name>`).
- **No behaviour change by default:** without a space checker the 1.x user-level checks still apply (`LegacyWikiSpaceAccessChecker`), no tenant path is registered and attachments stay off.
- **Per-tenant access:** alias `Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface` (and, to create spaces on the first visit, `Nowo\WikiBundle\Service\WikiSpaceLocatorInterface`) to your services. A denied `view` answers 404, any other denied action 403.
- **Template overrides:** `manage/page_view.html.twig` has a `page_aside` block (*Used in*); `manage/page_history.html.twig` renders `restore_forms`; `manage/space.html.twig` renders `archived_pages` / `unarchive_forms`. New translation keys: `wiki.space.archived`, `wiki.page.unarchive`, `wiki.page.used_in`, `wiki.history.restore`, `wiki.history.restored`.
- **Custom repositories:** `WikiPageRepositoryInterface` gains `findArchivedBySpace()`.
- **Attachments:** enable with `attachments.enabled: true`, keep `storage_dir` outside `public/`, on a persistent volume and in your backups; run `WikiAttachmentService::purgeOrphans()` / `purgeStrayFiles()` from a nightly job.
- **Templates (FR-006), only if you override bundle views:**
  - `manage/_page_tree.html.twig` no longer defines the `render_tree` macro: it includes `manage/_page_tree_list.html.twig`, which includes `manage/_page_tree_item.html.twig` per page (override that partial to change one item). The `--depth` custom property is gone; indent with nested lists or the `nowo-wiki-tree__item--depth-N` classes (N capped at 6). Copies that still print `style="--depth: …"` break a strict `style-src`.
  - `base.html.twig` has no inline `<style>` block any more (`.container-wiki`, `.container-narrow` and the body background live in `wiki.css`, the background only on `body.nowo-ui-wiki`). If your copy of `base.html.twig` keeps the old block, drop it. `base.html.twig` now sets `nowo_wiki_skin` and `nowo_wiki_csp_nonce`; keep those two lines in a copy, or the pages fall back to `framework` and no nonce.
  - Each manage page wraps its body in `<div class="nowo-wiki nowo-wiki--<view>" data-nowo-wiki-skin="…">` and splits it into blocks (see the CHANGELOG list); prefer extending the page and overriding one block over copying it. Space cards and search results became `<ul>` / `<li>` (same Tabler classes), the AI modal is styled through `.nowo-wiki-ai-modal` instead of `#wikiAiAskModal` (the id stays).
  - CSS that targeted `.wiki-tree-item` padding or `.wiki-page-content` / `.wiki-diff-*` rules from the old `wiki.css`: the legacy classes stay in the markup, the bundle rules now target `nowo-wiki-tree__*`, `nowo-wiki-content` and `nowo-wiki-diff__line--*`.
- **Theming:** with `web_ui.css_framework` other than `tabler` / `bootstrap*` (`custom`, `none`, `tailwind`, `foundation`), `wiki.css` now draws the pages from `--nowo-wiki-*` tokens; set the tokens on `:root` (or a dark theme on `[data-theme="dark"]`) instead of copying templates. Tabler / Bootstrap hosts see no visual change.
- **CSP:** set the request attribute `csp_nonce` (same value as in `script-src 'nonce-…'`) before rendering and the bundle scripts carry it; nothing is printed without it.

## To 1.8.1

From **1.8.0**:

```bash
composer update nowo-tech/wiki-bundle
```

- Keep importing the routes with `type: nowo_wiki`. Do not add an `attribute` import of the bundle controllers: it would bypass the configured paths, names and `route_prefix`.

## To 1.8.0

From **1.7.2**:

```bash
composer update nowo-tech/wiki-bundle
```

No configuration change is required.

- **List limits (new defaults):** the wiki index shows 24 spaces per page, the page history 25 revisions per page and the sidebar tree at most 500 pages. Tune them under `nowo_wiki.pagination` (`spaces_per_page`, `revisions_per_page`, `max_tree_pages`).
- **Custom repositories:** if you aliased `WikiPageRepositoryInterface` or `WikiPageRevisionRepositoryInterface` to your own class, add the new optional parameters: `findActiveBySpace(WikiSpace $space, ?int $limit = null)` and `findByPage(WikiPage $page, ?int $limit = null, int $offset = 0)`.
- **Template overrides:** `manage/index.html.twig` now loops over `space_page` (current page of `spaces`) and includes `manage/_pagination.html.twig`; `manage/page_history.html.twig` uses `older_revision` for the last row and includes the pager; `manage/_page_tree.html.twig` receives `tree_truncated` / `max_tree_pages`. Overrides keep working but show every space / only the first page of history until you merge these changes.
- **Logging:** route the new `nowo_wiki` Monolog channel if you want it in a separate file (see CONFIGURATION, Logging).
- **Imports:** ZIP archives with absolute or `..` entry names are now rejected.

## To 1.7.2

From **1.7.1**:

```bash
composer update nowo-tech/wiki-bundle
```

## To 1.7.1

From **1.7.0**:

```bash
composer update nowo-tech/wiki-bundle
```

## To 1.7.0

From **1.6.4**:

```bash
composer update nowo-tech/wiki-bundle
```

### Forms rendered through Form Types (REQ-TWIG-005)

`WikiManageController` now always passes these form variables; **if you override a manage template**, render them with `form_start` / the child loop / `form_end` instead of raw markup (see [USAGE — Forms in templates](USAGE.md#forms-in-templates-req-twig-005)):

| Template | New variable | Notes |
| -------- | ------------ | ----- |
| `manage/index.html.twig` | `search_form`, `ai_form` | `ai_form` must also be passed to `_ai_ask_modal.html.twig` (it is included with `with_context = false`) |
| `manage/space.html.twig` | `search_form`, `import_form` | Same `space` / `q` and `format` / `archive` / `update_existing` / `_token` names |
| `manage/search.html.twig` | `search_form` | Same query names (bookmarks keep working) |
| `manage/ai_ask.html.twig`, `manage/_ai_ask_form_fields.html.twig` | `ai_form` | The fields partial now renders `ai_form` children; the caller wraps it in `form_start(ai_form)` / `form_end(ai_form)` |
| `manage/page_edit.html.twig` | — | Remove the hand-rolled `<input type="hidden" name="_token" value="{{ csrf_token(csrf_token_id) }}">`: the page form's own `_token` child (rendered by the loop / `form_end`) is now the one checked. A page-edit override that still posts only the top-level `_token` fails CSRF validation. |

`csrf_token_id` is still passed to `page_edit.html.twig` and `ai_ask.html.twig` for backward compatibility but no longer used by the shipped templates.

## To 1.6.4

From **1.6.3**:

```bash
composer update nowo-tech/wiki-bundle
```

## To 1.6.3

From **1.6.2**:

```bash
composer update nowo-tech/wiki-bundle
```

## To 1.6.2

From **1.6.1** — explicit security service definitions win over the resource scan

```bash
composer update nowo-tech/wiki-bundle
```

- No breaking changes. No application upgrade steps.

## To 1.6.1

From **1.6.0** — space export sets application/zip without symfony/mime

```bash
composer update nowo-tech/wiki-bundle
```

- No breaking changes. No application upgrade steps.

## To 1.6.0

From **1.5.0** — fixes - flash messages rendered, LIKE search escaping, multibyte excerpts, dead config deprecated.

```bash
composer update nowo-tech/wiki-bundle
```

- **Deprecated keys:** remove `nowo_wiki.database.driver`, `nowo_wiki.database.platform` and `nowo_wiki.firewall` from your config. They never had an effect and now trigger a deprecation (removal in 2.0). `database.entity_manager` is still used.
- **Flash messages:** the bundle templates now render session flashes (import results and errors). If your host layout also renders `app.flashes`, the messages are consumed by whichever template runs first; override `@NowoWikiBundle/manage/_flashes.html.twig` with an empty template to keep only yours.

## To 1.5.0

From **1.4.1** — security: HTML sanitizer rebuilt on symfony/html-sanitizer, render-time sanitizing, ZIP import limits.

```bash
composer update nowo-tech/wiki-bundle
```

- **Stricter HTML sanitizer**: `WikiHtmlSanitizer` now removes every attribute that is not allowlisted (event handlers, `srcdoc`, `id`, arbitrary `data-*`, `style` other than `text-align: …`), `javascript:`/`data:` URLs and unknown elements (formatting wrappers such as `<section>` or `<font>` are unwrapped, keeping their text). Iframes need an https `src` on the YouTube/Vimeo allowlist.
- **Render-time sanitizing**: if you override `manage/page_view.html.twig`, replace `revision.contentHtml|raw` with `revision.contentHtml|nowo_wiki_safe_html`.
- **ZIP imports**: archives with more than 10 000 entries or more than 512 MiB uncompressed are rejected; override `WikiArchiveHelper` arguments (`$maxEntries`, `$maxUncompressedBytes`) in your service config if you need larger imports.

## To 1.4.1

From **1.4.0** — dependency refresh and repository maintenance.

```bash
composer update nowo-tech/wiki-bundle
```

- No breaking changes. No application upgrade steps.

## To 1.4.0

From **1.3.5** — Doctrine ORM `^3.7` requirement (`\SortDirection`); dependency updates.

```bash
composer update nowo-tech/wiki-bundle doctrine/orm
php bin/console cache:clear
```

- **Requirement:** `doctrine/orm` must be `^3.7` (ORM 2.x and 3.0–3.6 are no longer supported). It pulls `symfony/polyfill-php86`, which provides the global `\SortDirection` enum on PHP < 8.6.
- No configuration, route or template changes. No database migration.

## To 1.3.5

From **1.3.4** — REQ-CS-008 Igor FrankenPHP worker audit (igor-php require-dev, igor.json, make igor).

```bash
composer update nowo-tech/wiki-bundle
php bin/console cache:clear
```

No breaking changes for configuration. Behaviour changes relevant to FrankenPHP worker mode:

- **Wiki routes outside a secured firewall are anonymous.** The controller, the default access checker and `WikiKnowledgeSearchTool` only use the security token when the main request is handled by a firewall with `security: true`. Keep `route_prefix` behind such a firewall (or use `security.allow_unauthenticated: true`).
- **AI timeout:** set the bundle HTTP client on your platform and tune `nowo_wiki.ai.http_timeout` (seconds, default 30):

```yaml
# config/packages/ai.yaml
ai:
    platform:
        openai:
            http_client: 'nowo_wiki.ai.http_client'
```

- **Custom wiring:** `DoctrineOrmWiki*Repository` and `WikiSearchService` now receive `Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider` from the extension; passing an `EntityManagerInterface` still works. `WikiManageController`, `ConfigurableWikiAccessChecker` and `WikiKnowledgeSearchTool` take an optional last `?WikiTokenGuard` argument.
- No application upgrade steps for require-dev Igor tooling (REQ-CS-008). Consumers do not pull `igor-php/igor-php` transitively.

## From 1.3.3 to 1.3.4

No breaking changes. **No application upgrade steps.**

```bash
composer update nowo-tech/wiki-bundle
```

## To 1.3.3

No application upgrade steps.

```bash
composer update nowo-tech/wiki-bundle
```

## To 1.3.2

No application upgrade steps. **Demos only:** Hot Reload Bundle `^1.4` (FrankenPHP Mercure/`hot_reload`, `dev`/`test`).

```bash
composer update nowo-tech/wiki-bundle
php bin/console cache:clear
```

## To 1.3.1

From **1.3.0** — test-only FormKit merger wiring for controller unit tests. No host migration.

```bash
composer update nowo-tech/wiki-bundle
```

## To 1.3.0

From **1.2.0** — FormKit admin forms, UiKit macros, Twig Extra (REQ-TWIG-004), Twig-CS-Fixer.

```bash
composer update nowo-tech/wiki-bundle
php bin/console cache:clear
php bin/console assets:install --symlink --relative public
```

Requires `nowo-tech/form-kit-bundle` ^2.0 and `nowo-tech/ui-kit-bundle` ^1.4.

### Twig Extra Bundle (REQ-TWIG-004)

Hosts that render this bundle's Twig templates must install:

```bash
composer require twig/extra-bundle twig/string-extra
```

and enable `Twig\Extra\TwigExtraBundle\TwigExtraBundle`. Flex recipes usually register it automatically.

### Twig-CS-Fixer (maintainers)

Package maintainers: `composer twig:lint` / `composer twig:fix` use `.twig-cs-fixer.php` over `src/` (and `templates/` when present).

## To 1.2.0

Minor release: REQ-UI-002 — `security.allow_unauthenticated`, AllowAll checker, SecurityBundle compile-time guard, and soft manage-UI gate.

```bash
composer require nowo-tech/wiki-bundle:^1.2
php bin/console cache:clear
```

### Behaviour

| Topic | Before | 1.2.0 |
| --- | --- | --- |
| Apps without SecurityBundle (Web UI on) | Could boot | Boot fails with `LogicException` unless `allow_unauthenticated: true` |
| Manage auth attribute | `#[IsGranted('IS_AUTHENTICATED')]` | Feature checks via `WikiAccessCheckerInterface` (+ optional AllowAll) |
| Host firewall | Recommended | Still required in production (`access_control` on `/tools/wiki`) |

**Trusted local demos only:**

```yaml
nowo_wiki:
    security:
        allow_unauthenticated: true   # never in production
```

**Production:** keep `allow_unauthenticated: false`, install SecurityBundle, grant roles, and protect manage paths with host `access_control`.

Page mutations still call `requireUser()` — anonymous demos need a host authenticator (or real login) for write flows.

## To 1.1.1

**CI tooling only** (Vitest on Node.js 22). **No application or config changes.**

```bash
composer require nowo-tech/wiki-bundle:^1.1.1
```

Integrators: no changes. Bundle contributors: local/CI TypeScript coverage needs Node **22+** when using `jsdom@30`.

## To 1.1.0

Optional manage page shell for REQ-UI-001. **Non-breaking** — `web_ui.css_framework` / `layout_template` from **1.0.5** unchanged.

```bash
composer require nowo-tech/wiki-bundle:^1.1.0
php bin/console cache:clear
```

- Manage pages go through `@NowoWikiBundle/base.html.twig` (stacks package CSS/JS with `parent()`). Prefer `web_ui.layout_template` at the host chrome instead of freezing every page.
- If you **overrode** manage templates that `{% extends layout %}`, update them to `{% extends '@NowoWikiBundle/base.html.twig' %}` (or keep extending your own shell and stack assets yourself).

```yaml
nowo_wiki:
    web_ui:
        css_framework: bootstrap5
        layout_template: 'layouts/app.html.twig'
```

## To 1.0.5

No breaking changes for application consumers. Defaults keep Tabler-compatible markup.

- Optional `nowo_wiki.web_ui` (`css_framework`, `layout_template`) — see [CONFIGURATION.md](CONFIGURATION.md).
- Demo FrankenPHP image is **PHP 8.5** (`dunglas/frankenphp:1-php8.5-bookworm`); recreate the demo container after pull.
- PHPUnit / CI: `SYMFONY_DEPRECATIONS_HELPER=max[direct]=0` (REQ-SF-005).

```bash
composer update nowo-tech/wiki-bundle
```

## To 1.0.4

No breaking changes for application consumers.

- Composer may resolve `nowo-tech/tiptap-editor-bundle` to `1.2.0` (still within `^1.0`).
- Demo only: optional `FRANKENPHP_MODE=classic|worker` in `demo/symfony8/.env` (default `worker`). Recreate the container after changing it (`docker compose up -d` / `make up`).

## To 1.0.3

No breaking changes for application consumers. CI and test-dev tooling only (`symfony/var-exporter` in `require-dev`, GitHub Actions matrix aligned to Symfony `^7.4`).

## To 1.0.2

No breaking changes. Documentation only: CI requirements live in [GITHUB_CI.md](GITHUB_CI.md) (formerly `GITLAB_CI.md`).

## To 1.0.1

No breaking changes. Optional for consumers:

- Contributors: run `make setup-hooks` once per clone so commit-msg rejects Cursor co-author trailers (REQ-GIT-001).
- See [CHANGELOG.md](CHANGELOG.md) for documentation and CI additions.

## To 1.0.0

Initial stable release. No prior versions.

### Requirements

- PHP `>=8.2 <8.6`
- Symfony `^7.4 || ^8.0`
- `nowo-tech/tiptap-editor-bundle` `^1.0`
- Doctrine ORM 2.15+ or 3.x

### Install / upgrade steps

```bash
composer require nowo-tech/wiki-bundle nowo-tech/tiptap-editor-bundle
```

1. Add `config/packages/nowo_wiki.yaml` with `user_class`.
2. Run Doctrine migrations for `wiki_spaces`, `wiki_pages`, `wiki_page_revisions` (or your `table_prefix`).
3. Implement `WikiAccessCheckerInterface` (or use default role-based checker).
4. For team spaces, implement `WikiTeamMembershipResolverInterface`.
5. Clear cache: `bin/console cache:clear`.

### Optional features

**Import / export** (enabled by default):

```yaml
nowo_wiki:
    import_export:
        enabled: true
        max_upload_bytes: 52428800  # 50 MB
```

Console: `bin/console wiki:import {space} {source}` and `bin/console wiki:export {space} {target} [--zip]`.

**Symfony AI assistant**:

```bash
composer require symfony/ai-bundle
```

```yaml
nowo_wiki:
    ai:
        enabled: true
        agent: wiki_assistant
```

See [CONFIGURATION.md](CONFIGURATION.md) and [USAGE.md](USAGE.md).

### Breaking changes

None (first release).

See [CHANGELOG.md](CHANGELOG.md) for full history.
### FormKitBundle (admin forms)

If you use admin/dashboard Symfony forms, ensure `nowo-tech/form-kit-bundle` ^2.0 is installed (pulled transitively) and `Nowo\FormKitBundle\NowoFormKitBundle` is registered. Form types use profile `wiki` via `#[FormKitConfig]`; the bundle prepends that profile when the host has not defined it.
