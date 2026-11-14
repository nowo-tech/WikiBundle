# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[1.34.0] - 2026-11-14](#1340---2026-11-14)
  - [Added](#added)
- [[1.33.0] - 2026-11-14](#1330---2026-11-14)
  - [Added](#added-1)
  - [Fixed](#fixed)
- [[1.32.0] - 2026-11-13](#1320---2026-11-13)
  - [Added](#added-2)
- [[1.31.1] - 2026-11-13](#1311---2026-11-13)
  - [Security](#security)
  - [Tests](#tests)
- [[1.31.0] - 2026-11-12](#1310---2026-11-12)
  - [1.31.0 (unreleased)](#1310-unreleased)
- [[1.30.0] - 2026-11-11](#1300---2026-11-11)
  - [Added](#added-4)
  - [Fixed](#fixed-2)
- [[1.29.1] - 2026-11-11](#1291---2026-11-11)
  - [Security](#security-1)
- [[1.29.0] - 2026-11-11](#1290---2026-11-11)
  - [Added](#added-5)
  - [Changed](#changed)
- [[1.28.0] - 2026-11-10](#1280---2026-11-10)
  - [Added](#added-6)
  - [Changed](#changed-1)
- [[1.27.0] - 2026-11-09](#1270---2026-11-09)
  - [Added](#added-7)
- [[1.26.0] - 2026-11-09](#1260---2026-11-09)
  - [Fixed](#fixed-3)
  - [Changed](#changed-2)
  - [Documentation](#documentation)
- [[1.25.1] - 2026-11-09](#1251---2026-11-09)
  - [Fixed](#fixed-4)
- [[1.25.0] - 2026-11-08](#1250---2026-11-08)
  - [Added](#added-8)
  - [Changed](#changed-3)
- [[1.24.1] - 2026-11-08](#1241---2026-11-08)
  - [Security](#security-2)
  - [Added](#added-9)
  - [Known limitation](#known-limitation)
  - [Changed](#changed-4)
- [[1.24.0] - 2026-11-08](#1240---2026-11-08)
  - [Added](#added-10)
  - [Changed](#changed-5)
  - [Security](#security-3)
  - [Tests](#tests-1)
- [[1.23.0] - 2026-11-07](#1230---2026-11-07)
  - [Fixed](#fixed-5)
  - [Changed](#changed-6)
  - [Added](#added-11)
- [[1.22.0] - 2026-11-07](#1220---2026-11-07)
  - [Fixed](#fixed-6)
  - [Added](#added-12)
- [[1.21.1] - 2026-11-07](#1211---2026-11-07)
  - [Security](#security-4)
  - [Fixed](#fixed-7)
  - [Documentation](#documentation-1)
- [[1.21.0] - 2026-11-06](#1210---2026-11-06)
  - [Added](#added-13)
  - [Fixed](#fixed-8)
- [[1.20.0] - 2026-11-06](#1200---2026-11-06)
  - [Added](#added-14)
- [[1.19.1] - 2026-11-06](#1191---2026-11-06)
  - [Fixed](#fixed-9)
- [[1.19.0] - 2026-11-06](#1190---2026-11-06)
  - [Changed](#changed-7)
  - [Dependencies](#dependencies)
- [[1.18.0] - 2026-11-05](#1180---2026-11-05)
  - [Changed](#changed-8)
  - [Added](#added-15)
  - [Dependencies](#dependencies-1)
- [[1.17.0] - 2026-11-05](#1170---2026-11-05)
  - [Added](#added-16)
- [[1.16.2] - 2026-11-04](#1162---2026-11-04)
  - [Fixed](#fixed-10)
- [[1.16.1] - 2026-11-04](#1161---2026-11-04)
  - [Added](#added-17)
  - [Changed](#changed-9)
  - [Fixed](#fixed-11)
- [[1.16.0] - 2026-11-04](#1160---2026-11-04)
  - [Added](#added-18)
  - [Changed](#changed-10)
- [[1.15.1] - 2026-11-04](#1151---2026-11-04)
  - [Fixed](#fixed-12)
- [[1.15.0] - 2026-11-04](#1150---2026-11-04)
  - [Added](#added-19)
- [[1.14.1] - 2026-11-03](#1141---2026-11-03)
  - [Fixed](#fixed-13)
- [[1.14.0] - 2026-11-03](#1140---2026-11-03)
  - [Added](#added-20)
  - [Changed](#changed-11)
- [[1.13.2] - 2026-11-03](#1132---2026-11-03)
  - [Security](#security-5)
- [[1.13.1] - 2026-11-03](#1131---2026-11-03)
  - [Fixed](#fixed-14)
  - [Changed](#changed-12)
  - [Added](#added-21)
  - [Documentation](#documentation-2)
- [[1.13.0] - 2026-11-03](#1130---2026-11-03)
  - [Added](#added-22)
  - [Changed](#changed-13)
- [[1.12.0] - 2026-10-31](#1120---2026-10-31)
  - [Added](#added-23)
  - [Changed](#changed-14)
  - [Fixed](#fixed-15)
- [[1.11.1] - 2026-10-30](#1111---2026-10-30)
  - [Fixed](#fixed-16)
- [[1.11.0] - 2026-10-30](#1110---2026-10-30)
  - [Added](#added-24)
  - [Changed](#changed-15)
- [[1.10.0] - 2026-10-30](#1100---2026-10-30)
  - [Added](#added-25)
  - [Changed](#changed-16)
  - [Security](#security-6)
  - [Deprecated](#deprecated)
  - [Removed](#removed)
- [[1.9.0] - 2026-10-30](#190---2026-10-30)
  - [Added](#added-26)
  - [Changed](#changed-17)
- [[1.8.1] - 2026-10-29](#181---2026-10-29)
  - [Changed](#changed-18)
- [[1.8.0] - 2026-10-29](#180---2026-10-29)
  - [Added](#added-27)
  - [Changed](#changed-19)
  - [Fixed](#fixed-17)
  - [Removed](#removed-1)
- [[1.7.2] - 2026-10-28](#172---2026-10-28)
  - [Fixed](#fixed-18)
- [[1.7.1] - 2026-10-28](#171---2026-10-28)
  - [Security](#security-7)
- [[1.7.0] - 2026-10-26](#170---2026-10-26)
  - [Changed](#changed-20)
  - [Added](#added-28)
  - [Changed](#changed-21)
- [[1.6.4] - 2026-10-24](#164---2026-10-24)
  - [Changed](#changed-22)
- [[1.6.3] - 2026-10-18](#163---2026-10-18)
  - [Changed](#changed-23)
- [[1.6.2] - 2026-10-13](#162---2026-10-13)
  - [Changed](#changed-24)
- [[1.6.1] - 2026-10-13](#161---2026-10-13)
  - [Fixed](#fixed-19)
- [[1.6.0] - 2026-10-13](#160---2026-10-13)
  - [Fixed](#fixed-20)
  - [Deprecated](#deprecated-1)
  - [Changed](#changed-25)
- [[1.5.0] - 2026-10-13](#150---2026-10-13)
  - [Security](#security-8)
  - [Added](#added-29)
- [[1.4.1] - 2026-10-13](#141---2026-10-13)
  - [Changed](#changed-26)
  - [Dependencies](#dependencies-2)
- [[1.4.0] - 2026-10-09](#140---2026-10-09)
  - [Changed](#changed-27)
  - [Fixed](#fixed-21)
  - [Dependencies](#dependencies-3)
- [[1.3.5] - 2026-09-27](#135---2026-09-27)
  - [Fixed](#fixed-22)
  - [Added](#added-30)
  - [Changed](#changed-28)
- [[1.3.4] - 2026-08-24](#134---2026-08-24)
  - [Changed](#changed-29)
  - [Notes](#notes)
- [[1.3.3] - 2026-08-19](#133---2026-08-19)
  - [Changed](#changed-30)
- [[1.3.2] - 2026-08-18](#132---2026-08-18)
  - [Changed](#changed-31)
- [[1.3.1] - 2026-08-07](#131---2026-08-07)
  - [Fixed](#fixed-23)
- [[1.3.0] - 2026-08-04](#130---2026-08-04)
  - [Changed](#changed-32)
  - [Added](#added-31)
  - [Changed](#changed-33)
- [[1.2.0] - 2026-08-03](#120---2026-08-03)
  - [Added](#added-32)
  - [Changed](#changed-34)
  - [Compatibility](#compatibility)
  - [Upgrade](#upgrade)
- [[1.1.1] - 2026-07-31](#111---2026-07-31)
  - [Fixed](#fixed-24)
  - [Tests](#tests-2)
  - [Upgrade](#upgrade-1)
- [[1.1.0] - 2026-07-30](#110---2026-07-30)
  - [Added](#added-33)
  - [Changed](#changed-35)
  - [Documentation](#documentation-3)
- [[1.0.5] - 2026-07-29](#105---2026-07-29)
  - [Added](#added-34)
  - [Changed](#changed-36)
- [[1.0.4] - 2026-07-22](#104---2026-07-22)
  - [Added](#added-35)
  - [Changed](#changed-37)
- [[1.0.3] - 2026-07-16](#103---2026-07-16)
  - [Fixed](#fixed-25)
  - [Added](#added-36)
  - [Changed](#changed-38)
- [[1.0.2] - 2026-07-16](#102---2026-07-16)
  - [Changed](#changed-39)
- [[1.0.1] - 2026-07-16](#101---2026-07-16)
  - [Fixed](#fixed-26)
  - [Added](#added-37)
  - [Changed](#changed-40)
- [[1.0.0] - 2026-07-13](#100---2026-07-13)
  - [Added](#added-38)
  - [Security](#security-9)

## [Unreleased]

## [1.34.0] - 2026-11-14

Target 1.34.0.

### Added

- Member badge hook (spec 234, Beacon): `WikiMemberBadgeProviderInterface` (framework-free; `WikiMemberRef` class + id,
  `WikiMemberBadge` plain-text label, `neutral | info | agent` variant, optional title) puts host badges after
  revision authors in the history and comment authors in the comments panel (and `author.badges` in the comments JSON
  when there are some). One provider call per list (a history page, the comments panel's thread list), per-request
  memo bounded at 500 members, also for one large list (audit PQ-L2, PQ-L3), reset at every boundary (worker mode).
  Template `manage/_member_label_suffix.html.twig`, block `member_label_suffix`. Default `NullWikiMemberBadgeProvider`:
  no change.
- Write provenance (spec 234): `WikiWriteProvenanceProviderInterface::current(): ?WikiWriteProvenance` (channel
  `web | app | api | mcp`, actor label truncated to 120 characters) is asked for every new revision; nullable
  `wiki_page_revisions.channel` and `actor_label` columns (see UPGRADING). Shown escaped in the history; the API adds
  `created_by`, `channel`, `token_name` to pages and `channel`, `token_name` to revisions (OpenAPI updated). A stored
  channel outside the allow-list reads null (audit PQ-I2). Default `NullWikiWriteProvenanceProvider`: columns stay NULL.
- `history.show_actor_labels` (default: `history.show_names`; audit PQ-M1): the actor label / `token_name` is shown to
  its author, and to others only beside an author shown under a real name and when it passes the safe-name rule of
  display names; never beside an opaque "Editor xxxx" (`WikiActorLabels`). The author is recognised by the same object
  or by id only for `user_class` / `accepted_user_classes` (audit PQ-D1-L1); a page list never pairs a label with
  another write's author (PQ-D1-I1).
- Privacy (audit PQ-L1, PQ-D1-I2): `WikiCommentService::exportAuthorData()` and `wiki:comments:erase-user --export`
  export the user's actor labels (`revision_actor_labels`); `wiki:comments:erase-user` erases the user's actor
  labels and those of already-deleted authors (also with comments disabled); `WikiCommentService::eraseAuthor()` does
  too, through a listener on `nowo_wiki.comment.author_erased`. `WikiWriteProvenanceErasure` (`exportActor()`,
  `eraseActor()`, `eraseOrphaned()`) for hosts with their own flow; the label of a deleted author is never shown nor
  answered.
- `WikiPageProvenanceFinderInterface` (implemented by the Doctrine revision repository): creators and last-write
  provenance of a page list in two queries.

## [1.33.0] - 2026-11-14

### Added

- **Edit form in the read layout** (`web_ui.edit_view.layout: read`, default `form`): the edit form of a page looks like
  the page view with the editor in place of the body: the tree aside (rail and its fold state, the hidden-mode expand
  button), the reading column, the card frame and its toolbar (`page_header.toolbar_position: card`) and the page head
  with the in-wiki path. `WikiManageController::editPage()` (and the form shown again after a save conflict or a lost
  race) passes the page view's layout variables (`tree`, `tree_truncated`, `max_tree_pages`, `tree_search_href`,
  `tree_ui`, `tree_rail`, `space_icon`, `page_path`). New template block `wiki_page_form_body` (default: the UiKit
  form) inside `.nowo-wiki-edit-body`, which gives the editor the read typography (`--nowo-wiki-read-*`, both
  wiki.css copies). The layout is the new partial `manage/_read_scaffold.html.twig`, shared with the page view (its
  markup and blocks are unchanged).
- **Edit toolbar options** (`web_ui.page_header.edit_toolbar`): `current_tab: hidden` leaves the Edit tab out of the
  edit form; `back: view` adds a "View page" eye button (UiKit `view` icon; with `tabs: toolbar` it replaces "Back to
  page"). New key `wiki.action.view_page` in the 7 locales. Defaults unchanged.
- Every route of the loader carries its `nowo_wiki_route` option (`WikiRouteLoader::ROUTE_KEY_OPTION`), tenant copies
  included.

### Fixed

- **Breadcrumbs of tenant routes** (`web_ui.breadcrumb_current_label`): the edit, history, compare and move pages of a
  tenant route (`tenant_paths.name_prefix`, e.g. `app_project_nowo_wiki_page_edit`) showed a generic "Page" crumb, as
  `WikiBreadcrumbParentLabel` compared the bundle route names. The controller now puts the action's route key on the
  request (`WikiBreadcrumbParentLabel::ROUTE_KEY_ATTRIBUTE`, `remember($request, $title, $routeKey)`) and the listener
  generates the page view with the matched route's prefix (the bundle name as the fallback).

## [1.32.0] - 2026-11-13

### Added

- **Optional space icon (1.32.0)**: `WikiSpace::icon` (nullable string, UX icon name such as `tabler:book`) with
  `getIcon()` / `setIcon()`. The setter and the new `WikiSpaceIconType` field reject a value that is not
  `^[a-z0-9-]+:[a-z0-9-]+$` (a trailing newline too), is longer than 64 characters or has a prefix outside
  `nowo_wiki.space_icon.allowed_prefixes` (default `[tabler]`). New `WikiSpaceType` with the option `icon_field`
  (default `false`). The icon shows in the space list and in the sidebar of the space and page views (default book icon in the list, none in the sidebar).
  New column `icon` on `wiki_spaces`: see UPGRADING.

## [1.31.1] - 2026-11-13

Target: **1.31.1** (patch, security; house rule LQ — look-alike owner ids).

### Security

- **A look-alike user or team id listed, and so could open, another owner's spaces** (affects 1.0.0–1.31.0, MySQL and
  MariaDB only). `findAccessible()` matched `wiki_spaces.owner_scope_id` with SQL alone, on the column's default
  collation, and its result is the access source of `canAccessSpace()`, the space list, search, AI retrieval and the
  API. The MySQL 8 default `utf8mb4_0900_ai_ci` folds case and accents and ignores some invisible characters, and the
  PAD SPACE collations (MariaDB's default `utf8mb4_uca1400_ai_ci`, `utf8mb4_unicode_ci`, `utf8mb4_general_ci`) also
  ignore trailing spaces, so with `space_scope: user` the principal `Alice`, `álice`, `alice` + U+200B (or `alice ` on
  a PAD SPACE collation) reached the personal spaces of `alice` (reproduced on MySQL 8.4 and MariaDB 11.4). It needs
  owner ids that two principals can have in look-alike forms: user scope with a user that has no `getId()` (the
  `getUserIdentifier()` is used) or string ids, or a host team resolver returning such ids. Integer and UUID ids were
  not affected; PostgreSQL and SQLite compare exactly. Fixed in three layers:
  - every space lookup by owner (`findAccessible()`, `findBySlug()`) re-checks owner, scope type and slug with `===`
    in PHP;
  - an owner id with leading or trailing whitespace, a control or format character (zero-width space, bidi marks,
    BOM), invalid UTF-8 or more than 64 characters owns nothing: `WikiSpaceAccessResolver` drops it before the query
    and `WikiSpaceService::create()` refuses it (`InvalidArgumentException`), never trimmed or folded;
  - the new `WikiSpaceCollationListener` gives `owner_scope_id` a binary NO PAD collation in the generated schema
    (MySQL 8.0+ `utf8mb4_0900_bin`, MariaDB `utf8mb4_nopad_bin`, MySQL 5.7 `utf8mb4_bin`). Existing tables need an
    `ALTER TABLE`: see [UPGRADING › Unreleased](UPGRADING.md#unreleased).

### Tests

- `tests/Database/WikiSpaceLookalikeOwnerTest` runs the look-alike principals against the generated schema and against
  a non-migrated `utf8mb4_unicode_ci` column (MySQL / MariaDB jobs); unit tests for the id rule, the re-checks and the
  listener. CI gains a MariaDB 11.4 job for that test.

## [1.31.0] - 2026-11-12

### 1.31.0 (unreleased)

Target: **1.31.0** (minor; Symfony Beacon wiki UX review 2026-11-11). Every new option is off by default: with them off
the 1.30 pages render the same markup except for the fixes below. See [USAGE › 1.31 additions](USAGE.md#131-additions)
and [UPGRADING](UPGRADING.md#to-1310).

#### Added

- **`web_ui.page_header.title: card`:** the page view header never shows the title; when the body does not start with
  an `<h1>` equal to it, the title is printed in the content card after the path / sub-pages head and before the body
  (`.nowo-wiki-page-title`, `--nowo-wiki-read-h1-size`) — the page's `<h1>` when the header is not printed, else a
  visual line hidden from assistive technology beside the header's visually hidden `<h1>`.
- **`web_ui.page_meta: card`:** "v3 · Updated …" as the muted footer line of the content card instead of the scaffold
  meta list, the date in the reader's locale (`IntlDateFormatter` medium date + short time, request locale; `Y-m-d
  H:i` without ext-intl). Twig filter `nowo_wiki_datetime(locale)` (`Util\WikiDateFormat`).
- **`comments.layout: inline|overlay`:** `overlay` passes UiKit's side panel `layout: overlay` (a drawer at every
  width); it starts closed (the remembered state is not applied; `?comments=1` still opens it).
- **`comments.thread_list: plain|uikit`:** `uikit` gives the Milkdown thread list UiKit rows, neutral count badges with
  CLDR plurals (`wiki.comments.list.count_one|many|other`), a UiKit "Show resolved" checkbox and "Comment thread" for an
  untitled thread (`wiki-comments-list.ts`, re-applied on every redraw). Translated in the 7 catalogues.
- **Edit form with `page_header.tabs: toolbar`:** no back link and no space intro (the toolbar has "Back to page");
  Reset collaboration is a danger item of the ⋮ menu (UiKit split actions), still behind its confirmation.
- **`web_ui.breadcrumb_current_label` on the child pages:** the edit, history, compare and move pages name their
  BreadcrumbKit page crumb after the page (`Ui\WikiBreadcrumbParentLabel`, a `BreadcrumbTrailBuiltEvent` listener
  registered only with BreadcrumbKit; the title rides on the request, no service state). The `@?` integration stays
  optional.
- **Read tokens** `--nowo-wiki-read-h4-size` … `--nowo-wiki-read-h6-size` (h4–h6 also take the heading tokens) and
  `--nowo-wiki-read-callout-margin|padding|radius|gap|icon-size` for `.nowo-md-callout` (server render and
  MilkdownEditorBundle >= 1.17 read-only editor).
- **Sanitizer:** the disabled task checkbox of MilkdownEditorBundle >= 1.17
  (`li.nowo-milkdown-task > input.nowo-milkdown-task__box`) is kept: `<input>` is allowed with `type`, `class`,
  `disabled`, `checked` and `aria-label` only, then rebuilt as `type="checkbox"` + `disabled` with the box class;
  every other `<input>` (other type, outside a task item, not its first child) is removed. Hosts still cannot allow
  `input`. XSS tests: text input with handlers, image input with `src` / `formaction`, inputs outside task items,
  attribute and tag breaking in the label and class.
- Translation keys `wiki.page.meta_revision`, `wiki.page.meta_updated`, `wiki.comments.list.*`.

#### Fixed

- **Seamless swap / `title: dedupe` with Crepe:** the read-only editor's leading `.ProseMirror-widget` took the
  "first child" reset and the `> div` block margin, so the title moved 14–24 px down at the swap (and `dedupe` missed
  the heading). Widgets get no margin and the first non-widget block is the one reset. Chromium check
  `make check-seamless-swap` now also loads `tests/Browser/seamless-swap/widget.html` (div and span widgets, the
  deduped title) at 1280 and 390 px; it fails on 1.30's CSS and passes now.
- **Tree button chevron:** the toolbar's tree toggle (`tree_collapsed_mode: hidden`) pointed outward; it now points
  into the page (flipped in LTR and RTL).
- **Page head under 40rem:** `.nowo-wiki-card-frame > .nowo-wiki-page-head` no longer keeps the toolbar's width clear
  when the toolbar is its own row.
- **Search excerpts** no longer repeat the title: a leading heading (any level) equal to the title is left out.
- **"Content not visible" in the shared cache:** the viewer-neutral `content_html` stored the placeholder translated in
  the saver's locale (Spanish under an English UI). It now stores an empty, language-neutral marker
  (`Markdown\Milkdown\WikiNeutralTranslator`); plain text drops hidden references in any language (run
  `wiki:search:backfill-text --all` once). The neutral cache still resolves nothing (no title, id or URL of a hidden
  target), and readers keep getting the per-viewer render.
- **Audit NB-W-1 (Low):** the hidden-marker match of the plain text needed a whole class token (`-` was a boundary, so
  `foo-nowo-md-ref--hidden-x` matched), and authored HTML could wrap its own text in that marker to keep it out of
  `content_text` and search. The match now uses `(?<![\w-])nowo-md-ref--hidden(?![\w-])` on the `class` attribute
  only, and HTML revisions lose the renderers' `nowo-md-ref*` classes on save
  (`WikiHtmlSanitizer::withoutServerClasses()`); rendered Markdown keeps its marker.
- **Audit NB-W-2 (Info):** the auditor's fuzz payloads for the task-checkbox post-pass are pinned as regression tests
  (case, unquoted attributes, two inputs, comments, CDATA, `<input` in attributes / text / pre, forged and suffixed
  task classes, nested lists, svg / math / textarea wrappers, hostile labels, `checked` only as a value).

## [1.30.0] - 2026-11-11

Target: **1.30.0** (minor; Symfony Beacon 1.149 wiki follow-up). Every new option is off by default: the 1.29 pages are
unchanged. See [CONFIGURATION › Page layout](CONFIGURATION.md#page-layout-129) and
[USAGE › Page layout](USAGE.md#page-layout-129).

### Added

- **Host toolbar slot:** block `wiki_page_header_toolbar` on the page view and the edit form, also filled by the new
  `web_ui.slots.page_header_toolbar` template. Host buttons go into the toolbar of the compact header (or the end of
  the stacked tab row, or the content-card toolbar) before the tree and comments toggles, through the UiKit
  `page_header` contract (`toolbar`), so a host no longer overrides `nowo_ui_page_header` to add its buttons.
- **`web_ui.page_header.title: body`:** when the body starts with an `<h1>` equal to the title (the `dedupe` match,
  `WikiTitleDedupe::startsWithTitle()`), the page view header is drawn in the UiKit compact presentation: no visible
  title, no space subtitle, no breadcrumbs (the title stays a visually hidden `<h1>`). The body, server-rendered and
  read-only Milkdown alike, keeps its heading. No matching heading: the header is unchanged.
- **`web_ui.tree_collapsed_mode: hidden`** (with `tree_rail`): a collapsed tree is not drawn at all and the main
  column takes the width; an expand button (UiKit `rail_toggle`, `manage/_tree_toggle.html.twig`) sits in the page
  toolbar next to the comments toggle, shown only while the tree is collapsed. State as before (panel state store,
  `?tree=`); wiki.js moves the focus between the two toggles. Default `rail` (1.29).
- **`web_ui.reading_align: start`** (with `reading_width: readable`): the reading column sits next to the tree with
  the same maximum width. Default `center` (1.29).
- **`web_ui.page_header.tabs: toolbar`:** no tab row; on the page view Edit (pencil) and History (clock) are icon
  buttons of the toolbar, in the order tree toggle, Edit, History, host buttons, comments, actions / "more"; on the
  edit form and the history "Back to page" replaces the Page tab. The current view keeps `aria-current="page"`; the
  buttons are named by their translated labels. Twig function `nowo_wiki_tab_actions()` (`Ui\WikiTabActions`): the
  tab icon when the UiKit icon set can draw it, else a canonical kind. Default `row` (1.29).
- **`web_ui.page_header.toolbar_position: card`:** the page view toolbar moves to the top right of the content
  (server body and read-only editor, `.nowo-wiki-card-frame`), sticky while scrolling (`--nowo-wiki-card-toolbar-top`,
  `--nowo-wiki-card-toolbar-inset`), above the content (z-index 3) and before it in the tab order; the body's first
  block keeps clear of it (`--nowo-wiki-card-toolbar-width`, published by wiki.js). Under 40rem it is a row above
  the content. With `title: body` the page starts with the card and its `<h1>`. Default `header` (1.29).
  Audit MG: the toolbar is a `role="group"` named "Page actions" (its buttons stay in the tab order); focusable
  elements in the content get a `scroll-margin-block-start` of the toolbar's top + height + inset, so a focused link
  is never scrolled under it (WCAG 2.4.11; wiki.js publishes `--nowo-wiki-card-toolbar-height`); hosts with a sticky
  top bar set `--nowo-wiki-card-toolbar-top`. With `title: body` and `tabs: toolbar` the empty header is not printed,
  so the body's `<h1>` is the only one.
- **`web_ui.read_view.seamless_swap`** (literal boolean, default `false`): the read-only editor of commenters is
  mounted in the same box as the server body (`.nowo-wiki-read-swap`, one grid cell: the taller of the two is reserved
  from the first paint, so nothing moves), laid out invisibly, and cross-faded in when ready
  (`--nowo-wiki-read-swap-duration`, 150ms; no fade with `prefers-reduced-motion` or UiKit `data-motion="reduce"`).
  Audit MG-3: every non-editor child of the box (also a host `wiki_page_body` without the marker) is hidden and made
  inert at the swap, after the focus it held moved to the editor (same link, else the view).
  The editor gets the body's UiKit `card` / `card_body` box and drops its own frame; both bodies take the same
  typography from `--nowo-wiki-read-*` tokens (h1–h3, p, lists, code, pre, blockquote, tables, links). The server
  body leaves the tab order and the accessibility tree at the swap (`inert`, `aria-hidden`). Checked in Chromium at
  1280 and 390 px with the real Milkdown editor: same box, every block at the same y, CLS 0
  (`make check-seamless-swap`, `tests/Browser/seamless-swap`).
- **`web_ui.page_header.path: card`:** the in-wiki path (space › ancestors) above the page body, each level a link
  (`manage/_page_path.html.twig`). More than three levels fold the middle ones into a "…" disclosure menu; under 30rem
  only "‹ Parent" shows. An ancestor the viewer may not read is "…" with a generic hidden text: no link, no title, no
  name in any attribute (defence in depth: the read rule already hides pages below a hidden one). No extra query (the
  ancestors are those the tree already loaded). An archived ancestor is plain text, not a link to a 404 (MG-2-L4).
  Default `none`.
- **`web_ui.children: auto|always`:** the readable, non-archived direct sub-pages in tree order, as a "N subpages" chip
  above the body (a disclosure with the list) and a "Pages in this section" card after the body. One query
  (`WikiPageChildrenFinderInterface::findActiveChildren()`, implemented by `DoctrineOrmWikiPageRepository`; a host
  repository without it falls back to `findActiveBySpace()` bounded like the tree), at most `pagination.max_tree_pages`
  readable pages: the cap counts readable pages only (hidden ones are skipped in further batches when needed) and more
  shows as "N+ subpages" (`truncated`; audit MG-2-L1/L3). `findActiveChildren()` reads with `HINT_REFRESH` (MG-2-L2), at most 3 batches over a total order with ids
  deduplicated (MG-2-L5/L6).
  `auto` shows both only while the tree is collapsed or on one column. Default `never`.
- **`web_ui.breadcrumb_current_label`** (literal boolean, default `false`): with BreadcrumbKit >= 2.17 installed, the
  page view names the current crumb after the page title through
  `Nowo\BreadcrumbKitBundle\Service\BreadcrumbCurrentLabelInterface` (optional reference; without BreadcrumbKit or
  with an older one nothing happens). `composer.json` suggests `nowo-tech/breadcrumb-kit-bundle` ^2.17; no conflict.
- Translation keys `wiki.page.toolbar`, `wiki.path.label|hidden|more`, `wiki.children.count|count_more|title`.

### Fixed

- **`title: dedupe` on the read-only Milkdown view:** editors (and commenters with the page source) saw the title
  twice. wiki.js set `hidden` on the editor's first `<h1>`, which ProseMirror reverts at once (it redraws a node whose
  DOM attributes change). The view wrapper now gets `data-nowo-wiki-title-deduped` and wiki.css hides
  `.ProseMirror > h1:first-child`; the editor's own DOM is never touched.
  The server prints the marker when the body starts with the title (`comments_view.title_deduped`), so the
  heading is hidden from the editor's first paint, not when it becomes ready.

## [1.29.1] - 2026-11-11

Target: **1.29.1** (patch, security; REQ-RUNTIME-002 — request memos correct without the services resetter).

### Security

- **Revoked space memberships kept granting access where the services resetter does not run** (affects 1.13.0–1.29.0,
  default space checker). `LegacyWikiSpaceAccessChecker` memoised the spaces of a user per user object and emptied the
  memo only on `kernel.reset`. This mattered only when the services resetter does not run between requests: a host that removes or bypasses `services_resetter` or drives `http_kernel` directly, Messenger consumers started with `messenger:consume --no-reset`, and long-running console commands. The stock Symfony kernel still runs `services_resetter` before every request in FrankenPHP worker mode, even with `FRANKENPHP_RESET_KERNEL=false` (`Kernel::handle()` → `boot()`), so a stock worker was not affected. Without the resetter
  the host's EntityManager keeps the signed-in user managed, so the firewall hands the same object to every later
  request of that worker, and a membership revoked in the database (team left, space moved to another owner) kept
  granting the space until the worker restarted. The memo now belongs to one main request (dropped when the main request
  changes, not kept at all outside a request: console and Messenger read memberships on every call), and memberships are
  re-read through `WikiSpaceAccessResolverInterface` (a query) when a new request starts; team memberships (team mode)
  come from the host's `WikiTeamMembershipResolverInterface`, which decides how it reads them. Hosts
  with their own `WikiSpaceAccessCheckerInterface` (Beacon) are not affected by this checker, but should check their own
  memos the same way.
- New `WikiRequestBoundaryResetter` (internal, registered by the bundle) empties every request memo — the space checker,
  `WikiAvatars` (avatar URLs), `WikiFilePreviewFrames` (preview frame tokens), `WikiRenderContext` and
  `WikiEventDeferral` — on the main `kernel.request`, `kernel.terminate`, `console.terminate` and Messenger
  `WorkerMessageHandledEvent` / `WorkerMessageFailedEvent`, in addition to `kernel.reset`. It resets only memos already
  built (`IGNORE_ON_UNINITIALIZED_REFERENCE`, as the services resetter does), so no boundary instantiates a service.
  Before, a process without the resetter served avatar URLs a user had since changed and let the memos grow up to their bounds.
- Tests: `LegacySpaceCheckerWorkerTest` (one kernel, requests straight through `http_kernel`, no services resetter, the
  host EntityManager never cleared): a membership revoked with plain SQL is refused on the next request, and two people
  in turn over 60 rounds never see each other's spaces, a space moved half-way changes hands at once, and memory stays
  within 2 MiB; a boundary builds no memo but still empties one in use. Unit tests for the request-scoped memo and the
  boundary subscriber.

## [1.29.0] - 2026-11-11

Target: **1.29.0** (Symfony Beacon 1.149 wiki plan, **block A: layout** — the comments aside as a drawer, a
collapsible page tree rail, a readable column with code / table overflow, a compact header). Needs
`nowo-tech/ui-kit-bundle` **^2.48**. Every option is off by default; see [UPGRADING](UPGRADING.md#to-1290) for the
two default changes (wide content scrolls inside the column; the UiKit scroll-region script on the read view). Page
templates (block B) follow in 1.30.

### Added

- **Comments dock breakpoint (`comments.dock_min_width`: `md` default | `lg` | `xl`).** The panel docks as a column
  only from 768 / 1200 / 1440 px and is the overlay drawer (focus moved in and back, Escape, backdrop, inert page,
  Tab cycle — UiKit side panel) below. Passed to UiKit 2.48 `drawer_below` on the panel and its container. A panel the
  server renders open (the remembered state) starts **closed** below an `lg` / `xl` breakpoint instead of opening as a
  modal drawer on every load (UiKit audit LV-L2); the toggle opens it as a drawer. `?comments=1` (notification links,
  the no-JavaScript link, the reload of a server-rendered list) still opens it: `wiki.js` opens it after load
  (`data-nowo-wiki-comments-requested`).
- **Remembered state without a session.** With the default session store and no session (stateless firewall), the
  comments panel and the tree rail keep their state in `localStorage` (UiKit `persist`, keys `wiki-comments` /
  `wiki-tree`); the blocking UiKit `js/nowo-ui-layout-state.js` is printed in the head when needed (lg / xl dock or a
  `persist` key) so the remembered state applies before the first paint. A host store (`comments.state_store`) stays
  primary and never uses `localStorage`.
- **Page tree rail (`web_ui.tree_rail`, literal boolean, default `false`).** The tree card of the space home and the
  page view gets the UiKit `rail_toggle` ("Collapse Pages" / "Expand Pages", `aria-controls`, `aria-expanded`, a link
  to `?tree=rail|full` without JavaScript). Collapsed, the side column shrinks to `--nowo-ui-rail-width` (2.75rem)
  with "Pages" written vertically; the title and the tree are `display: none` (out of the tab order and the
  accessibility tree); focus stays on the toggle; no width transition under reduced motion. Below 62rem a collapsed
  tree is a one-row bar. The state is per user in the panel state store (`WikiPanelStateStoreInterface::TREE_RAIL`,
  "open" = collapsed, so nothing stored keeps the expanded tree), posted by `js/nowo-ui-rail.js` to the existing
  `space_panel_state` endpoint (same CSRF token and same-origin check), which is now registered with the comments
  **or** the rail. New service `Panel\WikiTreeRail` (stateless).
- **`web_ui.narrow_tree_position`** (`before` default | `after`): on the page view below 62rem, the tree after the
  page (replaces a host `order: 2` rule).
- **`web_ui.reading_width`** (`full` default | `readable`): the page view's main column centred at
  `--nowo-wiki-reading-width` (default the UiKit `--nowo-ui-reading-width`, `75ch`; class `nowo-wiki-reading` +
  UiKit `nowo-ui-readable`).
- **Compact header (`web_ui.page_header.layout`: `stacked` default | `compact`).** On the read and edit views the
  page tabs and the actions share one row under the title (UiKit 2.48 `tabs_inline`); the read view's actions use the
  UiKit `split` layout (icon buttons, one primary, a "more" menu) and the comments toggle sits at the end of that row,
  so the separate tab row is not printed. New partials `_page_header_compact.html.twig` and `_comments_toggle.html.twig`
  (the toggle, extracted from `_page_tabs.html.twig`; same markup).
- **Title dedupe (`web_ui.page_header.title`: `both` default | `dedupe`).** On the read view a body that starts with
  an `<h1>` whose text equals the page title (trimmed, whitespace collapsed, case-insensitive) does not repeat it:
  the server body drops it at render time (`Util\WikiTitleDedupe`) and `wiki.js` hides that heading in the read-only
  Milkdown view (`data-nowo-wiki-dedupe-title`; the document and the comment anchors are unchanged). A heading with
  more than text (image, media, math) is kept, and a dropped heading's `id`s stay on empty
  `<sup class="nowo-wiki-anchor">` elements (audit MA-I1). The stored
  revision, the editor, search, export and the API are untouched.
- Twig blocks `wiki_page_body` (the body card) and `wiki_page_after_body` (after it, empty; e.g. backlinks) on the page
  view.

### Changed

- **Wide content no longer widens the page (default).** The main column is a `minmax(0, 1fr)` grid whose items may
  shrink (`min-width: 0`), so a long code line or a wide table stays inside the column (the read-only Milkdown view's
  CodeMirror blocks included: `.cm-editor` `max-width: 100%`, `.cm-scroller` scrolls). Hosts can drop their own
  workaround (Beacon 1.148 `.nowo-wiki-comments-view { min-width: 0 }` / `.cm-editor { max-width: 100% }`).
- **Tables scroll sideways (default).** Each table of the page body is wrapped after sanitizing in a labelled scroll
  box (`.nowo-wiki-table-scroll`, a UiKit scroll region: focusable `role="region"` named "Table" while it overflows;
  new Twig filter `nowo_wiki_scroll_tables`, label `wiki.table_scroll` in 7 locales). The table keeps
  `display: table` and its semantics (audit MA-L1) and is as wide as its content (it was `width: 100%` and could push
  the card past the viewport). `<pre>` keeps `overflow-x: auto` and gets `max-width: 100%`.
- **Only the desktop (column) state of the comments panel is stored.** Opening or closing the panel while it is the
  overlay drawer (at or under its `dock_min_width` breakpoint, or `layout: overlay`) no longer posts to the panel state
  store, so a phone or tablet toggle never overwrites the choice made on a wide screen (the UiKit `persist` fallback
  already keeps only the column state). Applies to every breakpoint, `md` included.
- Compact header (`page_header.layout: compact`): Move, Delete and Archive close the "more" menu in the danger style,
  each with its own verb (`move`, `delete`, `archive` action kinds and icons); the stacked header keeps the 1.28 kinds.
- The read view loads UiKit `js/nowo-ui-scroll-region.js`: an overflowing code block or table gets a tab stop (axe
  `scrollable-region-focusable`). `wiki-code-blocks.ts` (1.17) is **deprecated** (it still names the code blocks it
  marks and covers pages without the UiKit script; removed in 2.0).
- `nowo-tech/ui-kit-bundle` constraint `^2.31` → `^2.48`.
- `WikiCommentsPanel::panel()` adds `drawer_below`, `persist` and `open_requested`; `WikiCommentController` and
  `WikiManageController` take an optional `$layout` (and the manage controller `?WikiTreeRail $treeRail`);
  `WikiPageFactory` an optional `$compactHeader`. All optional, defaults keep 1.28.
- The Twig global `nowo_wiki_web_ui` gains `layout` (`tree_rail`, `narrow_tree_position`, `reading_width`,
  `page_header`, `title`); parameter `nowo_wiki.web_ui.layout`.

- **Igor worker-state audit in CI.** A standalone `igor` job runs `composer igor` (igor-php, REQ-CS-008) on every push
  and pull request. The four findings igor reported on `src/Api/*` are false positives, each justified inline with
  `// @igor-ignore` and recorded in [FRANKENPHP-WORKER-AUDIT](FRANKENPHP-WORKER-AUDIT.md#igor-findings-2026-11-11):
  the download `Response` is built per call, the comment-thread closure runs synchronously, the failed-token counter
  lives in the host rate limiter by design and `WikiApiInput` is a per-request value object. No behaviour change.

## [1.28.0] - 2026-11-10

Target: **1.28.0** (Symfony Beacon spec 212 block B: file previews on the wiki read page). See
[UPGRADING](UPGRADING.md#to-1280). Works with the required MilkdownEditorBundle `^1.13` (no new Milkdown release
needed).

### Added

- **File previews in the per-viewer render (`editor.milkdown.file_previews`, default `true`, only with
  `file_cards: card`):** a `::file[…]{… view="preview"}` card now frames the preview URL the host's Milkdown
  `FilePreviewResolverInterface` approves for it, instead of always reading as a plain card. The resolver is called
  once per render, inside the render's `WikiRenderFrame` (space, page, reach; new `WikiMilkdownFilePreviewResolver`),
  and only about cards the viewer may see (a refused host file still reads *Content not visible*). Under the REST
  API's reach only host-reference cards are asked about (they already passed the reach-aware host file resolution); a
  plain-URL card gets no preview there.
- The frame is Milkdown's `FileCard` frame: `sandbox` from `nowo_milkdown_editor.markdown.file_preview_sandbox`
  through `FileCard::sandbox()` (never `allow-scripts` with `allow-same-origin` or `allow-popups-to-escape-sandbox`),
  `loading="lazy"`, `referrerpolicy="no-referrer"`. The preview URL must also pass the wiki link policy
  (`WikiLeafDirectiveRenderer::safeUrl()`: no `javascript:`, `data:`, `http:`, `//host` or ambiguous paths).
- **`WikiFilePreviewFrames`** (request-scoped, `kernel.reset`, at most 1000 frames): the wiki sanitizer never lets a
  sandboxed `<iframe>` through, so the render writes an empty placeholder with a 128-bit random token and
  `nowo_wiki_safe_html` puts the frame back after sanitizing. Only frames this request's render registered are
  restored; any other placeholder (stored HTML, HTML revisions, comments, author text) is removed, never framed.
  Tokens are single-use (audit KG-W-I1).
- The reader's Markdown (`WikiViewerMarkdownView`, the REST API `markdown` for readers) renders without preview frames
  and strips a preview card's *Open* / *Download* actions, so a card still reads `[name](url)` as in 1.27 and registers
  no frame (audit KG-W-L1, KG-W-I4). Comment thread JSON `html` keeps an empty placeholder span where the panel shows
  the frame (KG-W-I3).

### Changed

- `MilkdownWikiMarkdownRenderer` takes two optional arguments (`$filePreviews`, `$previewFrames`),
  `WikiLeafDirectiveRenderer` two (`$previewFrames`, `$previewSandbox`) and `WikiHtmlTwigExtension` one
  (`$previewFrames`). With `file_cards: link` (the default), `file_previews: false`, or no resolver answer (Milkdown's
  default resolver without a `preview` template) the output is byte-for-byte the 1.27 output.

## [1.27.0] - 2026-11-09

Target: **1.27.0** (DDD / hexagonal adapter fixes, audit 2026-11-09, P1 "the security user must be the entity").
See [UPGRADING](UPGRADING.md#to-1270).

### Added

- **User reference resolver.** New port `User\WikiUserReferenceResolverInterface` (default
  `DoctrineWikiUserReferenceResolver`, aliased): revision authors, link creators, uploaders, comment / thread authors,
  resolvers and reactions are stored through it. `user_class` instances are stored as before; an instance of a class
  listed in the new option **`accepted_user_classes`** (default `[]`; a security user that is no Doctrine entity)
  becomes `getReference(user_class, id)` on the wiki entity manager. Any other user is refused
  (`AccessDeniedException`, audit JX-W1), and ids ≤ 0 / empty ids are refused (`User\WikiUserClassPolicy`). Comment
  ownership ("my comment", "my reactions") also matches a listed class by id; other classes keep the 1.26
  identifier rule only. The privacy export / erasure of comments accept a listed user too.
- **Scalar actor ids on the events.** `actorId(): ?string` on `WikiPageSavedEvent`, `WikiPageCreatedEvent`,
  `WikiPageArchivedEvent`, `WikiPageUnarchivedEvent`, `WikiPageRestoredEvent`, `WikiPageLinkEvent`,
  `WikiPageMovedEvent`, `WikiTrashEvent`, `WikiPageFilesPurgedEvent`, `WikiAttachmentEvent` and `WikiCommentEvent`;
  payloads unchanged. Helper `User\WikiUserId::of()`.
- **Service ports.** `Service\WikiPageServiceInterface` (create, saveRevision, restore, archive, unarchive),
  `Service\WikiSpaceServiceInterface` (create, findBySlug) and `Link\WikiPageLinkServiceInterface` (link, unlink,
  usedIn): public aliases of the existing services.
- USAGE "Using it from a DDD / hexagonal application": known limitations updated.

## [1.26.0] - 2026-11-09

Target: **1.26.0** (minor: schema change, and `WikiPageRevision::getAuthor()` returns `?object`).

### Fixed

- **Deleting a user no longer deletes the revisions they wrote (data loss).** `<prefix>_page_revisions.author_id` was
  `NOT NULL ... ON DELETE CASCADE`: deleting a host user deleted every revision they had written, and the pages whose
  current revision was theirs lost it (`current_revision_id` became `NULL`, the page showed no content). The column is
  now nullable with `ON DELETE SET NULL`: the revisions and the pages stay, and the history shows the author as
  **Former person** (`wiki.history.former_person`, the wording of TimeTrackBundle's former person). The revision keeps no
  other trace of the deleted user (known limitation: co-editing's `seeded_by`, fixed in 1.26.1; see UPGRADING). A revision of a former person is a session of its own in the timeline, and `wiki:history:thin`
  never removes it as an older save of somebody's session. The REST API answers `"author": null` for it, as the
  schema already allowed. **The schema change is required:** see [UPGRADING.md](UPGRADING.md#to-1260).
- The other six relations to the user class were checked: links (`created_by_id`), attachments (`uploaded_by_id`),
  comment threads (`created_by_id`, `resolved_by_id`) and comments (`author_id`) were already `SET NULL`. Comment
  reactions (`user_id`) stay `ON DELETE CASCADE` on purpose: a reaction is the user's own mark, not content.
- Comments by a deleted user now read **Former person** too (`wiki.comments.unknown_author`, was *Deleted user*), the
  same wording as the history.

### Changed

- **`WikiPageRevision::getAuthor()` returns `?object`** (was `object`): null once the author's account was deleted.
  Host code calling it must handle null. The constructor's `$author` is `?object` too, as `WikiComment`'s (the bundle's
  services always pass the writing user).
- `WikiHistoryTimeline::rows()` rows carry `deleted` (bool); `WikiPageFactory::history()` rows carry
  `author_deleted`, and `page_history.html.twig` prints *Former person* for them
  (`data-testid="wiki-history-former-person"`).

### Documentation

- USAGE: new section "Using it from a DDD / hexagonal application" (where the bundle sits in a hexagonal host, the ports, events and DTOs to build an anti-corruption layer on, and the known limitations).

## [1.25.1] - 2026-11-09

### Fixed

- **Long pages no longer open scrolled to the bottom on phones (MUX-80).** `wiki.js` brought the current page of the
  tree into view with `scrollIntoView`, which also scrolls every ancestor: on narrow screens the tree is stacked under
  the page body, so long pages opened at the tree. The script now scrolls only the tree's own container (the tree or
  its sidebar body when it is a height-capped scroller, i.e. the sticky side column on wide screens), by computing the
  offset and setting `scrollTop`; when the tree flows with the page it does nothing. The page and window are never
  scrolled. The other bundle scripts (comments, moderation, code blocks) have no such call; the tree's keyboard
  moves only focus the moved item after a user action. New exports `treeScroller()` / `revealInScroller()`; Vitest
  cases and a Playwright check at 390 × 844.

## [1.25.0] - 2026-11-08

Opt-in `editor.milkdown.file_cards: card` draws `::file` with Milkdown's file card through the reach-aware host file resolution (WK-8), and Edit and History are no longer duplicated in the page header (WK-6). Audit: Appendix IK and Delta IK, Pass-Low (IK-L1 documented).

Two Beacon requests (2026-11-08): file cards drawn by Milkdown in the per-viewer render (WK-8), and no duplicated Edit /
History buttons on the page view (WK-6). Works with nowo-tech/milkdown-editor-bundle **^1.13** (no new Milkdown API).

### Added

- **`nowo_wiki.editor.milkdown.file_cards`** (WK-8): `link` (default, as 1.24) or `card`. With `card`, a
  `::file[name]{href size type}` card no host `DirectiveRendererInterface` supports reads, in the per-viewer render of
  pages and comments (the UI page body and the API reader view), as Milkdown's own file card (`FileCard::render()`:
  badge, name, size; `link`, `icon` and `card` views; no preview frames). Its link is the URL Milkdown's host file
  resolution gave the card (`host_files`, with the render's `WikiRenderFrame`, so the reach applies), and it must also
  pass the wiki link policy (`WikiLeafDirectiveRenderer::safeUrl()`), else the card has no link. A refused card keeps the
  neutral *Content not visible* placeholder, without uuid, name or link. A host that drew `file` with its own renderer
  only to get the card can remove it and set `file_cards: card` once it runs Milkdown ≥ 1.14 and lists every link
  host in `host_url_hosts` (audit IK-L1, see UPGRADING); while a host renderer supports `file`, it keeps the card (and
  Milkdown skips the card's `href` resolution, as before). `card` hides refused files only while host files are
  resolved (IK-I4); with `url: resolved` a visible card shows the resolver's name and type (IK-I5). The neutral `content_html` render keeps the plain
  link.
- `MilkdownWikiMarkdownRenderer` takes an optional trailing `bool $fileCards`, `WikiLeafDirectiveRenderer` an optional
  third `bool $fileCards`.

### Changed

- **Page view header (WK-6):** Edit and History are no longer header actions of `WikiPageFactory::pageView()`; since 1.16
  they are tabs of `page.header.tabs` (Page / Edit / History, same rights), which the bundle's page view always prints.
  The header keeps Save version, Move, Delete and Archive.
- The API reader view (`WikiViewerMarkdownView`) leaves a file card's badge and size out of the reader's Markdown, so a
  card reads `[name](url)` as the plain link did.

## [1.24.1] - 2026-11-08

Host files referenced by plain URL now respect the API's reach. Requested by Beacon after the 1.24 REST API audit
(spec 218, Appendix IF): Milkdown pages reference Beacon files as `/files/<uuid>/original` or `/files/<uuid>/preview`,
and the API's reader view (`WikiViewerMarkdownView`) returned those URLs and their alt texts even when the token's
*where* excluded the file's wiki. The download endpoint still checked access, so no file bytes leaked. Needs
nowo-tech/milkdown-editor-bundle **^1.13**. Audit: Appendix IJ, Pass-Low after the fixes (Delta IJ, 2026-11-08); IJ-L4 accepted by the owner as a known limitation.

### Security

- **Host files follow the render's reach.** The per-viewer render of pages and comments (the UI page body and the API
  reader view) resolves host file references through the host's Milkdown `HostFileResolverInterface`. This covers
  Milkdown's `host_schemes` values and, with Milkdown 1.13's `host_url_patterns`, plain URLs such as
  `/files/<uuid>/original`. The resolver gets the render's `WikiRenderFrame` (space, page, reach). A file the resolver
  refuses reads *Content not visible*, with no URL, uuid, alt text, link text or card name.
  - A resolver that is not a `ContextualHostFileResolverInterface` cannot see the reach, so under one (the API's
    *where*) it resolves nothing.
  - Without a reach (the UI), the output for a person who may see the files is unchanged.

### Added

- `nowo_wiki.editor.milkdown.host_files`: `auto` (default: only when Milkdown's `host_url_patterns` is set), `on` (also
  with `host_schemes` alone) or `off` (as 1.24).
- `WikiMilkdownRenderContext` (Milkdown `RenderContextProviderInterface`, gives the current `WikiRenderFrame`) and
  `WikiMilkdownHostFileResolver` (wraps the host's resolver as described above).
- `WikiLeafDirectiveRenderer` renders a refused `::file` card as Milkdown's neutral placeholder (translated `ref.hidden`).
- `WikiLeafHostFiles` (audit IJ-L2): the `url`, `href` and `link` attributes of leaf directives that name host files
  (`::download{href=…}`, `::file{url=…}`) go through the resolver with the frame; refused or unchecked ones render the
  placeholder, and a `::file` whose `href` is a host file links only that `href`.

### Known limitation

- Search matching and excerpts, and HTML revisions' `content_text`, use the viewer-neutral plain text: link text and
  `::file` labels of files outside a token's *where* can still appear there (audit IJ-L4, no uuid). See
  [API](API.md).

### Changed

- `nowo-tech/milkdown-editor-bundle` constraint raised to **^1.13** (conflict `<1.13.0`).
- `MilkdownWikiMarkdownRenderer` takes three optional trailing arguments (`$hostSchemes`, `$hostFiles`,
  `$renderContext`), wired by the bundle. The neutral render (stored `content_html`, search, AI) never resolves host files.

## [1.24.0] - 2026-11-08

REST API for wikis, pages, revisions, search, comments and files (spec 218, opt-in `api.enabled`), base-revision saves that answer 409 on a stale write, a reader's Markdown view, and page events after the co-editing commit.

### Added

- **Saves from a known revision (spec 218).** `WikiPageService::saveRevision()` and `restore()` take an optional last argument, `$expectedRevision`. With it, the page row is locked in a transaction (`WikiPageLockInterface`, implemented by the Doctrine page repository), the revision is compared under the lock, and a stale one throws `WikiRevisionConflictException` (current revision, its author and date) with nothing written. A save whose title and body equal the current revision's writes nothing and returns it. Events fire after the commit. Without the argument, saves behave exactly as before (last write wins). Proven on MySQL and PostgreSQL with two concurrent processes.
- **The reader's Markdown (spec 218).** `WikiViewerMarkdownView` gives a reader who may not edit a page the Markdown of what their render shows, never the source: links to pages they may open stay `[[wiki:<uuid>]]`, the others read *Content not visible*, and whatever the render hides (reference definitions, link targets, directive attributes, raw HTML when stripped) is absent. A source over the renderer's limits, which the renderer would show unparsed, gives a fixed placeholder instead (`WikiViewerMarkdownView::TOO_LARGE`, audit IF-L1).
- **Render context (spec 218).** `WikiRenderContext` names the space and page of every per-viewer render (page body, comment bodies, the reader's Markdown), so resolvers and directive renderers, the host's included, need not read route attributes. A render may carry a `WikiRenderReach` that narrows what resolves: the bundle's page-link resolver honours it and records the links it resolved. A nested render's own reach is intersected with the outer one (audit IF-I7); the context is emptied on `kernel.reset`.
- **Reads for the REST API (spec 218).** `WikiSearchService::searchPlainText()` matches titles and plain text only, never the Markdown source, case-insensitively and in page-id order. `WikiPageMetadataFinderInterface::pageMetadata()` (Doctrine page repository) lists a space's pages with their current revision's number, date and author id in one query. `WikiCommentService::threadEntities()` and `threadEntity()` return threads and comments as entities after the same rights check as `list()` and `thread()`.

- **REST API, skeleton (spec 218).** `nowo_wiki.api` (off by default: no route, service, listener or parameter). With `enabled: true` and the host's `authenticator` and `directory` hooks, a stateless JSON API under `api.prefix` (`/api/wiki/v1`): every request is authenticated by the host, rate limited, checked against the security user (the API must sit behind a secured stateless firewall), and answered `{"err", "ECODE"}` on refusal with `Cache-Control: no-store, private` and `X-RateLimit-*`. A token's level (`read`, `edit`, `full`) is a closed table over `WikiAction`; its *where* only narrows the person's access. `GET /wikis` and `GET /wikis/{wiki}`. The rate limiter and the plan gate deny until the host sets them.
- **REST API, reads (spec 218).** `GET /wikis/{wiki}/pages` (metadata, filters `parent`, `archived`, `updated_since`; every row checked with its ancestors; authors loaded in one query), `GET …/pages/{page}`, `…/revisions`, `…/revisions/{number}`, `…/revisions/{number}/diff?against=`, `…/threads`, `…/threads/{thread}` and `GET /search`. Bodies are the source only for an Edit token whose person may edit the page, otherwise the reader's Markdown with the token's reach; people are named by the history policy, with an id only for the token's own user. Search reads at most `api.search_candidates` rows, never matches the source, matches a page over the Markdown limits on its title only (audit IF-L1) and drops hits found only in markup (IF-I3); `total` counts only readable hits.

- **REST API, page writes (spec 218).** `POST /wikis/{wiki}/pages`, `PUT …/pages/{page}` (mandatory `base_revision`: a stale one is 409 `WIKI_REVISION_CONFLICT` with the current revision, its author per the history policy and its date, nothing written; an unchanged save adds nothing; an empty body over a non-empty page needs `allow_empty`), `…/move`, `…/archive`, `…/unarchive` (own pages at Edit, anyone's at Full) and `…/revisions/{number}/restore`. Writes need the host's audit listener (`WikiEvents::API_WRITE`, else 503 `AUDIT_001`) and, by default, a plan-limit listener (`api.quota_listener_required`); the first write of a wiki nobody opened creates it through the directory hook. With co-editing on, a save over live updates no revision has is 409 `WIKI_LIVE_SESSION`. `Idempotency-Key` on every write (table `<table_prefix>_api_idempotency`, only with the API on; `nowo:wiki:api:purge-idempotency`).

- **A co-editing hub that fails no longer refuses a saved comment (audit IF-L6).** The live update of a comment write is best effort: when the hub fails, the comment stays saved, the failure is logged by class, and the answer (UI or API) is a success; viewers see the change on their next load.
- **REST API, comments (spec 218).** `POST …/threads` (page-level; `anchor` is answered `not_supported`), `POST …/threads/{thread}/comments`, `PATCH …/threads/{thread}` (`resolved`), `PATCH` and `DELETE …/comments/{comment}` (one's own at Edit; others' comments are never edited through the API, deleted only at Full with `ModerateComments`), `DELETE …/threads/{thread}` (Full, `ModerateComments`). Through `WikiCommentService`, so its caps, rate limit and events apply as in the UI.

- **REST API, wiki lifecycle and files over host hooks (spec 218, Beacon Q-B1).** `POST /wikis`, `PATCH /wikis/{wiki}`, `POST /wikis/{wiki}/archive|unarchive` (Full; `Manage` on an existing space) through `api.hooks.wiki_management` (default: 403 `WIKI_001`); `GET …/pages/{page}/files`, `POST /wikis/{wiki}/files` (multipart `file`, Edit) and `GET /wikis/{wiki}/files/{file}` through `api.hooks.files` (default: no files). A wiki whose space exists is managed only by a person who can open it and holds `Manage` (404 otherwise); the management hook names the new wiki's owner key (`ownerKey()`), which must be within the token's *where* before anything is created; a hook that files the wiki under another owner is audited, logged and answered 500 with its idempotent claim kept (audit IF-L7, IF-L8). Every check of an upload is the host's; every download leaves as an attachment with `nosniff` and `Content-Security-Policy: default-src 'none'; sandbox`.

- **REST API documentation (spec 218).** `docs/API.md` and `docs/openapi.yaml` (OpenAPI 3.1), kept in step with the routes and the answers by `ApiOpenApiTest`; configuration in `CONFIGURATION.md`.

### Changed

- **Page events wait for the co-editing log's commit (audit IF-L2).** A save under the log's lock (the page form outside the live session, a restore, an API save) dispatches `PAGE_SAVED`, `PAGE_CREATED` and `PAGE_RESTORED` after the lock's transaction committed, and not at all when it rolled back; before, they fired inside it, so listeners could act on a write that was then undone. The bundle's own co-editing bookkeeping moved to a new internal event, `WikiEvents::PAGE_WRITTEN`, which still runs inside the write. Outside the lock nothing changes. **A listener that writes to the database (backlinks, file references) no longer shares the save's transaction:** if it fails, the save stays and that listener's data is stale until the next save. In such a scope a failing listener is logged by class and the next listeners still run (audit IF-L3).
- **A save from an expected revision reads the page and its current revision with locks, and numbers the new revision after the current one (audit IF-I2):** inside an outer MySQL REPEATABLE READ transaction a plain read could miss a revision committed since.

### Security

- The REST API refuses to build over `AllowAllWikiAccessChecker` (however wired) or a page repository without `WikiPageLockInterface` (audit IF-L5); archived pages need the role gate's `canArchive()` as in the UI (IF-L4); JSON writes need `Content-Type: application/json` (415, IF-I11); a slug taken by a concurrent create answers 409 `WIKI_003` (IF-I10); a save steps over an orphan revision number (IF-I14); a host listener that fails after an API write commits no longer turns it into a 500, and the audit event and the idempotent answer always follow (IF-L3).

### Tests

- **An `api` test suite** (`tests/Api`), through a host kernel with a stateless firewall, on SQLite and in CI on MySQL and PostgreSQL.
- **A `database` test suite on MySQL and PostgreSQL (spec 218, step 0).** `tests/Database` pins what `WikiPageService` writes and dispatches (create, revisions, restore, archive, the revision collision) and what search matches, on SQLite and, in CI, on MySQL 8.4 and PostgreSQL 16 (`WIKI_TEST_DATABASE_URL`). No change to `src/`.

## [1.23.0] - 2026-11-07

> **Upgrade if you use co-editing (`nowo_wiki.collaboration`).** In every earlier version, with every Milkdown, a tab that came back after the shared log was dropped (a closed laptop lid is enough) could undo newer text, including a confirmed "Replace for everyone", and nobody was asked. Every revision stays in the history, so nothing is unrecoverable, but the current text can be wrong with no warning. This release closes it with Milkdown 1.12 (audit HV-M1). **Add the `log_epoch` column before you deploy the code**: see `docs/UPGRADING.md`.

### Fixed

- **Co-editing: an update stored while a session save is in flight stays unsaved (audit HO-L1 window 2).** A session save that sends `_nowo_milkdown_collab_seq` (Milkdown 1.11 or later) records only the updates its text includes, under the log's row lock, so a later save outside the session asks instead of dropping that update. A client that sends no number, or a malformed one, counts as before.
- The numbers clients follow no longer jump at a compaction: the snapshot row takes no number of its own.
- With a client's number, two session saves at once keep the log instead of dropping it (audit HV-L2). A session save that loses the log's row still moves its base, and a committed save answers no error, also on PostgreSQL's `lock_timeout` (audit HV-L1, HV-I3).

- **Co-editing: every log has a name (audit HV-M1).** A random `log_epoch` changes with every reset. `state` answers it as `epoch`, `update` and `compact` send it as `e` and answer 409 `{"error":"log_epoch"}` under the write's lock if it is not the current log, `token` and `state` give no seed to a client on another log (header `X-Nowo-Collab-Epoch`), a `corrupt` report about another log is ignored, and hub messages `u` and `r` carry `e`. A form save with `_nowo_milkdown_collab_epoch` from another log always asks, and the 409 form keeps asking. A host's own store without `WikiCollabEpochStoreInterface` keeps today's behaviour.
- A confirmed "Replace for everyone" runs under the log's lock, so a session save from the old log cannot land after it.
- `replaced()` losing the log's row after a committed save is retried and logged, not a 500 (audit HV-L4). The base is the page's current revision read under the lock (audit HV-I4).

### Changed

- `conflict` on `nowo-tech/milkdown-editor-bundle` `<1.12.0`.

### Added

- `WikiCollabEpochStoreInterface`, optional, and `WikiCollabNumberedStoreInterface`, optional: `appendNumbered()` and `markSavedUpTo()`. `POST <url>/update` answers `200` with `{"s","p"}` with such a store, the bundled one included, and the published update carries `s` and `p`. A host's own store without it keeps `204` and the old message.

## [1.22.0] - 2026-11-07

### Fixed

- **Co-editing: a save outside the shared session no longer loses the others' text (audit HF-M1).** A page form saved with no live session (hub down, editor standalone or offline) while the shared log holds updates no version has now saves nothing and answers 409: the person's title and text stay in the form, in an editor outside the session, with a link to the live version and a "Replace for everyone" button. Before, the save dropped the log. New option `collaboration.save_conflict` (default `true`; `false` keeps the old behaviour). A restore asks the same way, on a page of its own. The check, the write and the drop run with the log locked, so an update waits rather than being lost between them. Imports, conversions, host calls, the console and `wiki:collab:purge` still replace the document without asking.
- A hub that fails while a reset is published no longer answers 500: the failure is logged and the save stands (audit HO-M1).
- The loser of two simultaneous saves (a deadlock or a duplicate revision number) gets the form back with the text and a message, not a 500 (audit HO-L4).
- `countDirectories()` counts the whole path of an entry ending in `/.` (audit GV-I17).

### Added

- `search.excluded_spaces` (default none, with `search.scope: space`): those spaces have no search page (404).

## [1.21.1] - 2026-11-07

### Security

- **Imports: limits on what is really extracted (audit GV-L3, GV-M2).** ZIP limits now count the bytes written, not the sizes an archive declares; before, an archive could write about 1000 times its size to the temp dir. Only Markdown documents are extracted. An archive naming more directories than the entry limit is refused. `import_export.max_document_bytes` (default 8 MiB) refuses a larger document, also in directory sources of `wiki:import`, front matter is capped at 64 KiB, and an archive whose Markdown does not fit in `memory_limit` is refused. A fatal error leaves nothing in the temp dir; each import also removes the bundle's own temp files older than one hour, export files included. Many documents within the limits can still exhaust `memory_limit` while they are imported, and leave the pages written so far.
- **Affected versions (GV-M2):** every published version with `WikiArchiveHelper`. Run with an entry declaring 100 B and holding 32 MiB on `v1.0.0`, `v1.4.1`, `v1.5.0`, `v1.20.0` and `v1.21.0`: all wrote the 32 MiB. Before 1.5.0 there was no ZIP size limit at all. Directory entries had the same effect (audit GV-L4). It needs the `Import` right. See `docs/UPGRADING.md` for what to do until upgrading.

### Fixed

- A Markdown file that cannot be read in an import now fails instead of importing an empty page, and a directory named `x.md` is a folder, not an empty document (audit GV-I14).
- `search.scope: space` accepts a slug that a `tenant_paths` `slug_requirement` accepts (a composite `org-<uuid>--<name>` answered 404). Whether the user may see the space is checked as before.
- `WikiQuotaCheckEvent::deny()` turns U+2028 and U+2029 into a space and keeps ZWJ and ZWNJ, so emoji sequences and Persian text stay joined (audit GV-I8).

### Documentation

- `web_ui.assets: false` does not remove what form widgets print (audit GV-I9). `nowo_wiki_web_ui`: `|slice` and the shared object in worker mode (audit GV-I10).

## [1.21.0] - 2026-11-06

Host hooks requested by Beacon (2026-11-06, WK-1 to WK-5), so it can drop its template forks and kernel listeners. Every option is off (or at the 1.20 value) by default: without them the pages, routes and responses are the same.

### Added

- **Quota event (WK-3):** `WikiEvents::QUOTA_CHECK` (`nowo_wiki.quota.check`, `WikiQuotaCheckEvent`: `action`, `space`, `user`, `count`, `bytes`, `page`, `deny(reason)`), dispatched before a metered write: `page_create` (new page, copy of a version), `page_unarchive`, `trash_restore`, `import`, `attachment_upload`. An import reports the Markdown documents of its archive as `count`. A refused upload answers 422 `{"error": "quota_exceeded", "reason": "…"}`; the page form answers 422 with the reason as an error flash; the others redirect with an error flash. No listener: no change.
- **Navigation scope (WK-4):** `web_ui.space_index: false` does not register the space index route (literal boolean); `search.scope: space` answers 404 to a search without a valid `?space=`, sets the request attribute `spaceSlug`, keeps the space as a hidden field of the search page and drops "all spaces" from the index search form. (`ai_ask` is already not registered while `ai.enabled` is false, since 1.19.1.)
- **Page slots and asset switch (WK-2):** blocks `wiki_sidebar`, `wiki_space_intro` and `wiki_page_header_extra`, each also fillable by a host template (`web_ui.slots.sidebar`, `.space_intro`, `.page_header_extra`); `web_ui.import_export: dialog` puts the exports and the import form in one *Import & export* dialog (template `manage/_import_export_dialog.html.twig`); `web_ui.assets` (`false`, or `{styles, scripts}`) leaves `nowo_ui_styles` / `nowo_ui_scripts` empty on every page, for hosts that bundle the assets themselves.
- **Comments panel extras (WK-1):** `comments.moderation_panel` (literal boolean): users with `ModerateComments` get *Moderate threads*, with *Delete thread* behind a confirm dialog, on the existing `DELETE …/comments/threads/{id}`. `comments.error_notices`: a write refused with 422 or 429 shows its own translated text (a UiKit toast when the toast script is loaded, else a notice in the panel). New `wiki-comments-moderation.ts` in `wiki.js`.
- **Twig global:** `nowo_wiki_web_ui` is a service (an `ArrayObject` with the same keys), so the options reach the templates whatever the bundle order.
- **Tree tokens (WK-5):** `--nowo-wiki-tree-gap`, `-indent`, `-guide`, `-link-padding`, `-font-size`, `-line-height`, `-radius`, `-hover-bg`, `-current-bg`, `-current-fg` in `wiki.css`; their defaults are the 1.20 values.

### Fixed

- `POST …/pages/new` and the edit form with an empty title: an error message instead of a 500.

## [1.20.0] - 2026-11-06

Optional page files list (`page_files.enabled`) and a purge event listing a purged page's files for host clean-up (`page_files.purge_event`); both off by default.

Beacon request 2026-11-06 (ME-3, WikiBundle part): the files a page refers to. Everything is off by default.

### Added

- **`page_files.enabled`** (default `false`): the page view gets a **Files** card listing the files the current revision
  refers to — Milkdown 1.8 file cards (`::file[name]{href size type}`) and images (`![alt](url)`), outside code, in
  order, once — with the name, *File* / *Image* and the size. Only relative and http(s) URLs are listed; names lose
  control and bidi-override characters. HTML revisions list nothing. Template variable `page_files`
  (`PageFile\WikiPageFile`), service `PageFile\WikiPageFileExtractor`.
- **`page_files.purge_event`** (default `false`, needs `trash.enabled`): when the trash purges pages for good, the bundle
  dispatches **`nowo_wiki.page.files_purged`** (`WikiEvents::PAGE_FILES_PURGED`, `WikiPageFilesPurgedEvent`: `space`,
  `batchId`, `pageIds`, `files`, `actor`) with the files every revision of those pages referred to, after the commit,
  so the host (which owns the storage behind its `upload_url`) deletes them. Never on *Delete*: the trash keeps files
  for a restore, and also sent when a `nowo_wiki.trash.purged` listener throws. `page_files.max_files` (default 200)
  caps the list per page and per revision; `page_files.max_purge_files` (default 5000) caps one event (`truncated`
  flag and a warning beyond it). The event's `files` are untrusted, editor-supplied URLs: the documented listener
  checks ownership by lookup before deleting.
- The extractor is best effort: cards on their own line, also in block quotes and list items, a repeated attribute
  keeps its last value (as the renderer), code and HTML comments skipped, escaped `\![…]` ignored.
- Translations `wiki.page.files`, `wiki.page.file`, `wiki.page.file_image` (7 locales).

## [1.19.1] - 2026-11-06

With the AI disabled, the AI question route is no longer registered.

### Fixed

- **The `ai_ask` route is no longer registered while the AI is off.** With `nowo_wiki.ai.enabled: false` (the
  default) the route loader still added `nowo_wiki_ai_ask` (`/tools/wiki/ask`), which rendered an "AI unavailable"
  page after the access and space checks. The extension now drops `routes.ai_ask` when the AI is disabled, like the
  tree, history, trash and comment routes, so the loader registers no AI route; `askAi()` also answers 404 before any
  work when it has no `ai_ask` route (a host routing to it by hand). The index already rendered no Ask AI action nor
  dialog without an available assistant; with no route it renders none either. Hosts that link to
  `nowo_wiki_ai_ask` with the AI off must drop the link.

## [1.19.0] - 2026-11-06

Minor release: dialog rule v2 (owner decision D-20261105-54, Symfony Beacon DIALOG-INVENTORY
"waiting on kit": Wiki — trash purge, page view / history dialogs). Requires `nowo-tech/ui-kit-bundle` **^2.31**. See
[UPGRADING](UPGRADING.md#to-1190).

### Changed

- **Confirm dialogs on the dialog anatomy, from UiKit.** The wiki's confirmations — delete (move to the trash),
  archive, restore a revision (history), co-editing reset, purge (trash) — stay UiKit `View\Confirm` dialogs; UiKit
  2.31 draws every `View\Confirm` with the platform dialog anatomy (tinted header with the title and ×, body with the
  message, tinted footer outside the body with Cancel then the one primary — solid danger for delete / archive /
  reset / purge, primary for restore), medium width, height of the content capped. No bundle template or config:
  the markup, ids, CSRF forms and `nowo-ui-confirm.js` runtime are the kit's (a host's `confirm` partial or Twig
  override applies to the wiki as to every bundle).
- **AI dialog:** no markup change (header × / body / footer Cancel + Ask); its height is UiKit 2.31's (the content's,
  capped at `--nowo-dialog-height-md`); the template comment no longer promises the fixed height.

### Dependencies

- `nowo-tech/ui-kit-bundle` `^2.31` (was `^2.29`); `composer.lock` of the bundle and of `demo/symfony8` updated to
  UiKit 2.31.0 (the demo also picks up FormKit 2.18.0 with it).

## [1.18.0] - 2026-11-05

Minor release. See [UPGRADING](UPGRADING.md#to-1180).

### Changed

- **Dialog sizes (UiKit 2.29, platform rule).** The AI question dialog uses the UiKit `dialog` theme partial at the
  medium size (`--nowo-dialog-width-md` × `--nowo-dialog-height-md`) instead of the old `lg` width; the body is the only
  scroller and the footer (Cancel, then the one primary *Ask*, bound with `form="…"`) stays outside it. The confirm
  dialogs (delete, archive, restore, co-editing reset, purge) are UiKit `View\Confirm` dialogs and follow the sizes as
  they are. Requires `nowo-tech/ui-kit-bundle` `^2.29`.

### Added

- **Page tab icons (Beacon spec 177).** `WikiPageFactory::pageTabs()` sets an icon per tab (Page `tabler:file-text`,
  Edit `tabler:pencil`, History `tabler:history`; `web_ui.tab_icons.<tab>`, null or `''` = none) and
  `manage/_page_tabs.html.twig` passes it to the UiKit `tabs` partial, so the row follows the host's tab style.
  Hosts that overrode `_page_tabs.html.twig` only to add the icons (Symfony Beacon) can remove that override.
- **Tab icon names are validated at container build.** `web_ui.tab_icons.<tab>` must be `[prefix:]name` with
  lowercase letters, digits and single hyphens (the `symfony/ux-icons` name grammar, one prefix at most), or null / `''`;
  anything else (spaces, uppercase, `..`, `/`, quotes) fails `cache:clear` with a clear message instead of a 500 at
  render time with the `ux_icon` set.

### Dependencies

- `nowo-tech/ui-kit-bundle` `^2.29` (was `^2.26`); `composer.lock` of the bundle and of `demo/symfony8` updated to
  UiKit 2.29.0.

## [1.17.0] - 2026-11-05

Minor release. See [UPGRADING](UPGRADING.md#to-1170).

### Added

- **Focusable code blocks (KIT-71).** `wiki.js` gives every rendered `<pre>` that overflows horizontally
  `tabindex="0"`, `role="group"` and an `aria-label` (`wiki.code_block`, "Code block", in the 7 locales, passed as
  the script's `data-code-label`), so keyboard users can scroll it (axe `scrollable-region-focusable`). Blocks
  rendered after load (comments panel, page preview, read-only editor) are picked up by a `MutationObserver`; a
  resize re-checks them; blocks that stop overflowing lose what was added; the markup's own `tabindex` /
  `aria-label` / `role` and editable regions are never touched. Hosts can drop their own scroll-region script for
  wiki code blocks.
- **Erasure of a person's mentions (DATA-WIKI-COMMENT-MENTION-ERASE).** `WikiCommentService::eraseMentions(handles,
  label = '@…')` rewrites every `@handle` of the erased person in other people's comments (bodies, quoted Markdown)
  and inline thread quotes (stored quote and anchor) to the label, without marking comments as edited; case-insensitive,
  Markdown-escaped `\_` accepted, never a longer handle or an e-mail address. New
  `WikiCommentMentionRepositoryInterface` (implemented by the Doctrine store; the call throws `LogicException` for a
  store without it), `WikiComment::redactBody()`, `WikiCommentThread::redactQuote()` and the
  `nowo_wiki.comment.mentions_erased` event (`WikiCommentMentionsErasedEvent`). Same contract as ChatKit's
  DATA-CHAT-MENTION-ERASE. Only comments are covered: mentions in page content (revisions, search text) stay.
- **Erased-mention label check (audit FG-L1).** `eraseMentions()` throws `InvalidArgumentException` for a label that
  could render as a mention (`@` followed by a handle character, or a handle character first), such as
  `@deleted-user`, which would link every erased mention to whoever owns that handle. USAGE now uses `[deleted user]`.
- **`wiki:comments:erase-user` erases mentions (audit FG-L2).** New repeatable `--handle` option (the user's
  `getHandle()` is added when the user class has one) and `--mention-label`: the user's mentions are erased through
  `eraseMentions()` before their own comments; an invalid handle or label stops the command before anything changes.
  Without any handle the command warns that mentions were kept.
- **Bounded mention erasure (audit FG-L3).** The candidate lookup matches the whole handle (each `_` as `_` or the
  escaped `\_`; handles with more than 3 underscores require every part) instead of the part before the first `_`
  (just `@` for a handle starting with `_`), and is paged by id: `eraseMentions()` walks batches of
  `WikiCommentService::ERASE_BATCH` (200), flushing and releasing each, inside one transaction (all or nothing).
  `WikiCommentMentionRepositoryInterface` is `commentsMentioning()` / `threadsMentioning()` (paged, returning a
  `WikiCommentMentionBatch`), `begin()` / `commit()` / `rollBack()` and `release()`.
- **Mention erasure keeps the caller's entities (audit FG2-L1).** Only the comments and threads a batch lookup loaded
  itself are released; instances the caller already managed (for example comments kept by `eraseAuthor()` on the
  same entity manager) stay managed with their pending changes, so later flushes no longer fail with "A new entity
  was found through the relationship WikiComment#thread". USAGE now calls `eraseMentions()` before `eraseAuthor()`.
- **Unscannable rows are never blanked (audit FG2-I1).** When the mention regex fails on a row (invalid UTF-8), the
  row is left untouched instead of being overwritten with an empty text. `eraseMentions()` returns a
  `WikiCommentMentionErasure` (countable; `commentIds`, `threadIds`, `skippedCommentIds`, `skippedThreadIds`,
  `skipped()`), the event carries the skipped ids, and `wiki:comments:erase-user` lists them and fails.
- **A failed commit is not hidden (audit FG2-I6).** If the erasure's commit fails, the rollback's own error is ignored
  and the original exception is rethrown.
- **The mention walk never stops early (audit FG3-L1).** Paging follows the candidate id page
  (`WikiCommentMentionBatch::$scanned` / `$lastId`), not the loaded rows, so a candidate hard-deleted between the id
  query and the load no longer ends the walk and leaves later candidates untouched.
- **Anchors are never blanked by a JSON encode failure (audit FG3-I3).** `eraseMentions()` refuses a label that is not
  valid UTF-8, and an anchor that cannot be re-encoded skips the thread (reported) instead of being written empty.
- USAGE documents the online erasure (no row locks), the re-runnable CLI, the paging contract for host stores and the
  event firing before an outer transaction's commit (audit FG2-I2…I5), and clearing the entity manager after a failed
  call (audit FG3-I1, FG3-I2).

## [1.16.2] - 2026-11-04

### Fixed

- **Milkdown read view** (`editor.type: milkdown`; reported by Symfony Beacon with Milkdown 1.6.0):
  - **Footnotes** (`[^1]`) render instead of showing as literal text: CommonMark's `FootnoteExtension` is enabled
    (unless `nowo_milkdown_editor.markdown.footnotes: false`; `max_footnote_refs` and case-insensitive labels as in
    the Milkdown renderer, inline `^[…]` footnotes stay text). Ids never carry the user's label: they are
    `nowo-fn-<salt>-<n>` / `nowo-fn-ref-<salt>-<n>[__<k>]` (`<salt>`: 8 hex of a hash of the Markdown, so a page and
    its comments never share an id), the back-links are kept and the footnote content goes through the same
    allowlist sanitizer.
  - **Heading ids**: headings get `id="nowo-h-<slug>"` with the editor's slugs (Milkdown `HeadingIds::slug()`:
    lower-case, runs of non letters/marks/digits become `-`, 80 characters, `section` when empty, `-2`, `-3`… for
    duplicates), so anchors and in-page links written in the editor work in the read view. `WikiHeadingIds` keeps the
    next suffix per slug (linear; Milkdown's `HeadingIds::slugs()` retries from `-2` for every duplicate, so 9 999
    identical headings took ~4 s per view, audit EP-L1) and gives ids to the first 1 000 headings of a document only.
  - **Footnotes with a non-ASCII label** (`[^Ä]: Note`) keep their whole text: league/commonmark's definition parser
    advanced by bytes instead of characters and ate the first characters of the note (`ote`); `WikiFootnoteStartParser`
    replaces it (audit EP-I6).
  - **Table column alignment** (`:---:`) is kept as the class `nowo-wiki-align--left|center|right` (styled by
    `wiki.css`); no `align` attribute and no inline style (CSP-safe).
  - **Leaf directives without a renderer** (`::beacon{…}`, any `::name{…}`) fall back to readable text (the label, or
    `name · key: value…`), or to a link when a `url` / `href` / `link` attribute holds a safe URL (same-origin `/path`
    or `https://` without userinfo: the Milkdown link policy and the UI kit URL policy must both accept it). Every
    value is escaped; host directive renderers still win for the viewer. A same-origin path with `//`, a `.` / `..`
    segment or an encoded slash (`/.//host`, `/%2F%2Fhost`) is not linked (audit EP-I2).
- **Sanitizer** (`WikiHtmlSanitizer`): `id` is kept only as one of those anchors — `nowo-h-<slug>` on `h1`…`h6`
  (letters, marks, digits and single hyphens, at most 90 characters), `nowo-fn-<8 hex>-<n>` on `li`,
  `nowo-fn-ref-<8 hex>-<n>[__<k>]` on `sup` — and dropped everywhere else, even when a host allowed `id` through
  `sanitizer.extra_allowed_attributes` (DOM clobbering: `__proto__`, `location`, host ids); listing `id` there now
  logs a deprecation at container build (audit EP-I4). `name` joins `FORBIDDEN_ATTRIBUTES`: a host can no longer allow
  it (it clobbers `document.<name>`, audit EP-I3). `align` and general `style` stay refused.
- The PHPUnit configuration sets `memory_limit` to 512M (the full suite outgrew the default 128M, audit EP-I9).

## [1.16.1] - 2026-11-04

The final Info items of the 1.16.0 security audit (appendix EI).

### Added

- **Moderator thread delete** `DELETE …/comments/threads/{id}` (`WikiAction::ModerateComments`, 204): a whole thread
  with its comments and reactions, event `nowo_wiki.comment.thread_deleted` (EI-I16).
- `comments.limits.user_threads_per_page` (default 50): open threads one user may start on one page (EI-I16).
- `comments.limits.total_threads_per_page` (default 1000): every thread of a page, resolved ones included (EI2-L1).
- `WikiCommentAuthorRepositoryInterface` (per-author queries of the comment store), implemented by the Doctrine store
  (EI2-I5).
- `WikiCommentService::visibleQuote()`: the quote a notification may show, blank once the text left the page
  (EI-I15).
- `WikiCommentService::exportAuthorData()`: the user's comments, reactions and the threads they started or resolved;
  `wiki:comments:erase-user --export` prints it. Event `nowo_wiki.comment.author_erased`
  (`WikiCommentAuthorErasedEvent`) after an erasure (EI-I17).

### Changed

- `comments.limits.threads_per_page` counts **open** threads only: resolved threads no longer lock a page (EI-I16).
- The server-rendered comments list shows the opening comment and the latest 19 of each thread, with "N earlier
  comment(s) in the editor" (EI-I18, EI2-I6).
- Open threads are listed first: resolved threads can no longer push open ones out of the 2000-thread window of
  `GET /threads` and the server list, and `truncated` is set only when threads were left out (EI2-L1).
- Reopening a thread checks the open-thread caps, like starting one (EI2-I1).
- The `wiki:comments:erase-user` confirmation names the resolved account and the number of comments (EI2-I4).
- `wiki:comments:erase-user` asks for confirmation unless `--force` or `--no-interaction` (EI-I17).

### Fixed

- **iOS Safari:** the sidebar page tree (`.nowo-wiki-sidebar__body`) uses `max-height: calc(100dvh - 8rem)`, with
  `100vh` kept as the fallback, so it no longer runs under the browser bars.

## [1.16.0] - 2026-11-04

Comments panel, avatars, plain-text search.

### Added

- **Page tabs (B-Q1).** The read and edit views get a tab row under the title: *Page* / *Edit* / *History* (UiKit
  `PageHeader::tabs`, an `AreaNav` rendered by the UiKit `tabs` partial at the secondary level; the current view has
  `aria-current="page"`; Edit and History only with those rights). It is always there, comments or not.
- **Comments (opt-in, `nowo_wiki.comments.enabled`).** Inline threads anchored in the text (Milkdown 1.6 anchor v1,
  validated with `CommentAnchor`) and page-level comments, in a right-side panel toggled from the end of the tab row:
  - the toggle is the UiKit side-panel toggle as an icon button (`tabler:message-circle`) with a visible count badge
    (open threads the viewer may read) that is also part of its accessible name; optional `comments.shortcut`
    (`aria-keyshortcuts`), none by default;
  - the panel is the UiKit 2.25 side panel (an inline column >= 768 px, a full-height drawer <= 767 px with the page
    behind inert), named by its heading, Escape / close / toggle, focus in on open and back on close, polite live
    region; the Milkdown thread component mounts into it (`panel: 'container'`), with two-way highlight / thread
    focus and the `comments-count` badge;
  - editors get the page as a read-only Milkdown mount on the read view (`nowo_milkdown_view()`) and the thread
    component in the edit form; **other commenters never receive the Markdown source**: their panel lists the open
    threads rendered by the server (inline threads with their quote and a muted *Reply from the editor* line, a link
    to the editor for viewers who may edit) and the page-comment form.
    `comments.read_view_source: true` (opt-in) gives them the mount and the source. Without JavaScript the toggle is
    a link to `?comments=1`, the panel lists the page-level threads and a `WikiCommentType` form posts to
    `…/comments/new`;
  - open / closed state per user: `WikiPanelStateStoreInterface` (the session by default, `comments.state_store` for a
    host store), `POST …/spaces/{spaceSlug}/panel-state`; `?comments=1|0` overrides it for one request;
  - endpoints under the route key `page_comments` (`…/pages/{pageSlug}/comments/…`, the Milkdown 6.1 contract v1.2:
    `updatedAt` / `base` and 409, `resolvedBy` / `resolvedAt`, deleted comments, `truncated`), JSON, same origin,
    `X-CSRF-Token` per page, bodies <= 16 KiB, Markdown <= 10 000 characters, `comments.rate_limiter` (required, per
    user across pages) and optional `comments.page_rate_limiter`, caps in `comments.limits` (422 `limit_reached`);
  - viewers who may not edit get only the quote of an anchor, blanked once the text left the page;
    `renderBodyForNotification()` (viewer-neutral) for notification bodies; privacy export / erasure
    (`exportAuthor()`, `eraseAuthor()`, `wiki:comments:erase-user`); the trash purge deletes comment rows explicitly;
    every response with the panel is `Cache-Control: private, no-store`;
  - rights `WikiAction::ViewComments`, `Comment`, `ResolveComment`, `ModerateComments`: outside `grants()`, denied by
    the 1.x legacy mapping (default deny); authors edit and delete their own comments with `Comment`;
  - tables `<prefix>_comment_threads`, `<prefix>_comments`, `<prefix>_comment_reactions` (left out of the schema when
    off; threads follow their page to the trash and are deleted with it);
  - events `nowo_wiki.comment.created` / `.replied` / `.edited` / `.deleted` / `.resolved` / `.reopened`
    (`WikiCommentEvent`: page, thread, comment, actor, mentions; never a body);
  - with co-editing, live updates by thread id on the private topic `<collab topic>/comments`, granted with the
    co-editing topic in one subscriber cookie (`WikiCollabTopicsAuthorizerInterface`).
- **Avatars** (avatar contract v1.1 §10.2): `Nowo\WikiBundle\Avatar\WikiUserAvatarResolverInterface` (keyed by
  `WikiAvatarKey::of()`, size 32; `NullWikiUserAvatarResolver` by alias), batched once per response. History authors
  and comment authors (`author.avatarUrl`, `resolvedBy.avatarUrl`) get the picture through the UiKit `_avatar`
  partial and URL policy, **only beside a real name**: with `history.show_names: false` or a name that fails
  `isSafeName()` nothing is resolved nor emitted. `WikiAuthorLabel::present()` / `presentAll()`.
- **Plain-text search.** `content_text` column on the revision (public API), filled on save from the sanitized
  HTML; `Nowo\WikiBundle\Search\WikiPlainText::fromHtml()` / `fromMarkdown()`; `wiki:search:backfill-text`
  (`--all`, `--batch-size`, `--dry-run`).

### Changed

- **Search matches the plain text** of the current revision instead of its Markdown source (or HTML): directive
  markers and attribute values (`:::callout{icon="bulb" type="info"}`), Markdown punctuation and tags are no longer
  hits. Revisions not backfilled yet are still matched the 1.15 way. Excerpts come from the same text.
- Requires `nowo-tech/ui-kit-bundle` `^2.26` (side panel, avatar partial and URL policy); dev requirement
  `nowo-tech/milkdown-editor-bundle` `^1.6`.
- `WikiAuthorLabel` is always registered (comments use it too) and takes the avatar memo.
- The route loader passes `#[Route]` defaults through (the optional `{path}` of `page_comments`).

## [1.15.1] - 2026-11-04

Empty space export, export index, front matter round trip

### Fixed

- **Exporting a space with no visible pages no longer returns 500** (reported by symfony-beacon). ZipArchive writes no
  file for an archive without entries, so the web export (`nowo_wiki_space_export`) ended in
  `FileNotFoundException: ".../wiki-export-<slug>-<hex>.zip" does not exist`, and `wiki:export --zip` reported success
  without writing the archive. Every export now holds a `README.md` index at its root (Outline-style): the space
  title, the export date (UTC) and the exported pages as nested relative links, or a line saying the space has no
  pages visible to the exporter. It lists exactly the exported pages (never the title of a page the exporter may not
  read), escapes titles for Markdown link text and is translated in the `NowoWikiBundle` domain (`wiki.export.readme.*`,
  all seven locales).
- `WikiArchiveHelper::createZipFromDirectory()` throws a `RuntimeException` when the archive was not written instead of
  returning a path that does not exist.
- `wiki:export` removes its temporary directory on every path and reports a failed export (`Export failed: ...`,
  exit code 1) instead of throwing.
- Imports skip the `README.md` index of a wiki export (first line `<!-- nowo-wiki:export-index -->`), so re-importing
  an export does not create a README page (nor, for Notion, nest every top-level page under it). Only `README.md` at
  the export root is the index; the marker anywhere else is page content. The import report counts the index as
  skipped (`README.md: wiki export index, skipped.`).
- The index name is reserved (audit DV-L1 / DV-I4): an Outline page whose slug is `readme` (`readme.md`, the same file
  as `README.md` on case-insensitive filesystems) and a top-level Notion page titled `README.md` (its folder) get the
  first free `-N` suffix (`readme-1.md`, `README.md-1/`); links and the index follow, and the front matter keeps the
  slug. The index never replaces a `README.md` that is not an earlier index (the export fails before writing any
  page), and a failed index write raises a path-free `RuntimeException`.
- Index titles stay on one line even with invalid UTF-8 (audit DV-I3).
- **Exported front matter parses back** (audit DV-I8, since the first export): `WikiFrontMatterParser::serialize()` wrote
  the closing `---` right after the last value (`wiki_parent: ''---`), so `parse()` found no front matter. Re-importing
  an Outline export lost every title, slug and parent and imported the YAML as page text. The delimiter is on its own
  line again.

## [1.15.0] - 2026-11-04

Version history timeline, named versions, copy to new page, retention; Copy as Markdown

### Added

- **"Copy as Markdown" on the page view** (`editor.milkdown.copy_markdown`, opt-in, Milkdown 1.5): a button that copies
  the page's stored Markdown. It is shown only to users who may edit the page, because the raw source holds page ids,
  mention handles and directive attributes that the per-viewer render hides (Milkdown audit DN-I2). That response is
  `Cache-Control: private, no-store` (audit DR-I1).
- **History timeline** (`history.timeline`, opt-in, 1.15): the version history gains the columns *Version name*,
  *Author* and *Saves in session*. A session is consecutive saves by the same author less than
  `history.session_minutes` apart (default 30); a named version is a session of its own. Authors show as the same
  opaque, per-page "Editor xxxx" as co-editing, or as their display name with `history.show_names` (only names that
  say nothing about the login).
- **Named versions** (D-W7: every editor): *Save version* on the page view names the current revision (one name per
  revision, 120 characters; saving again renames it). Stored in `<prefix>_revision_labels`, which is left out of the
  schema when the timeline is off.
- **Copy to new page**: a row action of the history creates a top-level page with the content of that revision
  (title "<title> (vN)", a free slug, both cut to the column limits; it takes the space's top-level visibility by
  design), for users who may create pages (`canCreate()` and `WikiAction::Create` on the
  space) and see the history.
- **Revision retention** (D-W6): `wiki:history:thin [--dry-run]` thins revisions older than `history.retention_days`
  (default 30) to the newest one per session and day. Named versions, the current revision and the newest revision
  are never removed; revision numbers are never reused.

## [1.14.1] - 2026-11-03

Collaboration cost: counters on the document row, split limiters

### Fixed

- **Co-editing updates are cheap per keystroke** (Beacon audit, High). The update endpoint used to read the size of the
  page's whole log twice per request (`SUM(LENGTH(data))` over the LONGTEXT rows) around the insert. Now:
  - The document row keeps `update_count`, `log_bytes`, `last_seq` and `snapshot_seq`, written in the same transaction
    as each insert, compaction and reset.
  - The caps and the append are one locked write (`WikiCollabCountingStoreInterface::appendWithin()`: three
    statements).
  - The Mercure relay goes out after the commit.
  - A host store without the new interface keeps working as before.
  - A log reset runs in one transaction that locks the document row first, in the same order as an append or a
    compaction (audit DQ-L1): an append can no longer land between the delete and the counter reset, and the two
    cannot deadlock.
- **Separate budgets for updates and presence.** `collaboration.update_rate_limiter` and
  `collaboration.awareness_rate_limiter` (e.g. `limiter.wiki_collab_update` / `limiter.wiki_collab_awareness`).
  Without them, `rate_limiter` keeps limiting every request.

## [1.14.0] - 2026-11-03

Outline-style page tree and trash

### Added

- **Outline-style page tree** (`tree.enabled`, opt-in, T062 / T049): the sidebar renders the UiKit 2.24 `tree`
  (WAI-ARIA treeview: one tab stop, arrows, Home / End, type-ahead, `*`) with disclosures, the current page's branch
  unfolded and the fold state of each user and space applied by the server (no flash; kept in the session unless
  `tree.state_store` names your service). Users who may edit move pages by dragging (drop line, *inside* / *between*),
  with **Alt+↑ / ↓ / ← / →** (reorder, nest under the previous page, move out) or with the **Move** form of a page
  (parent + position; the single-pointer and no-JavaScript alternative). Moves are announced in a live region.
- **Moves on the server** (`WikiPageMover`): same space, no cycles, `tree.max_depth` (default 10), edit rights on the
  page, its old and its new parent; one transaction that locks the space; sparse sibling positions (one row written
  per move; siblings renumbered once when they share a position). `nowo_wiki.page.moved` (`WikiPageMovedEvent`:
  page, from, to, index, actor, time) is the audit hook. Endpoints: `POST …/tree/move` and `POST …/tree/state` (JSON,
  same origin, CSRF token per space, 4 KB bodies), `GET|POST …/pages/{pageSlug}/move`. Without the tree none of them
  is registered and every page is byte-identical to 1.13.
- **Audit DI (before the first release of the tree):** the tree endpoints and the Move form need `View` on the page
  and on every ancestor, like the sidebar (DI-L1); the index of a move and the position it answers count the siblings
  the user can see (DI-I1); a page moved by someone else between the check and the lock is refused as `stale`
  (DI-I2, compared with the very parent whose `Edit` was checked, DI-I6); a page moves with its whole sub-tree, descendants the actor cannot see included, whose readers become those
  of the new branch (DI-I3, documented).
- **Trash** (`trash.enabled`, opt-in, T065):
  - *Delete* on a page moves it to the space's trash **with its whole sub-tree**, behind a confirm dialog. The pages
    are archived, so the tree, search, page links, mentions, backlinks, the export and the AI treat them as gone.
  - The trash lists each deletion with *Restore*, which brings the page back with its sub-pages (pages archived
    before stay archived; a page whose parent is gone meanwhile goes to the top level).
  - *Delete for good* opens a confirmation page, then asks a second confirm. It removes the pages with their
    revisions, host-object links and attachments (files included).
  - `wiki:trash:purge` purges deletions older than `trash.retention_days` (default 30); `--dry-run` counts them all.
  - Restore and purge run under the space lock and read the deletion again, so a purge never deletes pages a restore
    brought back; attachment files are unlinked after the commit (audit DO-M1, DO-L1). A deletion holding pages the
    purger cannot see needs `WikiAction::Manage` to be purged (DO-I1).
  - Rights: `WikiAction::Delete` and `WikiAction::Purge`, asked from your space checker. The 1.x mapping is the
    archive roles to delete and the import / manage roles to purge.
  - Events: `nowo_wiki.page.trashed`, `nowo_wiki.trash.restored` and `nowo_wiki.trash.purged`.
  - Without the trash nothing changes: no routes, no table (`<prefix>_page_trash` is left out of the schema) and no
    action.
- Requires `nowo-tech/ui-kit-bundle` ^2.24 (tree component).

### Changed

- **`ext-iconv` is required** (audit DA-I8): `show_names` transliterates names with iconv and, without it, falls back
  to the opaque label (fail closed).
- **A compaction of a purged log is refused with 409 `log_reset`** (audit DA-I9), like an append: the store no longer
  recreates the document.

## [1.13.2] - 2026-11-03

Pages under a hidden parent stay hidden on every read path

### Security

- **A page under a hidden parent is hidden too, on every read path** (hardening; found in the 1.14 tree audit,
  DI-L1). Until now the page view, history, diff, edit, archive / unarchive / restore, attachments (upload and
  download), co-editing, search and the AI answers checked `View` on the page only, while the sidebar tree, the export
  and the page-link / mention resolver already required `View` on every ancestor. One rule now
  (`WikiPageVisibility::isReadable()`, `WikiSpaceGuard::isPageReadable()` / `requireReadablePage()`): `View` on the
  page and on every ancestor; otherwise 404, like a missing page, and the hit is left out. A parent cycle in the data
  reads as hidden. Hosts whose checker grants `View` on a page but not on its parent will see that page disappear for
  those users, as it already did from their tree.
- **Import respects the reader rule** (audit DJ-L1): pages the importing user may not read are never matched, updated
  (`update_existing`) or used as a `wiki_parent`; a document asking for such a slug or parent fails with a neutral
  "not available" message (no "skipped existing" answer, no duplicate slug). A `wiki_parent` that does not exist fails
  the same way in the web import, so hidden and missing parents read alike. The CLI import is unchanged.
- The ancestor walk is shared across the hits of one search or AI answer (DJ-I1). SECURITY.md says that host
  endpoints for link titles, `@` candidates and the page picker must use `isPageReadable()` (DJ-I2).

## [1.13.1] - 2026-11-03

Tiptap assets only for tiptap, no collab tables when collaboration is off, safe co-editor names, purge/append race, audit residuals

Patch for hosts adopting 1.13 (Beacon requests) and the audit Info residuals CW-I7, CW-I9, CW-I10, CW-I12, CV-I9. See
[UPGRADING](UPGRADING.md#to-1131).

### Fixed

- **No schema diff without co-editing:** the collaboration tables are left out of the generated schema
  (`postGenerateSchema`) while `collaboration.enabled` is false.
- **Edit page loads the Tiptap script only for `editor.type: tiptap`** (Milkdown and Markdown pages no longer load it).
- **`wiki:collab:purge`** refuses `--older-than 0`, selects stale logs under a row lock inside a transaction and tells
  the live editors of purged pages to reload (CW-I10; `WikiCollabPagePurgeInterface`, `WikiCollabService::purge()`).
- **Capped manual reset** shows a flash message instead of failing silently (CW-I7; `wiki.collab.reset_capped`,
  `wiki.collab.rate_limited` in 7 locales).
- **Membership memo** in a `WeakMap` per user object (checked against the identifier), bounded to 64 users (CV-I9).
- **`collaboration.enabled` must be a literal boolean** (DA-M1): an `%env()%` placeholder registered the
  collaboration services while the schema listener left the tables out, so a diff would have dropped live tables.
- **Appends lock the page document** before storing an update; an append to a log purged meanwhile is refused with
  409 `log_reset` (no purged session is revived), document rows use the platform's insert-or-ignore form (no aborted
  PostgreSQL transaction), and purges also drop updates left without a document (DA-L1).
- **"Editor xxxx" is an HMAC** of the page id and the login under a subkey of `kernel.secret` (an empty secret is
  refused): stable per page, not linkable across pages nor reversible against a directory (DA-L2, DA-I5).
- **`show_names` filter hardened** (DA-L3): only Latin-script names without format / bidi controls or `@` are shown;
  the login and the e-mail local part are compared after case and accent folding, as words and as substrings;
  fullwidth, mathematical, circled and small-cap letters and other scripts fall back to the opaque label.
- The per-user limiter key is prefixed (`nowo_wiki_collab:`) and the schema listener only acts on the wiki's entity
  manager.

### Changed

- **Co-editor names are opaque by default** ("Editor xxxx"); `collaboration.show_names: true` shows display names,
  never one equal to the login identifier or shaped like an e-mail address (CW-I2).

### Added

- `collaboration.user_rate_limiter`: optional budget per user across all pages (CW-I9).
- Documentation: never put `mercure` keys in the hub's default JWT claims (CW-I12).

### Documentation

- `docs/ROADMAP.md`: knowledge-base roadmap towards Notion / Outline parity (page tree, trash, version history,
  comments, templates, search, per-page permissions, public links, Notion-style databases), parity matrix, open
  decisions.

## [1.13.0] - 2026-11-03

Milkdown editor bridge (renderer, link index, exports, HTML→Markdown conversion with report) and Mercure co-editing (off by default)

Wiki: Milkdown editor (`editor.type: milkdown`) and co-editing (`collaboration`, Mercure) — the Milkdown ↔ wiki bridge renders the nowo-tech/milkdown-editor-bundle dialect (page links, mentions, Tabler icons, directives, math, Mermaid) in every wiki surface under Milkdown's limits; `wiki:convert-markdown` gains page scope and a fidelity report. Schema change only when co-editing is used (two tables); Tiptap stays the default. See [UPGRADING](UPGRADING.md#unreleased).

### Added

- **`nowo_wiki.editor.type: milkdown`** (requires `nowo-tech/milkdown-editor-bundle` ^1.2 (conflict `<1.2.0`) with `NowoMilkdownEditorBundle` registered; the container refuses to compile otherwise): Markdown storage as with `markdown`, `MilkdownEditorType` as the page body unless `editor.markdown_form_type` says otherwise, and `editor.milkdown.page_resolver` (service id; default `WikiMilkdownPageResolver`).
- **`Markdown\Milkdown\MilkdownWikiMarkdownRenderer`** (alias of `WikiMarkdownRendererInterface` with `milkdown`): CommonMark + GFM + `MilkdownDialectExtension` + every `nowo_wiki.markdown_extension`, with the `nowo_milkdown_editor.markdown` options (`html_input`, `allow_remote_images`, `math`, `max_nesting_level`, `max_input_length`, `max_inline_markers`, `max_references`, `mark_colors`) handed over by `MilkdownBridgePass`. Two environments: a viewer-neutral one for the `content_html` cache, search excerpts and AI context (no page, user or host-block resolution) and a viewer one for the page view. Unsafe links are always dropped; input over the limits renders as escaped text.
- `Markdown\WikiViewerMarkdownRendererInterface` (`renderForViewer()`), `Markdown\WikiMarkdownReferenceParserInterface` (`references()`: page links and mentions from the renderer's parser), `Markdown\WikiMarkdownLimitsInterface` (`exceedsLimits()`).
- `Markdown\WikiRevisionBodyRenderer`: the page view renders Markdown revisions for the viewer (then sanitized and filtered for attachment URLs of other spaces) when the renderer is viewer-aware; the stored HTML otherwise.
- `Markdown\Milkdown\WikiMilkdownPageResolver` (pages the viewer may read: not archived, list gate, `View` on the space, the page and its ancestors; one query through `WikiPageBatchFinderInterface`), `WikiTablerIconRenderer` + `WikiMilkdownIconHydrator` (allowlisted icons drawn from the sprite after sanitizing; the sanitizer never allows SVG), `WikiMilkdownRichContent` and the Twig function `nowo_wiki_rich_content_assets()`; `wiki.js` draws Mermaid and math in `[data-wiki-rich-content]` with the Milkdown read-side renderer and the script's CSP nonce.
- `Repository\WikiPageBatchFinderInterface` (`findByIds()`), implemented by `DoctrineOrmWikiPageRepository`.
- `WikiMarkdownReferenceExtractor::mentions()`; with `milkdown` every method reads the renderer's parser.
- `wiki:convert-markdown`: `--page` (repeatable), `--report=<file>` (JSON fidelity report, format `nowo-wiki-conversion-report` v1), `--skip-lossy`, `--fail-on-loss` (every page is analysed first; nothing is written when a page is lossy), a console fidelity summary, and pages edited during the run are skipped (`skipped_changed`). `Interchange\Conversion\WikiConversionFidelityAnalyzer` / `WikiConversionFidelity`.
- `WikiMarkdownConverter(dialect: true)` (registered so with `milkdown`): `[[wiki:…]]` and `@handle` text kept as tokens, `<mark>` → `:mark[…]`, centred / right-aligned paragraphs and headings → `::: {align=…}`, embeds → links.
- Exports with `milkdown`: page links to exported pages become relative Markdown links to the exported files (`WikiMarkdownPortableLinks`).

- **Co-editing (FR-076):** `nowo_wiki.collaboration` (off by default; needs `milkdown` and symfony/mercure-bundle ^0.6.1, or your own publisher / subscriber services).
  - `WikiCollabController` (`routes.page_collab`): `token`, `state` (read only), `update`, `awareness`, `compact`, `corrupt` and the editor's `reset` form. Every request checks a trusted user, `canEdit()` and `View` + `Edit` on space and page, and goes through the **required** `collaboration.rate_limiter` (the container refuses to compile without it). POSTs must be same-origin JSON; bodies are size-limited.
  - `Collaboration\WikiCollabService`, `WikiCollabStateStoreInterface` with the default `DoctrineWikiCollabStateStore` (tables `<prefix>_page_collab_documents` / `<prefix>_page_collab_updates`): a Yjs log the server never decodes, compacted by clients (one snapshot, compactions serialised per page), bounded by `max_log_entries` and `max_log_bytes` (checked by counters before and after each append).
  - `WikiCollabPublisherInterface` / `WikiCollabSubscriberAuthorizerInterface`, defaults on symfony/mercure ^0.8 / ^0.9: private updates and a subscriber grant for the page topic only.
  - Seeding: one tab seeds an empty log (claimed by `POST token`, per user and `X-Nowo-Collab-Client`).
  - **Reset only when needed:** saves from the live session (form field `_nowo_milkdown_collab`, current base) keep the log; saves outside it or with a stale base, restores, imports, conversions, `corrupt` reports and the editor's *Reset collaboration* button (CSRF + confirmation) drop it and publish a reset — client / editor resets are capped per page (`corrupt_resets` per `corrupt_window`). Co-editors see a display name or an opaque label, never the login.
  - `bin/console wiki:collab:purge --older-than=<days>` deletes idle logs.
  - The page edit form passes MilkdownEditorType (^1.2) the `collaboration` option.

### Changed

- `LegacyWikiSpaceAccessChecker` (the default space checker) reads a user's spaces once per request (memo keyed by user identifier, cleared on `kernel.reset`): a page with many links costs one `listSpacesForUser()` instead of one per link (audit CV-L1).
- With `milkdown` the sanitizer also keeps `details` (`open`) / `summary` and `role`, `aria-label`, `data-math`, `data-directive`, `data-icon` (`WikiExtension::MILKDOWN_SANITIZER_ELEMENTS` / `MILKDOWN_SANITIZER_ATTRIBUTES`).
- `nowo_wiki_safe_html` draws dialect icons after sanitizing when `milkdown` is on; the page view passes `rich_content` and marks its body `nowo-md` / `data-wiki-rich-content`.

## [1.12.0] - 2026-10-31

Host polish found while integrating the wiki in a Tailwind host (symfony-beacon): the original-templates Twig namespace, a search form that fits `tenant_only`, catalogue flashes, spaced search excerpts and no empty pagination. No schema change. See [UPGRADING](UPGRADING.md#to-1120).

### Added

- **`@!NowoWikiBundle` Twig namespace:** `TwigPathsPass` also registers the bundle views under `!NowoWikiBundle` (the bundle templates without the `templates/bundles/NowoWikiBundle/` overrides), so an app override can extend the original page with the conventional `{% extends '@!NowoWikiBundle/manage/page_view.html.twig' %}` instead of the Symfony bundle-name alias `@!Wiki`.
- **Catalogue keys** (7 locales): `wiki.search.query` (label of the search field), `wiki.space.pick_page_read_only`, `wiki.page.error.invalid_slug` / `slug_exists` / `save_failed`, `wiki.import.failed`, `wiki.import.report`.

### Changed

- **Flashes are catalogue messages:** the controller builds every flash as a `TranslatableMessage` in the `NowoWikiBundle` domain (stored translated when the translator is available, as before; the `TranslatableMessage` itself otherwise, so a layout printing flashes with `|trans` still translates them). The page-create errors (empty, invalid or duplicate slug) and the import failure / report are catalogue keys with parameters instead of the services' English sentences (`Page slug already exists in this space.`, `2 created, 0 updated, …`).
- **Search form:** the `q` field is labelled `wiki.search.query` ("Search for") instead of repeating the placeholder text. With `tenant_only: true` (and tenant paths) the space stays a hidden field: there is no "All spaces" choice where every page lives under a tenant path.
- **Space home:** the header intro says "Select a page from the sidebar or create one." only to users who may create pages; the others read `wiki.space.pick_page_read_only`.
- **FormKit `wiki` profile:** the seeded profile (when the host defines none) keeps the alias and the `NowoWikiBundle` translation domain only; it no longer forces Bootstrap classes (`form-control`, `form-select`, `form-check-*`, `mb-2`) on every wiki field, so the host form theme decides (Tailwind / custom hosts).
- **Pagination:** the space index and the version history pass no `Pagination` when everything fits on one page (no empty pagination region / "1 of 1").

### Fixed

- **Search excerpts glued blocks together** ("OverviewThis runbook…"): block boundaries (paragraphs, headings, list items, table cells, line breaks, …) become spaces before the tags are stripped; entities are decoded and whitespace (no-break spaces included) collapses to one space.
- **Diff markers:** the "Added:" / "Removed:" screen-reader text uses the bundle's own `nowo-wiki-sr-only` class (still with `visually-hidden`), so hosts without Bootstrap / UiKit CSS no longer print it; the `+` / `-` marker is `aria-hidden`.

## [1.11.1] - 2026-10-30

Bundle routes are opt-in again: controllers are no longer autoconfigured, so a host's routing.controllers import does not expose them (REQ-SF-004 rule 7). See [UPGRADING](UPGRADING.md#to-1111).

### Fixed

- **Wiki routes registered without the route loader (REQ-SF-004 rule 7):** on Symfony >= 7.3 `#[AsController]` and the `#[Route]` attributes autoconfigured `WikiManageController` and `WikiAttachmentController` with the `routing.controller` tag, so a host whose `config/routes.yaml` has the default `controllers: { resource: routing.controllers }` got the default `/tools/wiki/...` routes even without importing the `nowo_wiki` loader, bypassing `nowo_wiki.routes`, `route_prefix` and `web_ui.enabled: false` (still behind the wiki access checks). The controllers are registered explicitly (`autoconfigure: false`, `controller.service_arguments` + `container.service_subscriber`, no `#[AsController]`); the routes exist only where the host imports `type: nowo_wiki`. Regression test with a kernel that only loads `routing.controllers`.

## [1.11.0] - 2026-10-30

Wiki: Markdown storage (`editor.type: markdown`) — revisions keep their Markdown source with a sanitized HTML render cache, an extensible league/commonmark renderer, GFM everywhere, a configurable sanitizer allowlist and `wiki:convert-markdown` to move existing pages. **Schema change:** two columns on `<prefix>_page_revisions`. See [UPGRADING](UPGRADING.md#to-1110).

### Added

- **Markdown storage:** `nowo_wiki.editor.type` (`tiptap` default | `markdown`). Revisions gain `content_markdown` (nullable) and `format` (`html` | `markdown`); with `markdown` the page form writes Markdown, the Markdown is the source and `content_html` is its sanitized render cache (page view, AI context, attachment references keep reading it). The page body is a plain textarea, or the host form type of `editor.markdown_form_type` (e.g. a Markdown editor bundle) with the editor options of `WikiEvents::EDITOR_OPTIONS`; an HTML page opened in the Markdown editor is converted for the form and saved as Markdown.
- `Markdown\WikiMarkdownRendererInterface` (alias) and `Markdown\WikiMarkdownRenderer`: league/commonmark + GitHub-flavoured Markdown, raw HTML escaped, unsafe links dropped, plus every `WikiMarkdownExtensionInterface` service (autoconfigured tag `nowo_wiki.markdown_extension`; none by default) — the extension point for the page-link / mention / icon / directive dialect of an editor bundle.
- `Markdown\WikiMarkdownReferenceExtractor`: the `[[wiki:<uuid>]]` / `[[wiki:<uuid>#anchor]]` page references of a Markdown revision (outside code), for host backlink indexes (listen to `nowo_wiki.page.saved`).
- `bin/console wiki:convert-markdown <space>... --author-id=<id> [--dry-run]`: appends one Markdown revision per page whose current revision is HTML (`WikiMarkdownConverter::htmlToMarkdown()`, archived pages included, older revisions untouched); idempotent. `WikiPageService::convertToMarkdown()`.
- `WikiPageService::create()` / `saveRevision()` take an optional `$format` (`WikiPageRevision::FORMAT_HTML` default, `FORMAT_MARKDOWN`); `restore()` keeps the format of the restored revision.
- `nowo_wiki.sanitizer.extra_allowed_elements` (element => its extra attributes) and `extra_allowed_attributes` (on every element): extra HTML an editor dialect emits (e.g. `<details>` / `<summary>`, `data-*`). Script-capable, embedding and form elements, `style`, URL attributes and `on*` handlers are refused at configuration time and by `WikiHtmlSanitizer::assertSafeAllowlist()` in the constructor.
- `WikiPageSavedEvent::$conversion`: `true` on the events dispatched by `wiki:convert-markdown`, so hosts index the new revision without notifying anyone.
- Translation key `wiki.editor.markdown_help` in the seven locales; demo migration for the two new columns.

### Changed

- `WikiMarkdownConverter::markdownToHtml()` reads GitHub-flavoured Markdown (tables, task lists, strikethrough, autolinks); task lists come out in the Tiptap markup (`data-type="taskList"` / `taskItem`, `data-checked`) instead of `<input>` checkboxes. `htmlToMarkdown()` writes GFM tables, task lists (`- [x]`) and strikethrough (`~~`).
- Markdown revisions: search matches the Markdown source, export writes it as is, diffs compare Markdown lines, and imports keep the Markdown when `editor.type` is `markdown`.

## [1.10.0] - 2026-10-30

Wiki: UiKit page contract (scaffolds, value-free view models, dialogs); SEC-004 BR: hidden pages never listed, searched, exported or sent to AI, access_roles enforced, attachment downloads gated, allow_unauthenticated debug-only. See [UPGRADING](UPGRADING.md#to-1100).

Wiki: pages on the UiKit page contract (scaffolds, value-free view models, `<dialog>` confirm), canonical web_ui / security.roles keys, hidden pages filtered from lists and AI, CSRF and roles fail closed. See [UPGRADING](UPGRADING.md#unreleased).

### Added

- **UiKit page contract (REQ-UI-003):** every wiki page extends a UiKit scaffold — index, history and search the list scaffold; space, page view and diff the show scaffold; page edit and AI question the form scaffold. `Ui\WikiPageFactory` builds the view models (page headers with actions, breadcrumbs and back links, columns, value-free rows, row actions, `Pagination`, empty states) and `Ui\WikiPages` resolves the layout and the per-page templates. `docs/TEMPLATES.md` lists every page, view model and data block; `tests/Integration/Contract/PageContractTest.php` extends UiKit `PageContractTestCase` (17 fixtures × none / bootstrap5 / tailwind, strict missing-translation rule).
- Canonical configuration: `web_ui.path_prefix`, `web_ui.templates.<page>` and `security.roles.<action>` (`admin`, `list`, `create`, `edit`, `history`, `archive`, `ai`, `import`, `export`); `web_ui.enabled: false` now registers no route.
- **Archive** action on the page view (POST + CSRF behind the UiKit confirm dialog); Restore of a revision also asks for confirmation.
- `CsrfProtectionPass`: with the Web UI enabled the container refuses to compile without form CSRF protection or the CSRF token manager (fail closed); `symfony/security-csrf` is a direct requirement.
- Demo: UiKit host layout (`templates/layouts/admin.html.twig` + masthead), Bootstrap 5 form theme, migration for the 1.9 tables (`<prefix>_page_links`, `<prefix>_attachments`; the 1.9.0 demo answered 500 on page views without it), seeded revisions and an archived page; Playwright e2e for the space page, Archive and Restore confirmations; README screenshots of the page view, the space home and the history.
- Translation keys for the new labels and the FormKit convention keys of the page form (`wiki_page_form.*`), in the seven locales.

### Changed

- **`security.access_roles` is enforced as the general gate** of the default checker: every permission needs an access role and a role of that action (admin roles excepted). The container refuses to compile when `access_roles` and every action list are empty without an `access_checker`.
- Pages the user may not view (`WikiAction::View` per page) are left out of the sidebar tree (with their sub-pages), the archived list, search results, AI sources and the AI context (`WikiContextRetriever`, `WikiKnowledgeSearchTool`).
- Templates: no layout, CDN, framework class, local page header / pagination / flashes / modal partial or inline JS of their own; the AI question of the index is a UiKit `<dialog>`. `wiki.css` only lays out the tree, the two columns, the page body and the diff with the UiKit `--nowo-ui-*` tokens; `wiki.js` scrolls the current page of the tree into view.
- Form types carry no framework classes; flash messages are translated when they are added.
- `nowo-tech/ui-kit-bundle` ^2.17.
- CI: the coverage job runs `composer test-coverage-100` (fails below 100%).

### Security

- **Hidden pages no longer leak** (1.9.0 regression of the per-page `view` rule of a host `WikiSpaceAccessCheckerInterface`): they are left out of the sidebar tree (with their sub-pages; the truncation notice counts readable pages only), the archived list, search, **the space export ZIP**, AI sources, the AI context and `wiki_knowledge_search`. Space lists and the global AI question follow the per-space `view` / `ask_ai` decision.
- **`security.access_roles` is enforced** (it was inert in 1.0.0–1.9.0); attachment downloads need `canList()` and uploads `canEdit()` of the role checker.
- An explicit `~` on `security.roles.<action>` or a deprecated `<action>_roles` key means no role (it fell back to the default in the 1.10 development line).
- `security.allow_unauthenticated` and `web_ui.enabled` must be literal booleans; `allow_unauthenticated` is refused in the `prod` environment and in non-debug kernels. With the Web UI enabled the container refuses to compile without form CSRF protection.
- Restore checks the CSRF token before looking the revision up; `.gitattributes` keeps demo, tests and tooling out of the dist archives.
- REQ-SEC-004 re-audit 2026-10-30 (Appendix BR): see [SECURITY](SECURITY.md#req-sec-004).

### Deprecated

- `route_prefix`, `templates.*`, `web_ui.css_framework` and `security.<action>_roles` (see UPGRADING); a pre-1.10 layout without the UiKit canonical blocks is wrapped in a deprecated bridge.

### Removed

- `layout.html.twig`, `base.html.twig`, `manage/_page_header.html.twig`, `manage/_pagination.html.twig`, `manage/_flashes.html.twig`, `manage/_ai_ask_modal.html.twig`, `manage/_ai_ask_form_fields.html.twig`, `manage/_page_tree_list.html.twig`, the 1.9 page blocks and the `--nowo-wiki-*` tokens (Template contract in UPGRADING).

[1.10.0]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.10.0

## [1.9.0] - 2026-10-30

Wiki: space-aware access (404 / 403), tenant paths, restore / unarchive, events, page links to host objects, attachments and image upload, themable templates See [UPGRADING](UPGRADING.md#to-190).

### Added

- **Space- and page-aware access** (`WikiSpaceAccessCheckerInterface::isGranted(user, WikiAction, space, ?page)`, `WikiAction` enum: `view`, `create`, `edit`, `archive`, `history`, `restore`, `import`, `export`, `manage`, `ask_ai`). Every manage route asks it with the resolved space and page: a space or page the user may not view answers **404** (indistinguishable from a missing one), a denied write on a readable space answers **403**. Templates receive `permissions` (every action's decision). The default `LegacyWikiSpaceAccessChecker` keeps the 1.x user-level decision; override the alias to decide per tenant.
- **`WikiSpaceLocatorInterface`**: finds (or provisions on the first visit) the space a route slug names; the default keeps the 1.x lookup among the user's spaces.
- **Tenant paths** (`nowo_wiki.tenant_paths`, `tenant_only`): copies of every space-scoped route under host paths such as `/projects/{spaceSlug}/wiki`, each with a name prefix and a slug requirement, and a router decorator (`WikiTenantUrlGenerator`) that generates the tenant copy for a matching `spaceSlug`, so templates and redirects keep the bundle route names.
- **Restore a revision** (POST `page_restore`, CSRF `wiki_restore_<page id>`): appends a new revision with the old body; history stays append-only. **Unarchive** (POST `page_unarchive`) and an *Archived pages* list on the space page for users allowed to archive.
- **Events** `nowo_wiki.page.created`, `.unarchived`, `.restored`, `.linked`, `.unlinked`, `nowo_wiki.attachment.uploaded` / `.deleted` and `nowo_wiki.editor.options` (add TiptapEditorType options per space / page); `WikiPageSavedEvent` carries the previous revision and the actor, `WikiPageArchivedEvent` the actor.
- **Links from pages to host objects** (`WikiPageLink`: page, object type, object id, created by / at, unique triple; table `<prefix>_page_links`), `WikiPageLinkRepositoryInterface` (`findByHostObject`, batch `findByHostObjects`, `findByPage`), `WikiObjectLinkResolverInterface` (tag `nowo_wiki.object_link_resolver`, autoconfigured: one `resolveVisible()` call per type with every id) and `WikiPageLinkService` (`link`, `unlink`, `usedIn`). The page view shows **Used in** (only the objects the viewer may open) in a new `page_aside` block.
- **Attachments and image upload** (`nowo_wiki.attachments`, off by default): `WikiAttachment` (table `<prefix>_attachments`), `WikiAttachmentStorageInterface` (default: a private local directory), `WikiAttachmentScannerInterface` (default: accepts every file), `WikiAttachmentLimitsProviderInterface` (10 MiB per file, 500 MiB per space, an extension + sniffed MIME allowlist without SVG / HTML / XML / scripts), `WikiAttachmentService` (upload, delete, orphan and stray-file cleanup) and `WikiAttachmentController`: upload (POST, CSRF header `X-CSRF-Token`, asks `edit` on the page, 422 with a reason and nothing stored on refusal) and download (asks `view` on the attachment's own page, 404 for every denial, `Cache-Control: private, no-store`, `nosniff`, a sandboxing CSP, inline only for raster images). The page editor gets the TiptapEditorBundle upload hook when attachments are enabled.
- **`WikiAttachmentReferenceFilter`**: on save, an image of another space's (or an unknown) attachment is removed and a link to one loses its `href`, so a page never embeds a file its readers may not open.
- **Themeable manage templates** (FR-006): every structural element carries a semantic BEM class next to its Tabler / Bootstrap ones (`nowo-wiki`, `nowo-wiki--<view>`, `nowo-wiki-header`, `nowo-wiki-layout`, `nowo-wiki-sidebar`, `nowo-wiki-tree`, `nowo-wiki-page`, `nowo-wiki-content`, `nowo-wiki-actions`, `nowo-wiki-btn`, `nowo-wiki-panel`, `nowo-wiki-history`, `nowo-wiki-diff`, `nowo-wiki-search-form`, `nowo-wiki-results`, `nowo-wiki-form`, `nowo-wiki-pagination`, `nowo-wiki-flash`, `nowo-wiki-ai-modal`, `nowo-wiki-used-in`, `nowo-wiki-archived`, …). New overridable blocks: `wiki_header` (every page), `wiki_flashes`, `space_sidebar`, `space_sidebar_header`, `page_tree`, `page_main`, `page_header`, `page_actions`, `page_body` (page view), `space_main`, `space_search`, `space_import_export`, `space_archived` (space), `history_list`, `history_pagination`, `history_footer`, `diff_body`, `diff_footer`, `search_form`, `search_results`, `page_form`, `page_form_fields`, `page_form_actions`, `wiki_search_highlight`, `wiki_ai_highlight`, `wiki_ai_modal`, `space_list`, `ai_error`, `ai_answer`, `ai_sources`, `ai_follow_up`, `ai_form`; one tree item is the partial `manage/_page_tree_item.html.twig`.
- **Design tokens** in `wiki.css` (`--nowo-wiki-surface`, `-surface-muted`, `-border`, `-ink`, `-muted`, `-accent`, `-accent-ink`, `-accent-soft`, `-ai`, `-radius`, `-radius-sm`, `-gap`, `-gap-sm`, `-sidebar-width`, `-tree-indent`, `-font-mono`, `-line-height`, `-code-bg`, `-diff-*`, `-danger*`, `-warning*`, `-success*`, `-info-soft`, container widths) with a dark variant (`prefers-color-scheme: dark` unless `[data-theme="light"]`, and `[data-theme="dark"]`). When `web_ui.css_framework` is not `tabler` / `bootstrap*` the page root gets `data-nowo-wiki-skin="tokens"` and `wiki.css` draws the layout (sidebar + content, one column on phones), panels, buttons, forms and lists from the tokens; skin rules use `:where()`, so any host rule wins.
- **CSP nonce**: every `<script>` of the bundle templates (`wiki.js`, the Tiptap editor, the demo Bootstrap bundle) carries `nonce="…"` from the request attribute `csp_nonce` when the host sets it.

### Changed

- `WikiPageFormType` has an `editor_options` option (extra TiptapEditorType options); requires `nowo-tech/tiptap-editor-bundle` ^1.6 (upload and suggestion hooks).
- **Templates without inline styles or handlers**: `base.html.twig` no longer prints an inline `<style>` block (its rules moved to `wiki.css`; the body background now applies only to the bundle's own `layout.html.twig`), and the page tree indents with nested lists and capped depth classes (`nowo-wiki-tree__item--depth-0` … `-6`, `data-wiki-depth`) instead of `style="--depth: N"`. The current page is marked `aria-current="page"` in the tree. The index space cards and the search results are lists (`<ul>` / `<li>`), the page header is a `<header>`, the sidebars `<aside>` and the page an `<article>`. A test fails on any `style=` / `on*=` attribute, `<style>` tag or `<script>` without the nonce in the bundle views.

[1.9.0]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.9.0

## [1.8.1] - 2026-10-29

Wiki: routes declared with #[Route] attributes (configurable paths/names kept) See [UPGRADING](UPGRADING.md#to-181).

### Changed

- Routes are declared with `#[Route]` on `WikiManageController` (REQ-SF-004); `WikiRouteLoader` reads them and still applies the configured `nowo_wiki.routes.<key>` path / name and `route_prefix`. Methods and requirements are unchanged.

[1.8.1]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.8.1

## [1.8.0] - 2026-10-29

Wiki: pagination, PSR-3 logging, PHPStan level 8, tree export keeps grandchildren, AI timeout hierarchy See [UPGRADING](UPGRADING.md#to-180).

### Added

- `nowo_wiki.pagination` (`spaces_per_page` 24, `revisions_per_page` 25, `max_tree_pages` 500): the wiki index and the page history are paginated (`?page=N`, new `manage/_pagination.html.twig`), and the sidebar tree loads at most `max_tree_pages` pages with a notice linking to search (REQ-PERF-001).
- PSR-3 logging on the `nowo_wiki` channel: start / finish / failure of imports and exports, page archiving and every AI agent call — slugs, counters and lengths only, never content, questions, answers or secrets (REQ-OBS-001). `psr/log` is now a direct requirement.
- `make validate-translations` also checks the seven required locales and key parity against `en` (`scripts/check-translation-parity.php`, REQ-MAKE-004).
- Demo Caddyfiles set the REQ-RUNTIME-001 timeout hierarchy (PHP 60 s, Caddy write 75 s, FrankenPHP `max_wait_time` 30 s above the 30 s AI HTTP timeout); documented in CONFIGURATION / DEMO-FRANKENPHP.
- Translation keys `wiki.space.tree_truncated` and `wiki.pagination.*` in all seven locales.

### Changed

- `WikiPageRepositoryInterface::findActiveBySpace()` takes an optional `?int $limit`, `WikiPageRevisionRepositoryInterface::findByPage()` optional `?int $limit` / `int $offset` (see UPGRADING).
- `SymfonyAiWikiAssistant::ask()` throws `UnexpectedValueException` when the agent returns a non-text result instead of a `TypeError`.
- PHPStan raised from level 6 to level 8 (`ignoreErrors: []`); the last inline `@phpstan-ignore` was removed (`WikiAuthorResolver` now checks that `user_class` exists).
- `make help` lists every standard target in the REQ-MAKE-001 order; the demo `up` prints `Demo started at: http://localhost:<PORT>`.
- TypeScript coverage fails below 100% (Vitest `coverage.thresholds`); PHPDoc on every public class and method.
- Docs: Twig override subpath table and recovery procedure (REQ-TWIG-001), translation override procedure (REQ-I18N-001), SECURITY secrets / logging / limits sections and a complete 12.4.1 release checklist, PSR evaluation (PSR-3 adopted), `docs/COVERAGE.md`.

### Fixed

- The page tree (sidebar and Outline / Notion exports) dropped grandchildren and deeper pages; `WikiPageTreeBuilder` now builds the tree recursively.
- Space imports reject ZIP entries with absolute or `..` paths before extracting.

### Removed

- The legacy array-mapping branch of `WikiMetadataListener` (doctrine/orm ^3 only produces `AssociationMapping` objects) and coverage-ignored dead branches in the interchange iterators.

[1.8.0]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.8.0

## [1.7.2] - 2026-10-28

Wiki: the page editor loads its Tiptap script; the search results count is pluralised. See [UPGRADING](UPGRADING.md#to-172).

### Fixed

- **Page editor script.** `manage/page_edit.html.twig` resolved the Tiptap script without the `nowo_tiptap_editor`
  asset package, so it requested `/tiptap-editor.js` (404) and the editor never loaded.
- **Search results count.** `wiki.search.results_count` was an ICU message in non-ICU catalogues, printed raw
  (`{count, plural, …}`); all seven locales use Symfony's plural syntax now.

[1.7.2]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.7.2

## [1.7.1] - 2026-10-28

Security: import / export temp directories are 0700 and the export ZIP 0600. See [UPGRADING](UPGRADING.md#to-171).

### Security

- **Import / export temp files are owner-only.** Import and export working directories were created `0777` in the
  shared temp dir and the export ZIP with the default umask (usually world-readable): other local users could read
  exported private spaces until the download finished. Directories are now `0700` and the ZIP `0600`.

[1.7.1]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.7.1

## [1.7.0] - 2026-10-26

Forms in the bundle views are Symfony Form Types rendered with form_start / child loop / form_end, no raw form controls (REQ-TWIG-005). See [UPGRADING](UPGRADING.md#to-170).

### Changed

- Manage views render every form through Symfony Form Types (REQ-TWIG-005): search filters (`WikiSearchType`, GET, same `space` / `q` query names), the space ZIP import (`WikiImportType`, same field names and token id) and the AI question forms (`WikiAiAskType`, same field names and token id `wiki_ai_ask`); import and AI POSTs are validated through the Form. The page editor drops the hand-rolled top-level `_token`: `WikiPageFormType` is created with `csrf_token_id` `wiki_edit_<page id>` / `wiki_new_<space id>` and its own CSRF field is checked, so an invalid token now re-renders the form with an error (HTTP 422) instead of a 403. Template overrides must render the new form variables (see UPGRADING).

### Added

- `make check-no-raw-html-form` (REQ-TWIG-005) fails on raw `<form>` / `<input>` / `<textarea>` / `<select>` in bundle and demo Twig; it runs in `release-check`.

### Changed

- Developer tooling: `make release-check` runs the full REQ-MAKE-002 chain in order (`ensure-up` first, then `check-no-cursor-coauthor`, `check-open-prs`, `check-twig-extra`, `composer-sync`, `cs-fix`, `cs-check`, `rector-dry`, `phpstan`, `igor`, `validate-translations`, `test-coverage-100`, demo release-check, `test-ts`); `check-open-prs` also fails on conflicted pull requests (REQ-REL-003). `check-no-raw-html-form` (REQ-TWIG-005) is wired once the remaining raw form markup is migrated.
- Internal: applied the pending Rector suggestions (`readonly` on the final classes `WikiArchiveHelper`, `WikiHtmlSanitizer`, `EmbedIframeSrcAttributeSanitizer`; `instanceof` null check in `WikiTokenGuard`; `??=` in `WikiDocumentTreeReader`); no behaviour change. `make rector-dry` is clean again.
- Developer tooling: new `make rector` target applies the Rector refactorings (`make rector-dry` only reports them).

[1.7.0]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.7.0

## [1.6.4] - 2026-10-24

Bundle views render form bodies through the child loop (REQ-TWIG-003). See [UPGRADING](UPGRADING.md#to-164).

### Changed

- Dependencies refreshed (Composer and pnpm/npm lock files of the bundle and its demos, campaign 2026-10-19).
- `.php-cs-fixer.dist.php`: `new ParallelConfig(2)` (REQ-CS-001).
- Views: `manage/page_edit.html.twig` keeps the custom markup of the title and content fields (rule 3) and renders
  any remaining child through the child loop (`{% for child in form %}` + `not child.rendered` + `form_row(child)`),
  closing with `form_end(form)` instead of `render_rest: false`, per REQ-TWIG-003.
- Documentation: wave-1 REQ compliance — README preamble order, stars badge image and documentation list order, CONTRIBUTING Cursor Agent settings and post-release-commit trailer check, canonical bug-report template, demo `.env.example` / `.env.test` comments, DEMO-FRANKENPHP troubleshooting linked from `demo/README.md`, doc TOCs, Spec Kit inventory refresh (92/92).

[1.6.4]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.6.4

## [1.6.3] - 2026-10-18

Allows nowo-tech/ui-kit-bundle 2.x (^1.4 || ^2.2) so hosts can move to UiKit 2.2. See [UPGRADING](UPGRADING.md#to-163).

### Changed

- Allows nowo-tech/ui-kit-bundle 2.x (`^1.4 || ^2.2`); the dev lock and the demo lock use 2.2.0. Templates are unchanged (compatibility release). With UiKit 2.x `nowo-ui.css` ships in `@layer nowo-ui`, so unlayered host/framework CSS wins equal-specificity conflicts with kit rules in the wiki pages; to keep the 1.x cascade, override the `stylesheets` block of `base.html.twig` / `layout.html.twig` and link `css/nowo-ui.unlayered.css` (UiKit 2.x only).

[1.6.3]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.6.3

## [1.6.2] - 2026-10-13

### Changed

- `WikiExtension::load()` now loads `services.yaml` before its explicit definitions, so `NullWikiTeamMembershipResolver`, `WikiHtmlSanitizer` and the access checkers keep the definitions registered by the extension instead of being overwritten by the `Security/` resource scan. No runtime behaviour change (both services have no required arguments); a DI test asserts the final definitions and aliases.

[1.6.2]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.6.2

## [1.6.1] - 2026-10-13

### Fixed

- Space export (`GET /tools/wiki/spaces/{slug}/export`) no longer fails with a 500 "You cannot guess the mime type as the Mime component is not installed": the ZIP download now sets `Content-Type: application/zip` explicitly instead of relying on `symfony/mime` (not a dependency of the bundle) to guess it.

[1.6.1]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.6.1

## [1.6.0] - 2026-10-13

### Fixed

- Flash messages are now rendered by the bundle templates (new `manage/_flashes.html.twig`, included from `_page_header.html.twig` and `page_view.html.twig`): import errors, the import report and creation errors were previously added to the session but never shown.
- Search: LIKE wildcards in the query (`%`, `_`) now match literally on every platform (`ESCAPE '!'` clause with proper escaping); before, `50%` or `snake_case` found nothing on SQLite/PostgreSQL.
- Search excerpts are centred on the match when the text before it contains multibyte characters (`mb_stripos` instead of a byte offset).
- Templates use the `include()` function instead of the `{% include %}` tag so `composer twig:lint` passes.

### Deprecated

- `nowo_wiki.database.driver`, `nowo_wiki.database.platform` and `nowo_wiki.firewall` were never read; they now trigger a deprecation and will be removed in 2.0.

### Changed

- Development: vitest and `@vitest/coverage-v8` upgraded to ^4.1.11.
- Docs: `UPGRADING.md` no longer repeats the "From 1.3.3 to 1.3.4" section or a stray `# Upgrading` heading; the 1.3.0 Twig notes are merged into the "To 1.3.0" section.

[1.6.0]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.6.0

## [1.5.0] - 2026-10-13

### Security

- `WikiHtmlSanitizer` is now built on `symfony/html-sanitizer` (DOM parsing, explicit element/attribute allowlist). The former regex/`strip_tags` implementation could be bypassed (stored XSS) with unquoted event handlers (`<img src=x onerror=…>`), unclosed `<iframe>`, `<iframe srcdoc>`, unquoted or entity-encoded `javascript:` URLs and `style` attributes. Links accept only http/https/mailto (or relative) URLs, media only http/https (or relative), `style` only a plain `text-align`, and iframes only an https `src` on the embed host allowlist.
- Page bodies are re-sanitized at render time: `manage/page_view.html.twig` uses the new `nowo_wiki_safe_html` Twig filter instead of `|raw`, so revisions stored before this fix are cleaned when displayed.
- ZIP imports are rejected before extraction when they contain more than 10 000 entries or more than 512 MiB of declared uncompressed data (ZIP bomb protection; limits are `WikiArchiveHelper` constructor arguments).

### Added

- `symfony/html-sanitizer` is now a runtime dependency.
- `nowo_wiki_safe_html` Twig filter for rendering stored wiki HTML in custom templates.

[1.5.0]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.5.0

## [1.4.1] - 2026-10-13

### Changed

- Development: `composer.json` pins `config.platform.php` to 8.2.0 so the committed lock stays installable on the minimum PHP; CI overrides the platform per matrix cell.
- Repository: `.twig-cs-fixer.cache` is now git-ignored.

### Dependencies

- `nowo-tech/form-kit-bundle` 2.6.1 → 2.7.1 (development lock; #54).
- `nowo-tech/ui-kit-bundle` 1.9.2 → 1.11.0 (development lock; #55).
- `doctrine/orm` 3.7.4 → 3.7.5 (development lock; #56).
- `jsdom` 30.1.1 → 30.1.2 (frontend tooling; #57).
- `vite` 8.3.2 → 8.3.4 (frontend tooling; #58).

[1.4.1]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.4.1

## [1.4.0] - 2026-10-09

### Changed

- **Doctrine ORM SortDirection:** the Doctrine ORM repositories (`DoctrineOrmWikiPageRepository`, `DoctrineOrmWikiPageRevisionRepository`, `DoctrineOrmWikiSpaceRepository`) pass `\SortDirection::Ascending`/`Descending` to QueryBuilder `orderBy()`/`addOrderBy()` instead of `'ASC'`/`'DESC'` (doctrine/orm deprecation, https://github.com/doctrine/orm/issues/11313).
- **Requirement:** `doctrine/orm` is now `^3.7` (was `^2.15 || ^3.0`).

### Fixed

- Tests: `WikiRepositoryWorkerTest` enables Doctrine native lazy objects only on PHP 8.4+, keeping the worker integration test green across the CI matrix.

### Dependencies

- Runtime lock: `doctrine/orm` 3.7.4, `doctrine/dbal` 4.5.0, `nowo-tech/form-kit-bundle` 2.6.1, `nowo-tech/ui-kit-bundle` 1.9.2, `nowo-tech/tiptap-editor-bundle` 1.3.10, `league/commonmark` 2.10.3, `league/html-to-markdown` 5.1.3 (Dependabot #42–#52 + update).
- Dev tooling: `igor-php/igor-php` ^0.10 (v0.10.1), `nowo-tech/phpstan-frankenphp` v1.2.3, `phpstan/phpstan` 2.3.1, `phpstan/phpstan-phpunit` 2.1.1, `phpstan/phpstan-symfony` 2.1.0, `rector/rector` 2.7.0, `phpunit/phpunit` 10.5.66, `friendsofphp/php-cs-fixer` 3.95.27.
- JS dev: Vite 8.3.2, jsdom 30.1.1.
- Demo (Symfony 8): same runtime bumps plus `doctrine/migrations` 3.9.8, `twig/twig` v3.30.0, `twig/extra-bundle` v3.29.0, `nowo-tech/hot-reload-bundle` v1.5.5, `nowo-tech/twig-inspector-bundle` v1.1.7.

[1.4.0]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.4.0

## [1.3.5] - 2026-09-27

### Fixed

- **FrankenPHP worker (no kernel reset):** repositories and `WikiSearchService` resolve the entity manager per call through `WikiEntityManagerProvider`, resetting a manager closed by a failed flush in an earlier request; page/space reads refresh from the database so changes by other workers are not served stale.
- **Security (worker):** new `WikiTokenGuard`; the controller, `ConfigurableWikiAccessChecker` and `WikiKnowledgeSearchTool` ignore a token left by a previous request when the current request is not behind a secured firewall.
- **Export:** a failed space export removes the temporary ZIP before rethrowing.

### Added

- **REQ-CS-008:** `igor-php/igor-php` (require-dev only), root `igor.json`, Composer/`Makefile` `igor` target, and `release-check` wiring for FrankenPHP worker-state audit.
- `ai.http_timeout` (default 30 s) and the `nowo_wiki.ai.http_client` service (timeout + max duration) to set as `http_client` of the AI platform.

### Changed

- **Worker safety (Igor):** justified `// @igor-ignore` annotations and/or `ResetInterface` / request-scoped fixes so `make igor` passes on package `src/`.
- Repository and `WikiSearchService` constructors accept `EntityManagerInterface|WikiEntityManagerProvider`; controller, access checker and AI tool accept an optional `WikiTokenGuard`. See [UPGRADING](UPGRADING.md) and [FRANKENPHP-WORKER-AUDIT](FRANKENPHP-WORKER-AUDIT.md).

[1.3.5]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.3.5

## [1.3.4] - 2026-08-24

### Changed

- **Demos:** MySQL env policy in FrankenPHP stack (REQ-DEMO-011).
- **QA:** add `phpstan-frankenphp` extension (REQ-CS-005).
- **Assets:** English JSDoc on TypeScript sources (REQ-ASSETS-002).
- **Docs:** PHP-FIG PSR evaluation (REQ-CS-007).
- **Style:** PHP CS Fixer alignment.

### Notes

- **No API or configuration changes** for integrators unless noted above.

[1.3.4]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.3.4

## [1.3.3] - 2026-08-19

### Changed

- Code style only (PHP CS Fixer). **No application upgrade steps.**

[1.3.3]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.3.3

## [1.3.2] - 2026-08-18

### Changed

- **Demos:** pin `nowo-tech/hot-reload-bundle` to `^1.4` with FrankenPHP Mercure/`hot_reload` (`dev`/`test` only).

[1.3.2]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.3.2

## [1.3.1] - 2026-08-07

### Fixed

- **CI / FormKit:** `WikiControllerContainerBuilder` injects `FormOptionsMerger` via `FormKitTestSupport::withMerger()` so controller unit tests no longer hit uninitialized `$formOptionsMerger` on `WikiPageFormType`.

[1.3.1]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.3.1

## [1.3.0] - 2026-08-04

### Changed

- **FormKitBundle:** depend on [`nowo-tech/form-kit-bundle`](https://github.com/nowo-tech/FormKitBundle) ^2.0. Admin form types use `FormOptionsTrait` + profile `wiki` (`#[FormKitConfig]`). Extension prepends that profile when missing; form types are tagged `form.type` so `FormOptionsMerger` is injected.

### Added
- **REQ-TWIG-004:** require `twig/extra-bundle` + `twig/string-extra`; `make check-twig-extra` in `release-check`; demos register `TwigExtraBundle`.
- **Twig-CS-Fixer:** `vincentlanglet/twig-cs-fixer`, `.twig-cs-fixer.php`, `composer twig:lint` / `twig:fix`.

### Changed

- **REQ-UI-001-kit:** Requires `nowo-tech/ui-kit-bundle` `^1.4`. Layout/base load `asset('css/nowo-ui.css', 'nowo_ui_kit')` and import `@NowoUiKitBundle/macros/ui.html.twig`. Extension seeds `nowo_ui_kit` from `web_ui.css_framework` when the host has not configured UiKit. Demo registers `NowoUiKitBundle` + `nowo_ui_kit.yaml`.

[1.3.0]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.3.0

## [1.2.0] - 2026-08-03

### Added

- **REQ-UI-002:** `security.allow_unauthenticated` (default `false`) with `AllowAllWikiAccessChecker`, compile-time SecurityBundle guard when the manage Web UI is enabled, and controller soft-gate (no hard `#[IsGranted('IS_AUTHENTICATED')]`).
- Docs: CONFIGURATION / SECURITY host firewall example; Flex recipe comments for `access_roles` / `allow_unauthenticated`.

### Changed

- Demo: resolve TipTap from Packagist (no sibling Docker mount); FrankenPHP docs/README aligned.
- Dev deps: `actions/stale` v11, jsdom 30.0.1, vite 8.2.0; TipTap / Rector lock refresh.

### Compatibility

- PHP `>=8.2 <8.6`; Symfony `^7.4 || ^8.0` (see `composer.json`).
- Manage UI with default security settings requires **SecurityBundle** (or set `allow_unauthenticated: true` for trusted local demos only).

### Upgrade

```bash
composer require nowo-tech/wiki-bundle:^1.2
```

See [UPGRADING.md](UPGRADING.md).

## [1.1.1] - 2026-07-31

### Fixed

- **CI / Vitest** — TypeScript job uses Node.js **22** so `jsdom@30` / `undici@8` can load (`markAsUncloneable`); Node 20 failed `pnpm run test:coverage`.
- **Demo** — `release-check` / `update-bundle` start the FrankenPHP container via `ensure-up` before Composer/exec.

### Tests

- Unit coverage for `web_ui.layout_template` → `nowo_wiki.templates.layout` and Twig `nowo_wiki_web_ui` prepend (restores **100%** PHP line coverage).

### Upgrade

```bash
composer require nowo-tech/wiki-bundle:^1.1.1
```

No application or config API changes. See [UPGRADING.md](UPGRADING.md).

## [1.1.0] - 2026-07-30

### Added

- Intermediate shell **`base.html.twig`**: manage pages extend it; stacks `wiki.css` / `wiki.js` with **`{{ parent() }}`** so host layouts keep their assets (REQ-UI-001).

### Changed

- Manage templates extend **`@NowoWikiBundle/base.html.twig`** instead of `layout` directly.
- Default **`layout.html.twig`**: package assets moved into `base`; CDN gated by `web_ui.css_framework` (Tabler/Bootstrap-compatible).

### Documentation

- **[USAGE](USAGE.md)** / **[CONFIGURATION](CONFIGURATION.md):** host layout preference, freeze rule, `base` shell.
- **[UPGRADING](UPGRADING.md)** section **To 1.1.0**.

## [1.0.5] - 2026-07-29

### Added

- FrankenPHP Friendly Worker Mode banner (REQ-DOCS-017); `make check-open-prs` / `demo-smoke`.
- `SYMFONY_DEPRECATIONS_HELPER=max[direct]=0` (REQ-SF-005).
- `nowo_wiki.web_ui` (`css_framework`, `layout_template`) + `nowo-ui-*` layout hooks (REQ-UI-001).
- GitHub About topics/homepage; optional GH workflows (stale, pr-lint, copilot-instructions).

### Changed

- Demo FrankenPHP image **PHP 8.5**; PHPStan level **8** with empty `ignoreErrors` (REQ-CS-005/006).
- README Documentation order and badge order (REQ-DOCS-002/004).
- Packagist keywords include `php` and `frankenphp`.

## [1.0.4] - 2026-07-22

### Added

- **Demo** — `FRANKENPHP_MODE` (`worker` default, or `classic`) selects FrankenPHP Caddyfile at container start; documented in [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md).

### Changed

- **Dependencies** — `nowo-tech/tiptap-editor-bundle` lockfile bump to `1.2.0` (constraint remains `^1.0`); dev `jsdom` to `29.1.1`.
- **REQ-GIT-001** — Co-author check uses `git --no-replace-objects`; strip script refuses dirty working trees; [GITHUB_CI.md](GITHUB_CI.md) expanded as canonical adoption doc.
- **Tooling** — PHP-CS-Fixer `fully_qualified_strict_types.import_symbols` enabled (import + short class names).

## [1.0.3] - 2026-07-16

### Fixed

- **CI / Doctrine** — `WikiMetadataListenerDoctrineTest` loads attribute metadata via `AttributeDriver` (no `EntityManager` / LazyGhost), fixing PHPUnit failures on PHP 8.4+ in GitHub Actions.
- **CI matrix** — Drop Symfony 7.0 (unsupported; `composer.json` requires `^7.4 || ^8.0`); coverage jobs use Symfony 7.4.

### Added

- **`symfony/var-exporter`** (`require-dev`) — available for Doctrine ORM proxy / LazyGhost scenarios in local and CI installs.

### Changed

- **CI** — Full `composer update --with-all-dependencies` in the test matrix; Dependabot bumps (`actions/checkout` v7, `actions/setup-node` v7, Vite, TypeScript).

## [1.0.2] - 2026-07-16

### Changed

- **Docs** — Rename `docs/GITLAB_CI.md` to `docs/GITHUB_CI.md` (GitHub Actions wording); update README and CONTRIBUTING links.

## [1.0.1] - 2026-07-16

### Fixed

- **i18n** — Complete missing `wiki.editor.help` and `wiki.ai.follow_up` keys in `de`, `fr`, `it`, `nl`, and `pt` translation files (parity with English/Spanish).

### Added

- **Code of Conduct** — Contributor Covenant (`CODE_OF_CONDUCT.md`).
- **Git hygiene (REQ-GIT-001)** — `.githooks/commit-msg`, `make setup-hooks`, `make check-no-cursor-coauthor`, CI job to reject Cursor co-author trailers.
- **Docs** — `docs/GITLAB_CI.md`; links from README and CONTRIBUTING; release checklist note to re-check co-author trailers before push.

### Changed

- **`make release-check`** — Runs `check-no-cursor-coauthor` first.

## [1.0.0] - 2026-07-13

### Added

- **Core wiki** — spaces (team/user scope), page tree, immutable revisions, diff, search.
- **Tiptap integration** — `WikiPageFormType` + `nowo-tech/tiptap-editor-bundle` (Notion variant).
- **Import / export** — Outline and Notion Markdown interchange (UI + `wiki:import` / `wiki:export` console commands, ZIP support).
- **Symfony AI (optional)** — `/tools/wiki/ask`, `WikiKnowledgeSearchTool`, context injection via `symfony/ai-bundle`.
- **Security** — `WikiAccessCheckerInterface`, `WikiSpaceAccessResolver`, CSRF on POST, `WikiHtmlSanitizer`, role-based import/export ACL.
- **Symfony** — dynamic routes, Twig overrides, Flex recipe `1.0.0`, Doctrine metadata listener.
- **i18n** — `NowoWikiBundle` translations (`en`, `es`, `fr`, `de`, `it`, `pt`, `nl`).
- **Tests** — 230 PHPUnit tests, 100% PHP coverage; Vitest for `wiki.ts`.
- **CI** — GitHub Actions (PHPUnit, PHPStan, Vitest, `composer audit`).
- **Demo** — Symfony 8 + FrankenPHP on port **8025** with auto-login fixtures.

### Security

- HTML sanitized on every revision save; see [SECURITY.md](SECURITY.md).

[Unreleased]: https://github.com/nowo-tech/WikiBundle/compare/v1.34.0...HEAD
[1.2.0]: https://github.com/nowo-tech/WikiBundle/compare/v1.1.1...v1.2.0
[1.1.1]: https://github.com/nowo-tech/WikiBundle/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/nowo-tech/WikiBundle/compare/v1.0.5...v1.1.0
[1.0.5]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.0.5
[1.0.4]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.0.4
[1.0.3]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.0.3
[1.0.2]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.0.2
[1.0.1]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.0.1
[1.0.0]: https://github.com/nowo-tech/WikiBundle/releases/tag/v1.0.0
