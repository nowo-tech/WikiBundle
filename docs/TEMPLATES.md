# Templates (UiKit page contract)

Since **1.10** every wiki page extends a [UiKitBundle](https://github.com/nowo-tech/UiKitBundle) page scaffold (REQ-UI-003). The host themes the scaffolds once (`nowo_ui_kit.layouts`, `nowo_ui_kit.theme`) and every wiki page follows; the bundle ships no layout, no CDN asset, no framework class of its own and no local copy of the UiKit partials (pagination, flashes, page header, modal, confirm).

## Table of contents

- [Pages](#pages)
- [View models](#view-models)
- [Data blocks and partials](#data-blocks-and-partials)
- [Layout](#layout)
- [Overriding one page](#overriding-one-page)
- [Unique pages](#unique-pages)
- [Contract test](#contract-test)
- [New page dialog and tree menu (1.35)](#new-page-dialog-and-tree-menu-135)

## Pages

| Page id (`web_ui.templates.<id>`) | Template | Scaffold | View model (`page`) | Other variables |
| --- | --- | --- | --- | --- |
| `index` | `@NowoWikiBundle/manage/index.html.twig` | `@NowoUiKit/page/list.html.twig` | `ListPage` from `WikiPageFactory::index()` | `ai_form` (WikiAiAskType view or null), `can_create`, `can_ask_ai`, `routes`, `dashboard_route` |
| `space` | `@NowoWikiBundle/manage/space.html.twig` | `@NowoUiKit/page/show.html.twig` | `ShowPage` from `WikiPageFactory::space()` | `tree`, `tree_truncated`, `max_tree_pages`, `tree_search_href`, `search_form`, `import_form` (null without import permission), `export_links` (1.21), `archived`, `can_create`, `can_import`, `can_export`, `permissions`, `routes`, `dashboard_route` |
| `page_view` | `@NowoWikiBundle/manage/page_view.html.twig` | `@NowoUiKit/page/show.html.twig` | `ShowPage` from `WikiPageFactory::pageView()` | `content_html`, `tree`, `tree_truncated`, `max_tree_pages`, `tree_search_href`, `used_in`, `can_edit`, `can_history`, `can_archive`, `permissions`, `routes`, `dashboard_route` |
| `page_edit` (create and edit) | `@NowoWikiBundle/manage/page_edit.html.twig` | `@NowoUiKit/page/form.html.twig` | `FormPage` from `WikiPageFactory::pageForm()` | `csrf_token_id`, `routes`, `dashboard_route` |
| `page_history` | `@NowoWikiBundle/manage/page_history.html.twig` | `@NowoUiKit/page/list.html.twig` | `ListPage` from `WikiPageFactory::history()` | `routes`, `dashboard_route` |
| `page_diff` | `@NowoWikiBundle/manage/page_diff.html.twig` | `@NowoUiKit/page/show.html.twig` | `ShowPage` from `WikiPageFactory::diff()` | `diff` (list of `{kind: same\|add\|remove, line}`), `routes`, `dashboard_route` |
| `search` | `@NowoWikiBundle/manage/search.html.twig` | `@NowoUiKit/page/list.html.twig` | `ListPage` from `WikiPageFactory::search()` | `query`, `global_search`, `routes`, `dashboard_route` |
| `ai_ask` | `@NowoWikiBundle/manage/ai_ask.html.twig` | `@NowoUiKit/page/form.html.twig` | `FormPage` from `WikiPageFactory::aiAsk()` | `question`, `answer`, `sources`, `error`, `csrf_token_id`, `routes`, `dashboard_route` |

Every page also reads the Twig global `nowo_wiki_web_ui` (1.21: a service, so any bundle order works; `assets`, `slots`, `import_export`, `space_index`; see [CONFIGURATION › Host hooks](CONFIGURATION.md#host-hooks-121)). Every page also receives `nowo_wiki_legacy_layout` / `nowo_wiki_legacy_blocks` (the deprecated pre-1.10 layout bridge, see [Layout](#layout)).

## View models

`Nowo\WikiBundle\Ui\WikiPageFactory` builds the UiKit view models (`Nowo\UiKitBundle\View\*`); `Nowo\WikiBundle\Ui\WikiPages` resolves the layout and the per-page templates. Labels are `Label` objects in the `NowoWikiBundle` domain; the scaffolds translate them.

**Value-free rule:** no view model and no page variable carries a Doctrine entity. List rows, tree nodes, archived pages, search hits, AI sources and "Used in" entries are plain arrays:

| Data | Shape | Built by |
| --- | --- | --- |
| Index rows | `{id, name, slug, href}` | `WikiPageFactory::index()` |
| History rows | `{id, number, created, current}` | `WikiPageFactory::history()` |
| Search rows | `{id, title, space, excerpt, href}` | `WikiPageFactory::search()` |
| `tree` nodes | `{title, href, current, children}` | `WikiPageFactory::tree()` |
| `archived` | `{title, action: View\Action\|null}` | `WikiPageFactory::archived()` |
| `sources` | `{title, space, excerpt, href}` | `WikiPageFactory::hits()` |
| `used_in` | `{type, label, href, badge}` | `WikiPageFactory::usedIn()` |
| `comments` (1.16) | `{id, open, count, shortcut, toggle_href, close_href, comments_url, csrf_token, state_url, state_token, can, page_threads, form}` (null: no toggle, no panel) | `WikiCommentsPanel::panel()` |
| `comments_view` (1.16) | `{markdown, options}` (the read-only Milkdown mount of commenters) | `WikiManageController::viewPage()` |
| `new_page_dialog` (1.35) | `{form: FormView (WikiPageFormType, placement), editor_type}` on `page_view` and `space` (null / absent: no dialog, no menu) | `WikiManageController::viewPage()` / `space()` |
| `tree_ui` (1.35 additions) | nodes with `item_attr['data-wiki-new-child'] = '1'` on pages the user may create pages under; `actions_template` = `@NowoWikiBundle/manage/_tree_new_page_button.html.twig` | `WikiNewPageMenu::annotate()` |

Only pages the user may **view** (`WikiAction::View` on the page, 1.9 access model) reach the tree, the archived list, the search rows, the AI sources and the AI context; a hidden page also hides its sub-pages in the tree.

Header actions per page:

| Page | Actions (kind) |
| --- | --- |
| `index` | Ask the wiki (`custom`, opens the AI `<dialog>`), Back to dashboard (`prev`, with `dashboard_route`) |
| `space` | New page (`create`), Export Outline / Notion (`export`), Back to dashboard |
| `page_view` | Edit (`edit`), History (`view`), Archive (`delete` look, POST + CSRF behind a UiKit `Confirm`); 1.16: tab row *Page* (current) / *Edit* / *History* (`PageHeader::tabs`, `AreaNav`); 1.35 (`web_ui.new_page_dialog`): New page (`create`, `dialogId` `nowo-wiki-new-page-dialog`, no URL) |
| `page_edit` | Back link to the page (or the space); Save / Cancel in the form; 1.16: the tab row (*Edit* current) on an existing page |
| `page_history` | Back link; row actions Compare (`view`) and Restore (`restore`, POST + CSRF behind a `Confirm`) |
| `page_diff` | Back link to the history |
| `search`, `ai_ask` | Back to dashboard |

## Data blocks and partials

| Template | Blocks it fills | Partials |
| --- | --- | --- |
| every page | `nowo_ui_styles` (`{{ parent() }}` + `wiki.css` from the `nowo_wiki` asset package), `nowo_ui_scripts` (both empty with `web_ui.assets` off, 1.21); flashes come from the layout's `nowo_ui_flashes` (the controller translates them when it adds them) | — |
| `index` | `nowo_ui_list_row` (name links to the space, slug in `code`), `nowo_ui_modals` (AI dialog) | `manage/_ai_dialog.html.twig` (UiKit `_dialog`: header, body, footer) |
| `space` | `nowo_ui_scripts` (`{{ parent() }}` + `wiki.js`, with the request `csp_nonce`), `nowo_ui_show_sections` (sidebar + search, import, archived pages; 1.21 blocks `wiki_sidebar`, `wiki_space_intro`), `nowo_ui_modals` (1.21, `web_ui.import_export: dialog`) | 1.21: `manage/_import_export_dialog.html.twig`; `manage/_sidebar.html.twig`, `manage/_page_tree.html.twig`, `manage/_page_tree_item.html.twig`; UiKit `action` partial for Restore page |
| `page_view` | `nowo_ui_scripts` (`{{ parent() }}` + `wiki.js`; 1.16 + UiKit `js/nowo-ui-side-panel.js` with comments), `nowo_ui_page_header` (1.16: the UiKit header without its tabs, then the tab row; 1.21 block `wiki_page_header_extra` between them), `nowo_ui_content` (1.16: inside the UiKit side panel container with comments), `nowo_ui_show_sections` (sidebar + sanitized content + "Used in"; 1.21 block `wiki_sidebar`) | same sidebar partials; 1.16: `manage/_page_tabs.html.twig` (UiKit `tabs` at the secondary level + `side_panel_toggle`), `manage/_comments_panel.html.twig` (UiKit `side_panel`, body through `content_template`), `manage/_comments_panel_body.html.twig`, `manage/_comments_view.html.twig` (`nowo_milkdown_view()`); 1.29: `manage/_comments_toggle.html.twig` (the toggle), `manage/_page_header_compact.html.twig` (`web_ui.page_header.layout: compact`: UiKit `page_header` with `tabs_inline`, no tab row), blocks `wiki_page_body` / `wiki_page_after_body`, UiKit `rail_toggle` in `_sidebar.html.twig` (`web_ui.tree_rail`), UiKit `js/nowo-ui-scroll-region.js` (always), `js/nowo-ui-rail.js` and `js/nowo-ui-layout-state.js` (in `nowo_ui_styles`, when needed); 1.33: the layout (aside, main column, card frame, page head) is `manage/_read_scaffold.html.twig`, shared with the edit form's read layout |
| `page_edit` | `nowo_ui_scripts` (`{{ parent() }}` + the TiptapEditorBundle script, with the request attribute `csp_nonce` when set; 1.16 + the side panel script and `wiki.js` with comments); `nowo_ui_page_header` and `nowo_ui_content` as `page_view`; `nowo_ui_form_body` keeps the UiKit child loop; 1.33 (`web_ui.edit_view.layout: read`): `nowo_ui_content` draws the page view layout (`manage/_read_scaffold.html.twig`) with block `wiki_sidebar` and block `wiki_page_form_body` (the whole form; default the UiKit form, `{{ parent() }}`) in `.nowo-wiki-edit-body` | 1.16: `manage/_page_tabs.html.twig`, `manage/_comments_panel.html.twig`; 1.29: `manage/_page_header_compact.html.twig` (`web_ui.page_header.layout: compact`) |
| `page_history` | `nowo_ui_list_row` (current revision badge; 1.16: UiKit `_avatar`, `xs`, decorative, before a real author name only; 1.34: host member badges and the write provenance after the author name) | `manage/_member_label_suffix.html.twig` (block `member_label_suffix`; also after comment authors in `manage/_comments_panel_body.html.twig`) |
| `page_diff` | `nowo_ui_show_sections` (diff lines in a `code_block`) | — |
| `search` | `nowo_ui_list_row` (title links to the page, space badge, excerpt) | — |
| `ai_ask` | `nowo_ui_form_body` (error, `{{ parent() }}`, help), `nowo_ui_form_aside` (answer and sources) | — |

Bundle-specific markup uses UiKit theme slots only (`card`, `card_header`, `card_title`, `card_intro`, `card_body`, `form`, `form_actions`, `btn_primary`, `btn_secondary`, `muted`, `code`, `code_block`, `badge_*`, `flash_warning`, `table_cell`) plus the semantic `nowo-wiki-*` hooks styled by `wiki.css` with the `--nowo-ui-*` tokens.

The page tree is the one bundle partial meant for overriding: override `templates/bundles/NowoWikiBundle/manage/_page_tree_item.html.twig` to change one item (variables `node` = `{title, href, current, children}`, `depth`).

An override under `templates/bundles/NowoWikiBundle/` can extend the original template through the `@!NowoWikiBundle` namespace (1.12; the bundle views without the app overrides), e.g. `{% extends '@!NowoWikiBundle/manage/_page_tree_item.html.twig' %}`. The Symfony bundle-name alias `@!Wiki` points at the same files.

## Layout

The scaffolds extend `page.layout ?? nowo_ui_layout.admin`:

- `nowo_wiki.web_ui.layout_template: null` (default): `nowo_ui_kit.layouts.admin` — set it once for every UiKit bundle.
- A layout that renders the UiKit canonical blocks (`nowo_ui_page_header`, `nowo_ui_flashes`, `nowo_ui_content`, `nowo_ui_modals`, `nowo_ui_styles`, `nowo_ui_scripts`, `nowo_ui_document_title`): used as is.
- A pre-1.10 layout (renders `{% block body %}` / `stylesheets` / `javascripts` only): wrapped in the **deprecated** bridge `@NowoWikiBundle/manage/_legacy_layout.html.twig` with a deprecation notice (removed in 2.0).

## Overriding one page

Prefer theming UiKit (`templates/bundles/NowoUiKitBundle/page/*.html.twig`, `nowo_ui_kit.theme`) over overriding wiki pages. For a genuinely wiki-specific change, point `nowo_wiki.web_ui.templates.<id>` at a thin template that extends the bundle page and overrides one block:

```twig
{# templates/wiki/page_view.html.twig, nowo_wiki.web_ui.templates.page_view #}
{% extends '@NowoWikiBundle/manage/page_view.html.twig' %}

{% block nowo_ui_show_after %}
    <p class="app-wiki-footer">Questions? #docs on chat.</p>
{% endblock %}
```

## Unique pages

None: every wiki page extends a UiKit scaffold.

## Contract test

`tests/Integration/Contract/PageContractTest.php` extends `Nowo\UiKitBundle\Test\PageContractTestCase` and renders every page (21 fixtures from `tests/Support/PageFixtures.php`, built with `WikiPageFactory` and the real form types) against the UiKit stub host layout and theme, for `none`, `bootstrap5` and `tailwind`, with the strict missing-translation rule. It also checks the page tree, the 1.16 tab row, comments toggle and panel (open, closed, edit view) and history avatars, the sanitized content, the AI `<dialog>`, the confirm contract of Restore / Archive and the CSP nonce of the editor script.

## New page dialog and tree menu (1.35)

With `web_ui.new_page_dialog: true`:

| Template | Where | What |
| --- | --- | --- |
| `manage/_new_page_dialog.html.twig` | block `nowo_ui_modals` of `page_view` and `space` | The dialog (UiKit `dialog`, id `nowo-wiki-new-page-dialog`, wide, no backdrop close) with the new-page form (`form_start` / `form_row` loop / `form_end`, id `nowo-wiki-new-page-form`), the context line (`data-wiki-new-page-context`, `data-above` / `data-below` / `data-child` sentences with `%title%`), Cancel and Create; with `with_menu` the tree menu (`role="menu"`, id `nowo-wiki-new-page-menu`, three `role="menuitem"` buttons `data-wiki-new-page-choice="above|below|child"`). Variables: `new_page_dialog`, `with_menu`, `bundle`, `nowo_ui_page_type` |
| `manage/_tree_new_page_button.html.twig` | UiKit `tree` `actions_template` (passed by `_sidebar.html.twig` from `tree_ui.actions_template`) | The «…» button of a row (`data-wiki-new-page-more`, `tabindex="-1"`, `aria-haspopup="menu"`, hidden until wiki.js finds a choice). Variable: `node` |

`nowo_ui_scripts` of `page_view` and `space` add the TiptapEditorBundle script when the dialog is on and the editor is
Tiptap. Hosts that replace the blocks keep the markers (`data-wiki-new-page-*`) for `wiki.js`.
