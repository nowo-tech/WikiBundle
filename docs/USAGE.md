# Usage — WikiBundle

## Table of contents

- [Manage UI routes (defaults)](#manage-ui-routes-defaults)
- [Import / export](#import--export)
- [Symfony AI](#symfony-ai)
- [Search](#search)
- [SearchKit provider (1.44)](#searchkit-provider-144)
- [Application integration](#application-integration)
- [Using it from a DDD / hexagonal application](#using-it-from-a-ddd--hexagonal-application)
- [Pagination and list limits (REQ-PERF-001)](#pagination-and-list-limits-req-perf-001)
- [Space lifecycle: rename, archive, purge (1.50)](#space-lifecycle-rename-archive-purge-150)
- [Creating and erasing spaces by id (1.55)](#creating-and-erasing-spaces-by-id-155)
- [Read ports and page access by id (1.51)](#read-ports-and-page-access-by-id-151)
- [Space icon (1.32)](#space-icon-132)
- [Icon picker, emoji icons and the tree menu (1.39)](#icon-picker-emoji-icons-and-the-tree-menu-139)
- [New page dialog and tree menu (1.35)](#new-page-dialog-and-tree-menu-135)
- [Member badges and write provenance](#member-badges-and-write-provenance)
- [Twig overrides (REQ-TWIG-001)](#twig-overrides-req-twig-001)
- [Theming: classes, tokens and blocks (REQ-UI-003)](#theming-classes-tokens-and-blocks-req-ui-003)
- [Demo smoke (REQ-TEST-011)](#demo-smoke-req-test-011)
- [Translations (REQ-I18N-001)](#translations-req-i18n-001)
- [Events](#events)
- [Links to host objects (1.9)](#links-to-host-objects-19)
- [Attachments (1.9)](#attachments-19)
- [Markdown pages (1.11)](#markdown-pages-111)
- [Milkdown editor (1.13)](#milkdown-editor-113)
- [Favourites, recents and backlinks (1.45)](#favourites-recents-and-backlinks-145)
- [Comments (1.16)](#comments-116)
- [Browser console](#browser-console)

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

## SearchKit provider (1.44)

Optional: the wiki's pages in SearchKitBundle's "search everywhere" — the header search, the palette, the results page
and the recents — **through the wiki's own read rules**. 1.46: built on SearchKit's provider base, so it needs
`nowo-tech/search-kit-bundle` **>= 1.10.1** (a suggested package, PHP >= 8.3; a security requirement, see
[SECURITY](SECURITY.md#searchkit-provider-144)) with its bundle registered; without it, or with an older one (1.8 to
1.10.0 included), nothing is registered. Off by default:

```yaml
nowo_wiki:
    search_kit_provider: true          # literal boolean (shortcut for enabled: true)
    # or, with every option:
    search_kit_provider:
        enabled: true
        priority: 40                   # tag priority: SearchKit lists its groups highest first
        candidates: 200                # rows read and checked per search, title matches first (10–1000)
        scope: owner                   # a SearchKit scope id = a space owner id (owner) or a space slug (slug)
        viewer_resolver: ~             # service id of Search\WikiSearchViewerResolverInterface
        user_provider: security.user_providers   # without viewer_resolver: loads the viewer by identifier
        icon: ~                        # UX Icons name of the rows; null: nowo_search_kit.icons.wiki
        tenant: current                # 1.46: current (only the current tenant's spaces) | all
        tenant_resolver: ~             # 1.46: service id of Search\WikiSearchTenantResolverInterface (the current tenant)
        sensitive: false               # 1.46: true = page titles are sensitive (see below)
```

The group can also be switched with SearchKit's own `nowo_search_kit.groups.wiki.enabled` (1.9).

What it does, for a viewer (SearchKit's `SearchQuery::$userIdentifier`):

| Step | Rule |
|---|---|
| Viewer | `viewer_resolver`, else the Symfony user provider; the user must carry exactly the identifier. None: no group |
| Role gate | `security.access_checker` `canList()` for the viewer (the search page's `list` feature; 1.44: the default checker decides another user than the session's on that user's roles) |
| Spaces | The viewer's spaces from the tenant / team resolver, each with `View` from the space checker; narrowed by the SearchKit scope and the `space:` filter; 1.46: **the current tenant only** (below); then the host's `nowo_wiki.search_kit.spaces` listeners (below). Only these reach the SQL |
| Current tenant (1.46) | As for every quick-access list (favourites, recents, backlinks; owner decision of 2026-11-19), the palette shows the current tenant only: a space is searched when its `LinkPicker\WikiLinkTenantResolverInterface` key equals the current tenant of `Search\WikiSearchTenantResolverInterface` (`tenant_resolver`). No current tenant: no group. `tenant: all` searches every readable space instead (the 1.44 / 1.45 behaviour). SearchKit's tenant is the current tenant key (`*` with `all`) |
| Rows | Live pages only (archived and trashed pages are left out; the wiki has no draft state), the typed text matched literally as one phrase (escaped `LIKE … ESCAPE '!'`, case-insensitive) on the title or the plain text of the current revision — never the Markdown source, never markup alone (`WikiSearchService::plainTextHit()`, the rule of the REST API's search) |
| Pages | `View` on the page's space and on the page **and every ancestor** (`WikiPageVisibility`, the page view's rule), on a fresh read of the candidates and their ancestors |
| Host rules | SearchKit's `nowo_search_kit.item_visibility` event (subjects of type `wiki`, the page id), after the wiki's checks; deny wins |
| Group | Key and row kind `wiki`, label `wiki.search_kit.group`. Rows: page title; the page view URL (`routes.page_view` through the router); subtitle = space name · readable ancestors · the wiki search's plain-text excerpt when the title does not match. `score` (title before body) and `date` (last change) |
| Order and window | Title matches first, then the latest change: at most `candidates` rows are read and checked per search (results pages included) |
| Total | The visible rows within that window; exact (`exactTotal`) unless the visible rows fill it |
| See all | The wiki's search page with the query, only where it answers the same question: the one space a `space:` filter (or a `scope: slug` scope) names, or — with `search.scope: all` and neither scope nor filter — the page across spaces. An owner scope, a negated or a second space: no link |
| Results page | `PaginatedSearchProviderInterface`: pages of the same visible list |
| Recents | `SearchReferenceResolverInterface`: a `wiki` reference on the page view route (or a `tenant_paths` copy) whose `spaceSlug` / `pageSlug` name a live page in a space the viewer may read **now**; that page is read again through every rule above and kept only with the same URL. Anything else is null |
| Filters | `space:<slug>` and `-space:<slug>`; a forbidden and an unknown space answer the same. Any other filter key: no group (it would widen the rows) |
| By identifier | `IdentifierAwareSearchProviderInterface`: `searchFor()` runs it, decided for that identifier only |

Excerpts are the plain text the wiki's own search shows (`content_text`, or the text of the viewer-neutral HTML cache):
raw HTML of the source is text there too, and SearchKit prints it as text and marks the match itself. Hosts that wrap
the rules in their own provider use the framework-free port `Nowo\WikiBundle\Search\WikiReadablePageFinder`
(`spaces()`, `find()`, `page()`), registered with the option.

**Sensitive titles.** With `sensitive: true` the group is off while SearchKit keeps recents in the browser without
revalidating them (`nowo_search_kit.recent.enabled: true` and `recent.revalidate: false`): titles of restricted pages
then never reach the browser's storage. Default `false`, as before 1.46.

**Cost.** One query reads at most `candidates` rows (each with its revision's text) on every keystroke SearchKit sends,
and again for each results page; keep the default of 200. With several Symfony user providers, set `user_provider` to the
firewall's own provider rather than the chain. Synonym alternatives of SearchKit (`SearchQuery::terms()`) are used for
ranking only; the SQL matches the typed text.

### Current tenant (1.46)

Default resolver (`Search\WikiTenantPathsSearchTenantResolver`): without `tenant_paths` every space is one tenant, so
nothing changes. With `tenant_paths` the wiki cannot tell which tenant a SearchKit request belongs to (SearchKit's
endpoint is not a tenant route), so the group finds nothing until you alias your own resolver — use the keys of your
`WikiLinkTenantResolverInterface` — or set `tenant: all`. A host that aliased its own `WikiLinkTenantResolverInterface`
must alias this port too:

```php
use Nowo\WikiBundle\Search\WikiSearchTenantResolverInterface;

final readonly class CurrentOrganisationTenant implements WikiSearchTenantResolverInterface
{
    public function __construct(private CurrentOrganisation $organisations)   // your request-scoped service
    {
    }

    public function currentTenant(string $userIdentifier): ?string
    {
        return $this->organisations->current()?->id();   // the same key as your WikiLinkTenantResolverInterface
    }
}
```

```yaml
nowo_wiki:
    search_kit_provider:
        enabled: true
        tenant_resolver: App\Wiki\CurrentOrganisationTenant
```

### Several current tenants (1.47)

When people work in several wiki tenants at once — their organisation's wiki, one wiki per project, their personal
wiki, a general wiki — one current key would hide most of them. Implement `Search\WikiSearchTenantsResolverInterface`
instead and return every tenant key the person may search from this request; the group searches the **union** of
those tenants:

```php
use Nowo\WikiBundle\Search\WikiSearchTenantsResolverInterface;

final readonly class CurrentWikiTenants implements WikiSearchTenantsResolverInterface
{
    public function __construct(private CurrentOrganisation $organisations, private ProjectAccess $projects)
    {
    }

    public function currentTenants(string $userIdentifier): array
    {
        $organisation = $this->organisations->current();   // the organisation chosen in the header
        if ($organisation === null) {
            return [];                                      // no tenant context: nothing is searched
        }
        $keys = ['organisation:' . $organisation->id(), 'user:' . $userIdentifier, 'general'];
        foreach ($this->projects->visibleIn($organisation, $userIdentifier) as $project) {
            $keys[] = 'project:' . $project->id();
        }

        return $keys;                                       // the keys your WikiLinkTenantResolverInterface gives the spaces
    }
}
```

```yaml
nowo_wiki:
    search_kit_provider:
        enabled: true
        tenant_resolver: App\Wiki\CurrentWikiTenants   # a list port or the single-key port, either works
```

Rules:

- Keys are compared exactly (`===`): no case or whitespace folding. `*` and empty strings are never keys (dropped),
  duplicates are ignored, keys containing control characters or starting with `tenants:` are dropped (1.47.1), and more than 500 distinct keys (`WikiPageSearchDefinition::MAX_TENANTS`) find nothing. An empty list
  finds nothing.
- Nothing widens the list: a `space:` filter, a SearchKit scope or a recent naming a space outside these tenants finds
  nothing.
- The SearchKit tenant (SearchKit's item visibility event, the recents check) is the key itself for one key, and
  `tenants:` followed by the SHA-256 of the JSON array of the sorted keys for several (1.47.1)
  (`WikiPageSearchDefinition::tenantId()`): stable for the same keys in any order. The keys themselves are in the spaces
  event (`WikiSearchSpacesEvent::$tenants`).
- To narrow further per space (a project whose wiki feature is off), use the spaces event below; it can only exclude.

The single-key port of 1.46 (`WikiSearchTenantResolverInterface::currentTenant()`) keeps working: it is one list of one
key. The default resolver implements both.

### Host rules (1.46)

Two hooks, both narrowing only (they can hide rows, never add one), both applied before the window and the total, so a
hidden row is never shown, counted or hinted at (the totals and the "see all" link are those of the remaining rows):

- **Per space, before the SQL:** `Nowo\WikiBundle\Event\WikiEvents::SEARCH_KIT_SPACES` (`nowo_wiki.search_kit.spaces`),
  a `WikiSearchSpacesEvent` with the viewer's identifier, the scope, the locale and the spaces the wiki would read
  (`spaces()`: each a `WikiSearchSpace` with a `WikiSpaceSnapshot` and its tenant key — the link picker's
  `WikiLinkTenantResolverInterface`). Call `exclude($spaceId)` or `excludeAll()`. An exclusion wins whatever the listener
  order; the event cannot be stopped. Dispatched for searches, results pages and recents of the SearchKit group only (not
  by the wiki's own search page). `search.excluded_spaces` does **not** apply to the SearchKit group (it only removes the
  wiki's search page there): exclude those spaces here. Use it for rules per space or tenant, e.g. leaving out the wikis of a project whose
  wiki feature is off.
- **Per page, after the wiki's checks:** SearchKit's `nowo_search_kit.item_visibility` (`SearchItemVisibilityEvent`,
  group `wiki`, subject type `wiki`, subject id = the page id, tenant = the current tenant key or `*`), once per search page with every candidate;
  `deny()` wins over any `allow()`.

```php
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Event\WikiSearchSpacesEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: WikiEvents::SEARCH_KIT_SPACES)]
final readonly class HideWikisOfDisabledProjects
{
    public function __construct(private ProjectFeatures $features)
    {
    }

    public function __invoke(WikiSearchSpacesEvent $event): void
    {
        foreach ($event->spaces() as $space) {
            if (!$this->features->wikiEnabled($space->space->ownerScopeId)) {
                $event->exclude($space->space->id);
            }
        }
    }
}
```

Without a listener the results are the wiki's own. Listeners must keep no state between calls (FrankenPHP worker mode).

### Beacon: replacing WikiPageSearchProvider

Beacon's `App\Search\Provider\WikiPageSearchProvider` re-implements these rules. With WikiBundle 1.46 and SearchKit
1.9 installed, `config/packages/nowo_wiki.yaml`:

```yaml
nowo_wiki:
    # Spec 240 / KIT-85: the wiki's own SearchKit provider (group "wiki", the slot of WikiPageSearchProvider).
    search_kit_provider:
        enabled: true
        priority: 74                   # WikiPageSearchProvider's #[AsTaggedItem(priority: 74)]
        scope: owner                   # the project scope chip = the project UUID = its wikis' owner id
        # viewer_resolver: ~           # default: security.user_providers (app_user_provider, by e-mail = getUserIdentifier())
```

Then delete `src/Search/Provider/WikiPageSearchProvider.php` (and its `search.matched_page_text` / `search.see_all_wiki`
messages if nothing else uses them) and point `tests/Functional/Search/TaskWikiSearchTest.php` at
`Nowo\WikiBundle\Search\SearchKit\WikiPageSearchKitProvider`. What the wiki now decides, and what to keep in Beacon:

- **Spaces** come from `ProjectWikiMembershipResolver` (the projects with `project.wiki.view`, the organizations with
  `organization.wiki.view`, the person's own wikis) and `BeaconWikiAccessChecker`'s `View` (a restricted wiki only with a
  grant). One difference: the checker lets the owner's managers read an **archived wiki**, which the old provider left out
  of the search (`WikiCatalog::listedOnly()`); keep that with a `space_access_resolver` (or `space_reach` port) that leaves
  archived wikis out of the listed spaces, or accept that managers find them. The **general wiki** (`general`, `product.wiki`) is not among those owner ids today: add `general` to
  `getTeamIdsForUser()` for a user with `product.wiki` if it must stay searchable (the old provider searched it outside a
  project scope).
- **Scoped to a project**, the provider searches only the wikis owned by that project UUID, as before.
- The subtitle names the wiki's space name, not the project or organization name the old provider looked up.
- The MySQL FULLTEXT path of the old provider is not used: the wiki matches with `LIKE` on the bounded candidate window
  (`candidates`).

## Application integration

1. Implement `WikiAccessPolicyInterface` (or use role-based defaults).
2. For team spaces, register `WikiTeamMembershipSourceInterface`.
3. Set **`nowo_ui_kit.layouts.admin`** (once for every UiKit bundle) to your project layout or a one-file bridge that renders the UiKit canonical blocks — every wiki page extends a UiKit scaffold that extends it and stacks `wiki.css` with **`{{ parent() }}`** (REQ-UI-001 / REQ-UI-003). `nowo_wiki.web_ui.layout_template` overrides it for the wiki only. Do not fork wiki pages; see [TEMPLATES.md](TEMPLATES.md).
4. Create the spaces per team with `WikiSpaceOperationsInterface::createSpaceFrom()` (1.55; or expose a create UI in
   your app that calls it): [Creating and erasing spaces by id](#creating-and-erasing-spaces-by-id-155).

## Using it from a DDD / hexagonal application

WikiBundle is a **bounded context with its own model and persistence** (spaces, pages, revisions, links, attachments,
comments, trash; Doctrine ORM) plus its own UI and REST API. Treat it as a separate "wiki" context: your domain refers
to wiki pages by id, and the wiki refers to your objects by string ids.

**Keep your domain free of the bundle**

- Do not hold `WikiPage` / `WikiSpace` entities in your aggregates; store the page or space id (UUID string).
- Spaces are owned by `owner_scope_type` (`team` | `user`) and an `owner_scope_id` string — no relation to your
  team or user tables.
- Link wiki pages to your objects with `WikiPageLinkService::link($page, $objectType, $objectId)`; the wiki only keeps
  the strings and asks your `WikiObjectLinkSourceInterface` what the viewer may see.
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
| Who may use the wiki | `Security\WikiAccessPolicyInterface` | `security.access_checker` |
| Per-space / per-page rights | `Security\WikiSpaceAccessPolicyInterface` | service alias |
| Team membership | `Security\WikiTeamMembershipSourceInterface` | `team_membership_resolver` |
| Your objects shown on a page | `Link\WikiObjectLinkSourceInterface` | autoconfigured |
| Avatars | `Avatar\WikiUserAvatarSourceInterface` | service alias |
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

**The DDD host path (1.36, opt-in)** — see [ARCHITECTURE](ARCHITECTURE.md) for the whole picture:

        nowo_wiki:
            user_class: App\Security\User          # any class with getId(); no Doctrine entity needed with user_reference: id
            user_directory: app.wiki_user_directory   # User\WikiUserDirectoryInterface: names and avatars by id
            database:
                mapping_type: xml               # the wiki entities from Resources/config/doctrine-xml
                user_reference: id              # host users stored by id (no foreign key, no user table needed)

- Implement the framework-free twins of the ports above (`WikiAccessPolicyInterface`, `WikiSpaceAccessPolicyInterface`,
  `WikiTeamMembershipSourceInterface`, `WikiObjectLinkSourceInterface`, ...): they take `ValueObject\WikiUserRef` and
  snapshots, never a Symfony user, a request or a wiki entity. Same configuration keys.
- Call the wiki through `WikiPageOperationsInterface`, `WikiSpaceOperationsInterface` and
  `WikiPageLinkOperationsInterface` (ids in, snapshots out); `createPageAt()` / `createPageNextTo()` place a new page in
  the tree (1.35 "create at a position", needs `tree.enabled`). 1.55: create spaces with `createSpaceFrom()`, erase
  them with `WikiSpaceLifecycleInterface::eraseSpace()` and read comments by id:
  [Creating and erasing spaces by id](#creating-and-erasing-spaces-by-id-155).
- List, count, export, link back and search through `Query\WikiSpaceQueryInterface` and `Query\WikiPageQueryInterface`,
  and decide whether a person may read a page with `Security\WikiPageAccessInterface` (1.51, ids in, snapshots out):
  [Read ports and page access by id](#read-ports-and-page-access-by-id-151).
- The 1.34 badge and provenance hooks (`WikiMemberBadgeProviderInterface`, `WikiWriteProvenanceProviderInterface`)
  already take and return value objects: a badge provider is asked about `WikiMemberRef(<user_class>, <id>)`.
- Listen to the events through their id accessors: `$event->pageId()`, `$event->revisionSnapshot()->revisionNumber`,
  `$event->actorId()` / `$event->actorRef()`.

**Known limitations**

- Without `user_reference: id`, revision authors, link creators, uploaders, comment authors and reactions are Doctrine
  `ManyToOne` relations to `user_class` (a Doctrine entity in the wiki's entity manager).
- Deleting a user row keeps their page revisions without an author (1.26, `ON DELETE SET NULL`) and their comments
  without an author; their reactions go with them (with `user_reference: id` nothing is deleted with the user).
- The event payloads keep their entity members next to the id accessors (not deprecated: a listener that changes a
  page still needs them).
- `WikiCommentService` is final and has no interface (it is the UI / REST comment engine; call its privacy methods
  from your adapter).
- With the attribute mapping the bundle maps its entities in the default entity manager; with another
  `database.entity_manager`, add the `WikiBundle` mapping to that manager yourself or use `mapping_type: xml`, which
  maps it there.
- A `ManyToOne(WikiPage)` of yours is set with `getReference()` on your own manager (never with an instance the wiki
  returned); after a trash purge it points at a page that no longer exists. Store the page id instead of a relation, or
  listen to `nowo_wiki.trash.purged` (`WikiTrashEvent::$pageIds`, after the commit) and clear your rows that point at
  those pages ([UPGRADING › To 1.40.2](UPGRADING.md#to-1402) has an example).

## Pagination and list limits (REQ-PERF-001)

List pages of the manage UI are bounded by default (`nowo_wiki.pagination`, see [CONFIGURATION.md](CONFIGURATION.md#pagination-req-perf-001)):

| Page | Limit | Navigation |
| ---- | ----- | ---------- |
| Wiki index (spaces) | `spaces_per_page` (24) | `?page=N`, UiKit pagination |
| Page history (revisions) | `revisions_per_page` (25), one extra row is read to link the last "Compare" button; the total is the current revision number (history is append-only) | `?page=N`, UiKit pagination |
| Sidebar page tree (space + page view) | `max_tree_pages` (500) pages loaded with one query | a notice links to search when more pages exist |
| Search results | 25 hits | — |

Each list is one query (no N+1): spaces through `WikiSpaceRepositoryInterface::findAccessible()`, the tree through `findActiveBySpace($space, $max + 1)`, history through `findByPage($page, $limit, $offset)`. Exports (`/export`, `wiki:export`) are synchronous, write to a temp directory and stream the ZIP; they are bounded by PHP `max_execution_time` — use the `wiki:export` console command for very large spaces.

## Space lifecycle: rename, archive, purge (1.50)

`Nowo\WikiBundle\Space\WikiSpaceLifecycleInterface` (public service) renames, archives, brings back and purges a
space. Call it instead of writing the wiki's tables yourself: it keeps every wiki table consistent, runs on the wiki's
own transaction runner, on the wiki's private entity manager: your entity manager is not involved at all.

```php
use Nowo\WikiBundle\Space\WikiSpaceLifecycleInterface;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;
use Nowo\WikiBundle\ValueObject\WikiUserRef;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class WikiAdminController extends AbstractController
{
    public function __construct(private WikiSpaceLifecycleInterface $spaces)
    {
    }

    public function rename(string $spaceId, string $name, ?string $slug, int $version): WikiSpaceSnapshot
    {
        // Name and/or slug (null keeps it); $version is the snapshot's `version` the form was built from.
        return $this->spaces->renameSpace($spaceId, $name, $slug, $this->actor(), $version);
    }

    public function archive(string $spaceId, int $version): WikiSpaceSnapshot
    {
        return $this->spaces->archiveSpace($spaceId, $this->actor(), $version);
    }

    public function unarchive(string $spaceId, int $version): WikiSpaceSnapshot
    {
        return $this->spaces->unarchiveSpace($spaceId, $this->actor(), $version);
    }

    public function purge(string $spaceId): void
    {
        // Only an archived space; `force: true` (your administrator flag) purges an active one (it is archived first).
        $this->spaces->purgeSpace($spaceId, $this->actor());
    }

    /** The person asking: the space checker and the access-check event receive it (`user()` is your user object). */
    private function actor(): WikiUserRef
    {
        $user = $this->getUser();

        return new WikiUserRef($user::class, (string) $user->getId(), $user->getUserIdentifier(), $user->getRoles(), $user);
    }
}
```

**Who may do it.** With an actor, the person must be able to open the space (otherwise
`WikiSpaceNotFoundException`, exactly as for a space that does not exist: a space of another tenant cannot be told
from a missing one) and hold `Manage` on it through your `WikiSpaceAccessPolicyInterface`; a listener of
`WikiEvents::SPACE_ACCESS_CHECK` (`WikiSpaceAccessCheckEvent`: `action`, `space`, `actor`, `isDefaultGranted()`,
`grant()`, `deny()`, a deny sticks) may narrow or widen that, for example to keep a default space from being archived.
A refusal is `WikiSpaceAccessDeniedException`. A null actor is a trusted call that checks nothing (a console command,
a message handler that already checked): pass the person whenever there is one.

**Rename.** Only an active space is renamed (`WikiSpaceStateException::ARCHIVED` otherwise, as the REST API answers
`WIKI_002` to a wiki update while it is archived): bring it back first. The slug stays unique per owner (`WikiSlugTakenException`; `WikiInvalidSlugException` for a slug that is
not lowercase letters, digits and single dashes, at most 120 characters — 1.55: or a slug a `tenant_paths`
requirement matches, see [Creating and erasing spaces by id](#creating-and-erasing-spaces-by-id-155)). Links between pages follow page ids and keep
working; URLs built with the old slug stop resolving, because the bundle keeps no slug history.

**Archive.** An archived space is read-only on every path that goes through the space checker — the bundle's pages,
tree, comments, attachments and the REST API (`403 WIKI_002`) — and the page service refuses creates (the space row is
read under its lock), saves and restores with `WikiPageArchivedException`. Trusted calls your code makes directly are
not refused: `convertToMarkdown()`, archiving or bringing back a page, the page mover, the trash, the comment service,
attachment uploads and the icon services; check `WikiSpaceSnapshot::isArchived()` before calling them. Only the
people who may manage the space can open it: for everyone else it disappears from lists, the tree, search, the link picker,
favourites, recents and the host-object link lists (`findByHostObject(s)`). It is reversible. The rule wraps whatever space checker you configured
(`WikiArchivedSpaceAccessChecker` decorates the bundle-side space checker). The REST API's wiki body reports
`archived: true` for it as well.

**Purge.** Irreversible. It deletes the pages with their revisions, named versions, comments and reactions, attachment
rows (their files are removed from the attachment storage after each commit), links to host objects, references from
and to the pages, favourites, visits, trash entries, co-editing state and the REST API's idempotency records of the
pages, then the space row. Search reads the live rows, so nothing of the space is found any more. It runs in batches of
100 pages, one transaction each; with `page_files.purge_event` each batch dispatches `WikiPageFilesPurgedEvent` for the
files its revisions referred to. Call it **outside any transaction**: inside one it throws `WikiSpacePurgeInTransactionException` (nothing written;
answer 409 without `Retry-After`), because each batch commits on its own before its files go and its events fire. The
first call marks the space as being purged (it stays archived; rename and
unarchive answer `WikiSpaceStateException`). A call that stops — a failure, or the `maxBatches` budget — leaves the
space so, and calling again resumes where it stopped. Two workers may run it at once: the one that deletes the space
row dispatches `SPACE_PURGED`; the other returns `finished: true` without it. Attachment files whose delete failed (it
is logged) or that a crash between a commit and the unlink left behind stay in the storage: run
`WikiAttachmentService::purgeStrayFiles()` from a nightly job (it lists the storage and removes the files no row
points to). From a queue:

```php
$result = $this->spaces->purgeSpace($spaceId, null, false, maxBatches: 20);
if (!$result->finished) {
    $this->bus->dispatch(new PurgeWikiSpace($spaceId)); // go on in another message
}
```

**Optimistic locking.** `WikiSpaceSnapshot::$version` grows with every rename, archive and unarchive. Pass it back as
`expectedVersion`: a change made meanwhile answers `WikiSpaceVersionConflictException` (nothing written; read again).
Null means last write wins. Archiving an archived space (or bringing back an active one) writes nothing, whatever the
version, so a retry after a lost answer is harmless.

**Conflicts (REQ-RUNTIME-003 rule 6).** A lock conflict surfaces as `Nowo\TransactionContract\TransientStorageFailure`:
nothing was written, answer `409` + `Retry-After` with `CONFLICT_001`. A purge that had already committed part of its
work throws `Nowo\TransactionDoctrine\WriteUnit\ConcurrencyConflict` with `committed: true`: answer `CONFLICT_002`
without `Retry-After`; the client reads again and calls the purge again. `ConflictCode::forFailure()` gives the code.

| Exception | Answer |
| --- | --- |
| `WikiSpaceNotFoundException` | 404 |
| `WikiSpaceAccessDeniedException` | 403 |
| `WikiSpaceVersionConflictException`, `WikiSpaceStateException`, `WikiSlugTakenException`, `WikiSpacePurgeInTransactionException` | 409 (no `Retry-After`) |
| `WikiInvalidSlugException`, `InvalidArgumentException` (empty or too long name, nothing to rename) | 422 |
| `TransientStorageFailure` | 409 + `Retry-After`, `CONFLICT_001` / `CONFLICT_002` |

**Events**, after the change committed: `WikiEvents::SPACE_RENAMED`, `SPACE_ARCHIVED`, `SPACE_UNARCHIVED`
(`WikiSpaceLifecycleEvent`: the space before and after, the actor) and `SPACE_PURGED` (`WikiSpacePurgedEvent`, once,
when the space row is gone), plus `ATTACHMENT_DELETED` for each purged attachment.

**REST API.** `PATCH /wikis/{wiki}`, `POST /wikis/{wiki}/archive` and `/unarchive` still go to your
`api.hooks.wiki_management` hook; call the service from it for the wiki's space (`$wiki->space?->getId()`). The purge
has no endpoint.

## Creating and erasing spaces by id (1.55)

Three operations let an application keep no wiki entity at all — create a space, erase it, and read the comments of a
page. Every write goes through the wiki's own transaction runner and connection, on its private entity manager (the
only mode since 2.0). Your rows that pointed at a
`WikiSpace` (grants, shares, a profile) keep a plain id column instead of a relation.

**Create a space** — `Service\WikiSpaceOperationsInterface::createSpaceFrom(WikiNewSpace $space): string` returns the
new space's id. The values are checked as the space settings are: the name trimmed, 1 to 255 characters
(`InvalidArgumentException`); a usable owner id (no leading or trailing whitespace, control or format characters, at
most 64 characters); the slug (`WikiInvalidSlugException`): lowercase letters and digits in segments joined by single
dashes, at most 120 characters — or, with `tenant_paths`, a slug of lowercase letters, digits and dashes that a tenant's
`slug_requirement` matches whole (`<owner uuid>--<name>`); the icon (`WikiInvalidIconException`): `<prefix>:<name>` with
a prefix of `space_icon`. The slug is unique per owner (exact owner id: `Team-a` and `team-a` are two owners): a taken
slug is `WikiSlugTakenException`, also when another request created it at the same time (the unique key decides; no
duplicate, no 500, your entity manager stays open and its pending changes are neither flushed nor dropped). Inside a
transaction you opened, the insert joins it. Beacon's catalog (spec 209 FR-021):

```php
use App\Wiki\Domain\ValueObject\WikiSlug;
use Nowo\WikiBundle\Service\WikiSlugTakenException;
use Nowo\WikiBundle\Service\WikiSpaceOperationsInterface;
use Nowo\WikiBundle\ValueObject\WikiNewSpace;

final readonly class WikiCatalog
{
    public function __construct(private WikiSpaceOperationsInterface $wikiSpaces, private EntityManagerInterface $entityManager)
    {
    }

    /** @return string the new wiki's space id */
    public function create(WikiOwner $owner, string $name, string $slugName, ?string $description, ?string $icon = null): string
    {
        // Throws WikiSlugTakenException when the owner already has that slug (also when created meanwhile): show
        // "slug taken" on the form, as WikiCreator::problem() does before.
        $spaceId = $this->wikiSpaces->createSpaceFrom(new WikiNewSpace(WikiSlug::compose($owner->scopeId(), $slugName), $name, $owner->scopeId(), icon: $icon));
        if (null !== $description && '' !== $description) {
            $profile = new WikiSpaceProfile($spaceId); // Beacon's row: a plain space id column
            $profile->setDescription($description);
            $this->entityManager->persist($profile);
            $this->entityManager->flush();
        }

        return $spaceId;
    }
}
```

`createSpace($name, $scope, $ownerId, $slug)` (1.36) goes through the same unit and returns the snapshot. Read a space
back with `findSpace($id)`, list an owner's spaces with
`WikiSpaceQueryInterface::findSpaces(WikiSpaceCriteria::forOwners(WikiSpaceOwnerScope::Team, [$ownerId]))`.

**Erase a space (account erasure)** — `Space\WikiSpaceLifecycleInterface::eraseSpace(string $spaceId, ?int $maxBatches
= null): WikiSpacePurgeResult` deletes the space for good: an active space is archived first (hidden from search,
navigation, favourites and recents from that first unit on), then its pages, revisions, named versions, comments and
reactions, attachments (rows, then their files through your attachment storage after each commit), links, references,
favourites, visits, trash entries, co-editing state and REST idempotency records go, in batches of 100 pages, one
transaction each, and the space row last. The wiki's search keeps no index of its own (it reads the page rows), so
nothing of the space is found once its pages are gone. It is the forced, trusted `purgeSpace()`, and idempotent: a
space already gone answers `finished: true`, `found: false` (`alreadyGone()`) with no page and no batch (never
`WikiSpaceNotFoundException`), so a retried erasure or a replayed message has nothing to catch.

Since 1.55.1 (audit ZAP):

- **The id must be a lowercase UUID**, exactly as the wiki gave it (`InvalidArgumentException` otherwise). A look-alike
  id (upper-cased, padded, truncated) is refused instead of being answered "already gone" while the space stays.
- **Pass the expected owner on every erasure**: `eraseSpace($id, null, WikiSpaceOwnerScope::Team, $ownerId)`. Type and id
  are compared byte for byte under the space lock before anything is written; another owner is
  `WikiSpaceOwnerMismatchException`. A wrong id from a stale mapping or another organisation's list then erases nothing.
- **Files.** `finished` means the rows are gone. `filesFailed` counts attachment files whose delete failed (logged),
  `filesTruncated` the batches whose `WikiPageFilesPurgedEvent` list was cut by `page_files.max_purge_files`. When
  `needsFileSweep()` is true, run `WikiAttachmentService::purgeStrayFiles()` (attachments) and your own page-file sweep
  (files you store for the pages) before reporting the erasure complete. A process that dies between a batch commit and
  its file deletes cannot report them, so keep `purgeStrayFiles()` in a nightly job too.

**Transaction: the erasure runs in the wiki's own units, after your erasure committed.** This is the recommended split:

1. **Inside your erasure's transaction**, write only your own rows: mark your profile of each wiki as archived (your
   lists and your space checker hide it at once, and the mark rolls back with the erasure), delete your grants and
   shares by space id. Do not purge there: `eraseSpace()` throws `WikiSpacePurgeInTransactionException` inside a
   transaction, before writing anything, because each batch must commit before its files are deleted and its events
   are dispatched (a purge inside your transaction would delete files of rows your rollback brings back).
2. **After the commit** (your "erasure committed" event, or a message), call `eraseSpace()` for each space id. A call
   that stops (a lock conflict, a crash, the `maxBatches` budget) leaves the space archived and marked as being purged
   — hidden — and the next call resumes where it stopped.

Archiving the wiki's own space inside your transaction is not needed for that (your mark hides it, and the purge
archives first). It is still possible — `archiveSpace($id)` joins a transaction you opened as a savepoint, in both
entity manager modes, and rolls back with it — but its `SPACE_ARCHIVED` event is dispatched when the wiki's unit ends,
before your commit: do not combine it with listeners that act on the archive. Beacon's eraser and the listener after
the commit:

```php
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Query\WikiSpaceCriteria;
use Nowo\WikiBundle\Query\WikiSpaceQueryInterface;
use Nowo\WikiBundle\Space\WikiSpaceLifecycleInterface;

// UserKit runs the erasers in one transaction: Beacon's rows only.
final readonly class PersonalWikiEraser implements PersonalDataEraserInterface
{
    public function erase(ErasureRequest $request): void
    {
        foreach ($this->spaceIds(PersonalWikiOwner::scopeIdOf($request->user)) as $spaceId) {
            $this->profiles->markArchived($spaceId);              // WikiSpaceProfile.archivedAt, by space id
            $this->grants->deleteBySpaceId($spaceId);             // DQL on Beacon's WikiSpaceGrant / WikiSpaceShare
        }
    }
}

// After UserKit committed the erasure (AccountErasedEvent): the wiki's own units.
#[AsEventListener]
final readonly class PersonalWikiPurgeListener
{
    public function __construct(private WikiSpaceQueryInterface $spaces, private WikiSpaceLifecycleInterface $lifecycle)
    {
    }

    public function __invoke(AccountErasedEvent $event): void
    {
        $owner = PersonalWikiOwner::scopeIdOf($this->users->get($event->userId));
        foreach ($this->spaces->findSpaces(WikiSpaceCriteria::forOwners(WikiSpaceOwnerScope::Team, [$owner]), 0, 500) as $space) {
            // Idempotent; the expected owner makes a wrong id erase nothing.
            $result = $this->lifecycle->eraseSpace($space->id, null, WikiSpaceOwnerScope::Team, $owner);
            if ($result->needsFileSweep()) {
                $this->bus->dispatch(new SweepWikiFiles()); // WikiAttachmentService::purgeStrayFiles() + page files
            }
            $this->profiles->deleteBySpaceId($space->id);
        }
    }
}
```

A lock conflict before the call committed anything is `TransientStorageFailure` (retry-safe, `CONFLICT_001`); after
part of it committed, `ConcurrencyConflict` with `committed: true` (`CONFLICT_002`: call again to resume). From a queue,
pass `maxBatches` and dispatch the next message while `finished` is false (as for `purgeSpace()`).

**Read a page's comments by id** — `Service\WikiPageOperationsInterface` (read-only, no lock, no access decision: ask
your space checker as for the page):

| Method | Answers |
|---|---|
| `findPage(string $pageId): ?WikiPageSnapshot` | the page (1.36) |
| `findPageRevision(string $pageId, int $revisionNumber): ?WikiRevisionSnapshot` | a revision by number; `1` is the first save, its `authorId` the page's author |
| `findCommentThread(string $threadId): ?WikiCommentThreadSnapshot` | `pageId`, `createdById`, `pageLevel`, `resolvedAt`, `resolvedById`, `isResolved()` |
| `findComment(string $commentId): ?WikiCommentSnapshot` | `threadId`, `pageId`, `authorId`, `position`, `editedAt`, `deletedAt`, `isDeleted()` |
| `findThreadComments(string $threadId): list<WikiCommentSnapshot>` | the comments of the thread in order, deleted ones included; at most `MAX_THREAD_COMMENTS` (1000, the first ones) |

Ids are matched byte for byte (a look-alike id answers null). **These reads decide no access**: they answer pages in
the trash or in archived spaces and deleted comments too. Check the snapshot's `pageId` with your space checker
(`ViewComments` on that page) or `WikiPageAccessInterface` before showing or sending anything. The snapshots carry no body, anchor or quoted text:
render a comment for a reader with `WikiCommentService::renderBodyForNotification()`, which applies the reader's rights
to the page links it holds. Without `comments.enabled` the comment reads answer null / an empty list. Beacon's comment
notifications (spec 178 T064):

```php
#[AsEventListener(event: WikiEvents::COMMENT_REPLIED)]
public function onReplied(WikiCommentEvent $event): void
{
    $thread       = $this->wikiPages->findCommentThread($event->threadId());
    $participants = [$thread?->createdById];
    foreach ($this->wikiPages->findThreadComments($event->threadId()) as $comment) {
        if (!$comment->isDeleted()) {
            $participants[] = $comment->authorId;
        }
    }
    $this->notify($event, [NotificationType::WIKI_COMMENT_REPLY => $this->users->byIds(array_filter($participants))]);
}

/** Who wrote the page's first revision (the page's author), null when unknown. */
private function pageAuthorId(string $pageId): ?string
{
    return $this->wikiPages->findPageRevision($pageId, 1)?->authorId;
}
```

## Read ports and page access by id (1.51)

Three public services answer what an application lists, counts, exports and links to, so it never writes queries over
the wiki's tables (they are the wiki's schema, not an API). All three take ids, criteria and `WikiUserRef`s and return
snapshots and plain value objects, never an entity; they work the same with the attribute or the XML mapping, users
stored by relation or by id, and the shared or the private entity manager.

| Service | For |
|---|---|
| `Query\WikiSpaceQueryInterface` | spaces by owner (kind, owner ids, space ids, archived or not), sorted and paged; how many; spaces by id; usage per owner (spaces, pages, attachment bytes) |
| `Query\WikiPageQueryInterface` | pages by space, owner or id (archived or not), sorted and paged; how many; pages by id; live pages and last change per space; ancestors; current revision bodies; backlinks; search |
| `Security\WikiPageAccessInterface` | whether a person may read a page, and which pages of a list they may read |

**Spaces of an owner, a page at a time.** A criteria narrows by every filter it is given; an empty list matches nothing
and a filter takes at most 1,000 ids. "Tenant" is whatever owner ids your application maps a tenant to.

```php
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Query\WikiArchiveFilter;
use Nowo\WikiBundle\Query\WikiSpaceCriteria;
use Nowo\WikiBundle\Query\WikiSpaceQueryInterface;
use Nowo\WikiBundle\Query\WikiSpaceSort;

$criteria = WikiSpaceCriteria::forOwners(WikiSpaceOwnerScope::Team, [$projectId, $organizationKey], WikiArchiveFilter::Active, WikiSpaceSort::NameAsc);
$spaces   = $spaceQuery->findSpaces($criteria, offset: 0, limit: 50);   // list<WikiSpaceSnapshot>
$total    = $spaceQuery->countSpaces($criteria);
$byId     = $spaceQuery->findSpacesByIds($spaceIds);                     // array<id, WikiSpaceSnapshot>, order asked
```

**Usage per owner** (plan limits, billing): one entry per owner asked, zero when it holds nothing.

```php
use Nowo\WikiBundle\Query\WikiOwnerUsage;

$usage = $spaceQuery->usage(WikiSpaceOwnerScope::Team, $ownerIds);       // array<owner id, WikiOwnerUsage>
$payer = WikiOwnerUsage::total($usage);
$payer->spaces;           // spaces not archived ($payer->archivedSpaces: archived ones)
$payer->pages;            // pages not archived, in all of those spaces ($payer->archivedPages)
$payer->attachmentBytes;  // every attachment of those spaces
```

**Pages.** A page criteria needs space ids, owner ids or page ids. `WikiPageSort::Position` is the sidebar order
inside each space; `CreatedAsc` keeps the order pages were written in (an export).

```php
use Nowo\WikiBundle\Query\WikiPageCriteria;
use Nowo\WikiBundle\Query\WikiPageSort;

$criteria = WikiPageCriteria::inSpaces([$spaceId], WikiArchiveFilter::Any, WikiPageSort::CreatedAsc);
$pages    = $pageQuery->findPages($criteria, 0, 100);       // list<WikiPageSnapshot>
$empty    = $pageQuery->countPages(WikiPageCriteria::inSpaces([$spaceId])) === 0;
$stats    = $pageQuery->pageStats($spaceIds);               // array<space id, WikiSpacePageStats>: live pages, last change
$paths    = $pageQuery->ancestors($pageIds);                // array<page id, list<WikiPageSnapshot>>, root first
$bodies   = $pageQuery->pageContents(array_map(fn ($page) => $page->id, $pages)); // array<page id, WikiPageContent>
```

`pageContents()` returns the stored source (Markdown, or HTML for an HTML revision) with the viewer-neutral HTML cache
and the plain text: hand it only to people who may read the page.

**Backlinks and search for a person.** Both take an optional viewer and then keep only the pages that person may read
(the page view's rule, below), before the limit.

```php
$sources = $pageQuery->backlinks($pageId, $viewer, spaceIds: $tenantSpaceIds, limit: 50);  // by title
$result  = $pageQuery->searchPages($searchableSpaceIds, 'deploy', $viewer, limit: 25);
foreach ($result->hits as $hit) {
    $hit->page;     // WikiPageSnapshot
    $hit->space;    // WikiSpaceSnapshot
    $hit->excerpt;  // plain text around a text match; '' for a page found by its title
}
$result->complete;  // false: more matches than the bounded scan read
```

With `backlinks.enabled` the backlink index answers (exactly the links a reader sees, never inside code) and at most
`backlinks.candidates` linking rows are read per call; without it the current revisions' Markdown is scanned for
`[[wiki:<id>` and each candidate is checked with the renderer's own reference parser. The search is the wiki's own
(title and plain text of the current revision, never the Markdown source, title matches first, archived spaces never
searched); pass the spaces your rules let the person search.

**Who may read a page, by id.** `WikiPageAccessInterface` is the id twin of `WikiSpaceGuard::isPageReadable()`: `View`
on the page and every ancestor through the same space checker (your `WikiSpaceAccessPolicyInterface` or
the space access policy, with the 1.50 archived-space rule), a parent cycle reading as hidden. The decisions
are the guard's. When you hold the security user, put it in the reference, so a policy that needs the user object
decides as on the wiki's own pages; a person given only by ids (`WikiUserRef::ofId()`) is asked as the bundle's stand-in
user, as the id operations do, and a policy that needs your user object then refuses (fail closed: nothing readable).

```php
use Nowo\WikiBundle\Security\WikiPageAccessInterface;
use Nowo\WikiBundle\ValueObject\WikiUserRef;

// You hold the user object (a request, a message handler that loaded it): pass it as the last argument.
$viewer = new WikiUserRef(User::class, (string) $user->getId(), $user->getUserIdentifier(), $user->getRoles(), $user);
// Only ids (a worker without the user): WikiUserRef::ofId(User::class, $id, $identifier, $roles).
$access->isPageReadable($viewer, $pageId);
$readable = $access->readablePageIds($viewer, $hitIds);   // the readable ones, in the order asked
```

`readablePageIds()` reads the pages and their ancestors in one query per tree level, so a list of hits costs a handful
of queries whatever its size.

**Readable is not live.** Like the guard, the rule does not check the page's archive state: an archived or trashed page
(the trash archives a page and its sub-tree) of an active space is readable by anyone with `View`. The backlinks and the
search above only return live pages; when you pass raw ids (stored references, AI sources from embeddings), drop the
archived and trashed ones yourself: `findPagesByIds()` and keep `archivedAt === null`, or read the candidates with
`WikiArchiveFilter::Active`.

**Rules every read follows.**

- No access decision in the list, count, tree and content reads: they answer what is stored. Show a person only what
  `WikiPageAccessInterface` (or your own rules) lets them read.
- Ids and owner ids are compared byte for byte in PHP after the SQL (a folding collation never answers for another id);
  owner ids follow the 1.31.1 owner-id rule and an unusable one is refused (`InvalidArgumentException`).
- Paging: `limit` 1 to 500, `offset` 0 or more; lookups by id take lists of any size (read 500 at a time).
- Read-only and stateless: no row is locked or written, nothing is kept between calls (FrankenPHP worker mode with the
  kernel kept alive). Lists, counts, trees, contents and usage read columns only, so no wiki entity enters your entity
  manager; the search and the viewer checks load pages into the wiki's manager (the private one when it is on), as the
  wiki's own pages do.

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
- 1.38 **page icons**: the same format and prefixes for `WikiPage::icon`. With `page_icon.form_field: true` the page
  form gets the field; the REST API (`icon` in page payloads and in create / update), import / export (`wiki_icon` in
  the front matter) and `WikiPageIconService` set it too. A seed:

  ```php
  $icons = $container->get(\Nowo\WikiBundle\Service\WikiPageIconService::class);
  $icons->setIcon($page, 'tabler:rocket');      // or setPageIcon($pageId, 'tabler:rocket'); null or '' clears it
  ```

  It shows before the title in the page tree and in the content card. It is drawn with `nowo_ui_kit.icon_set:
  ux_icon` (any allowed prefix), `tabler-icons` (`tabler:` names) or `bootstrap-icons` (`bi:` names, once `bi` is an
  allowed prefix); otherwise nothing is printed.
- A host form adds the field with `WikiSpaceIconType`; on a form whose data is a `WikiSpace` it writes through
  `setIcon()` with the configured prefixes, on a DTO or an array through the property path (the field name or `property_path`).

```php
$form = $this->createForm(WikiSpaceType::class, $space, ['icon_field' => true]);
// or in your own form type:
$builder->add('icon', WikiSpaceIconType::class);
```

Space exports carry no space metadata beyond the title (the pages are exported), so the icon is not part of them.

## Icon picker, emoji icons and the tree menu (1.39)

```yaml
# composer require nowo-tech/icon-selector-bundle:^1.6   (picker or emoji)
nowo_wiki:
    space_icon:
        allowed_prefixes: [tabler]
        picker: icon_selector     # text (default) | icon_selector
        emoji: true               # also one emoji, stored as emoji:<grapheme>
    page_icon:
        form_field: true
        picker: icon_selector
        emoji: true
    tree:
        enabled: true
        context_menu: true        # opt-in (default false)
    web_ui:
        page_nav: both            # none (default) | top | bottom | both
```

- **Picker**: the page and space icon fields become IconSelector's picker (search + grid of the allowed prefixes, the
  Emoji tab with `emoji: true`); the stored value is the same `tabler:book` string, or `emoji:🚀`. Import the
  IconSelector API routes in the host (`@NowoIconSelectorBundle/Resources/config/routes.yaml`) and run
  `assets:install`; the wiki pages load `icon-selector.js` (with the CSP nonce when there is one).
- **Emoji**: shown as escaped text in the tree, the card title and the space list; a stored value of another shape is
  never printed. Existing icon strings render as before. On MySQL / MariaDB the connection must be `utf8mb4`.
- **Tree menu** (opt-in, `tree.context_menu: true`): see [CONFIGURATION](CONFIGURATION.md). Keyboard: Shift+F10 or the ContextMenu
  key on a row, arrows / Home / End in the menu, Enter to choose, Escape to close (focus back on the row); in the rename
  field Enter saves and Escape cancels. 1.41: on touch screens a long press on a row opens it too.

### Tree menu entries (1.41)

| Entry | Shown to | What it does |
| --- | --- | --- |
| Open in new tab | anyone who gets the menu | The page in a new tab. |
| Edit | edit role + Edit on the space | The page's edit form. |
| Rename | edit role + Edit on the space | The row's title becomes a field; a new revision with the same body. |
| Change icon / emoji | the same, with `page_icon.form_field` | The page icon field (IconSelector's picker with `page_icon.picker: icon_selector`). |
| Add child page | create role + Create, and the edit rights | The new-page dialog (or form) placed as the last child. |
| New page above / below | the same (not on a row whose parent is archived) | The new page right above / below the row. |
| Duplicate | the same, with the `page_new` route | A copy of the page (current revision, icon, title "… (copy)") right below it, then the copy opens. The page only, not its sub-pages, history, comments or attachments. The copy has the readers of its place in the tree: host access rules are not copied; listen to `nowo_wiki.page.duplicated` (`WikiPageDuplicatedEvent`: source, copy, actor) to copy yours. A page moved by someone else meanwhile answers 409. |
| Move up / Move down | a movable tree (edit rights), where a sibling is on that side | The tree's Alt+arrow move. |
| Move to… | a movable tree | A dialog with the parents the "Move" form offers (never the page's own sub-tree); the tree's move. |
| Copy link | anyone who gets the menu | The page address to the clipboard. |
| Archive | archive role + Archive on the space | After a confirmation; the row leaves the tree and an undo line offers **Unarchive**. |
| Delete | `trash.enabled`, archive role + Delete on the space | After a confirmation; the page and **all** its sub-pages go to the trash as one deletion (restore from the trash), archived ones and those the user cannot see included. The count in the confirmation is the sub-pages the user can see, and says so. |

The marks are decided once per page view (audit PT-L4); every endpoint checks the page itself again (403 / 404) and
answers a sentence the menu shows. A move that lost a race answers 409, a page archived or trashed meanwhile 410.

### Focus mode (1.41, UiKit >= 2.55)

With UiKit's shell focus mode on (`nowo_ui_kit.theme.focus_mode.enabled: true`; `keep` lists what stays, e.g.
`[breadcrumb_min, search, timer_chip]`), the page view and the page edit form show the focus toggle in their toolbar,
remembered under the area `wiki`. In focus mode the page tree is not hidden: it becomes a drawer at the start edge,
opened by the **Pages** button of the toolbar (Escape or a click outside closes it, the focus goes back to the button).
With a UiKit without focus mode the wiki prints nothing for it.

1.44 (Beacon focus-drawer request 2026-11-18):

- **Below the bars.** The drawer starts at `--nowo-wiki-focus-drawer-top`, else
  `var(--nowo-ui-top-offset, var(--nowo-ui-focus-bar-height, 0px))` (UiKit >= 2.57 sets them; an older UiKit gives
  `0px`, the 1.43 position; UiKit's `--nowo-ui-top-offset` is the host's `--nowo-ui-host-top-height` plus the focus bar),
  and is the viewport height minus that. Its layer stays below the focus bar's (`--nowo-ui-focus-bar-z`, 45):
  `--nowo-wiki-focus-drawer-z`, one below it by default (it was 1000), the backdrop one below the drawer.
- **Backdrop.** While it is open a backdrop (`.nowo-wiki-focus-backdrop`, `--nowo-wiki-focus-backdrop-bg`) covers the
  page below the same top; a click on it closes the drawer.
- **Close button.** The tree card's header (with "Pages" and its other buttons) gets a close button
  (`data-wiki-focus-drawer-close`, label `wiki.focus.close_tree` "Close Pages", `aria-controls` the card), shown only in
  focus mode; the focus goes back to the **Pages** button.
- **Escape.** One Escape closes only the drawer (the wiki handles it first and marks it handled, so UiKit's focus mode
  ignores it); the next Escape leaves focus mode. An Escape inside a text field, the tree's menu or a dialog in the
  drawer is left to them.
- **Previous / next**: `web_ui.page_nav` prints links to the neighbours of the page in the tree's reading order.
- **Edit view title**: the title field of the page form reads as the page title (class `nowo-wiki-title-input`, the
  read view's `--nowo-wiki-read-h1-size` / heading tokens; override with `--nowo-wiki-edit-title-size`). The label stays.

## New page dialog and tree menu (1.35)

Off by default. Turn it on with:

```yaml
nowo_wiki:
    tree:
        enabled: true          # for the tree menu (the dialog works without it)
    web_ui:
        new_page_dialog: true
```

- **Page actions.** The page view gets "New page" in its header actions. It opens the new-page form (title and editor,
  the same `WikiPageFormType` as the new-page page) in a modal `<dialog>`. Create posts it to the new-page route; a
  refusal (an empty title, a taken slug, the plan limit, a place no longer valid) shows the form again on its own page
  with the reason. The space home keeps its "New page" link.
- **Tree menu.** Each row of the page tree has a menu with "New page above", "New page below" and "New page as child".
  Open it with a right click on the row, the row's «…» button (shown on hover and focus, always on touch screens), or
  the ContextMenu key / Shift+F10 when the row has the focus. Arrows, Home and End move between the choices; Enter
  chooses; Escape or Tab closes the menu and puts the focus back on the row. A choice opens the dialog with a line that
  says where the page goes ("Below “Runbooks”").
- **Rights.** Only users who may create pages in the space get the dialog. The tree menu also needs the edit rights in
  the space (role gate `canEdit()` and `WikiAction::Edit` on the space): one check per page, not per row, so the menu
  may offer a place under a page the user may not edit; the server refuses it (403). On every create the server checks
  the create right on the space (role gate and `WikiAction::Create`, so private spaces, id mode and tenants as before)
  and, for a place, the role gate `canEdit()` and `WikiAction::Edit` on the new page's parent (none at the top level).
  CSRF is the new-page form's own token (`wiki_new_<space id>`).
- **Places.** The browser sends what was chosen, never an index: `child:<page id>`, `above:<page id>` or
  `below:<page id>` (hidden field `place`). The server puts the page right above or below that page, under its parent,
  or last among its children, whatever the browser shows of the tree (a truncated tree, hidden siblings). The page is
  created and placed in one transaction that holds the space lock of the tree moves (`WikiPageMover::createAt()` /
  `createNextTo()`), with the `tree.max_depth` check, so placed creates never share a position. A create without a
  place is as before: a top-level page at position 0, without the lock (such pages sort by title). A top-level row
  whose page has an archived parent offers "as child" only.
- **Links.** `GET …/pages/new?place=child:<page id>` (or `above:` / `below:`) opens the new-page page with the place
  filled in.
- **Cost.** With the option on, each page view and space home of a user who may create pages builds the new-page form
  (and fires `nowo_wiki.editor_options`), mints its CSRF token, loads the editor script and is sent `private, no-store`.
- **API.** `POST /wikis/{wiki}/pages` takes a numeric `position` instead (see [API.md](API.md#creating-a-page)).

## Member badges and write provenance

Spec 234 (Beacon request; 1.34.0). Two host ports, both framework-free (plain PHP value
objects, no Symfony, Doctrine or HTTP type: REQ-ARCH-001 rule 3) and both off by default: without them the history,
the comments and the API render as before (the API answers carry the new fields as null).

### Member badges

`Nowo\WikiBundle\Member\WikiMemberBadgeProviderInterface` puts a host-decided badge after a member's name: revision
authors in the history timeline and comment authors in the comments panel (and in the comments JSON, as
`author.badges`, only when there are some). Beacon uses it for an *Agent* pill on service accounts.

- The bundle asks `badgesForMany(list<WikiMemberRef>)` **once per rendered list** (a history page, a thread) with the
  distinct members not seen yet, and memoises the answers for the request only (`WikiMemberBadges`: emptied at every
  request / console / Messenger boundary and on `kernel.reset`; safe in FrankenPHP worker mode with the kernel kept
  alive). Answer by index (the key of the list given); a missing index means no badge.
- `WikiMemberRef` is the user's class (Doctrine proxy prefix removed) and id (`getId()` as a string), the same class +
  id model as the wiki's id user references; compare with `isSameMemberAs()`. A user without an id the wiki can match
  exactly (1 to 128 characters, no edge whitespace, no control characters) gets no reference and no badge. A deleted
  author ("Former person") gets none either.
- `WikiMemberBadge(label, variant, title)` is plain text: the label is cut to 40 characters, the title (tooltip) to
  120, control characters removed; `variant` is `neutral`, `info` or `agent` (anything else is refused). The template
  maps the variant to UiKit badge slots and escapes every value; **no HTML and no CSS class comes from the host**. At
  most three badges are shown per member; anything that is not a `WikiMemberBadge` is dropped.
- Badges are shown beside every author, also an opaque "Editor xxxx" (`history.show_names: false`): return categorical
  badges only ("Agent", "Bot"), never one that names or singles out a person, or the badge links that person's edits
  across pages.
- Presentation: override the block `member_label_suffix` (or the file) `manage/_member_label_suffix.html.twig`.

### Write provenance

`Nowo\WikiBundle\Provenance\WikiWriteProvenanceProviderInterface::current(): ?WikiWriteProvenance` is asked once per
new revision (page create, save, restore, conversion to Markdown, imports), at write time. The revision stores:

| Column (`wiki_page_revisions`) | Value |
| ------------------------------ | ----- |
| `channel` `VARCHAR(8) NULL` | `web`, `app`, `api` or `mcp` (`WikiWriteProvenance::CHANNELS`; anything else is refused by the value object) |
| `actor_label` `VARCHAR(120) NULL` | the host's plain-text label (a token name; never a secret, a login or an e-mail): control characters removed, whitespace collapsed, **truncated** to 120 characters (in the value object and again in the entity) |

The actor id is the revision `author` the bundle already stores. The history shows "via MCP · <label>" after the
author (escaped; nothing when not recorded), the API answers `channel` / `token_name` (see [API](API.md#bodies)).

> **Warning — the actor label can name the author.** A token name is chosen by the user and is the same on every page
> ("Ana's laptop", `ana-ci`, an e-mail address). Beside an opaque "Editor xxxx" (`history.show_names: false`) it would
> link the pseudonymous author across pages and often name them. So the label is shown in the history and answered as
> `token_name` only (audit PQ-M1):
>
> - to the author themself (their own writes), always: the same user object, or a `user_class` / `accepted_user_classes`
>   user with the author's id (a user of another identity domain whose id collides is not the author: audit JX-W1);
> - to others only with `history.show_actor_labels` on (default: the value of `history.show_names`), beside an author
>   shown under a real name, and when the label passes the same safe-name rule as display names (no `@`, nothing of
>   the login or its local part, printable Latin text only).
>
> The channel is categorical and is always shown. Comments carry no provenance. The label is user text: a user can
> call a token "Agent" or "Former person", so never treat it as a verified role (badges come from the badge provider).

Decide the channel from the **request path** (your web UI, your app, your API firewall, your MCP endpoint), never from
a header, field or parameter the client sends; resolve it per call (no per-request value in a service property).

Standard Symfony host:

```php
use Nowo\WikiBundle\Provenance\WikiWriteProvenance;
use Nowo\WikiBundle\Provenance\WikiWriteProvenanceProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class AppWriteProvenance implements WikiWriteProvenanceProviderInterface
{
    public function __construct(private RequestStack $requests, private ApiTokenContext $tokens) {}

    public function current(): ?WikiWriteProvenance
    {
        $request = $this->requests->getMainRequest();
        if ($request === null) {
            return null; // console, Messenger: nothing recorded
        }
        $path    = $request->getPathInfo();
        $channel = match (true) {
            str_starts_with($path, '/mcp') => WikiWriteProvenance::CHANNEL_MCP,
            str_starts_with($path, '/api/') => WikiWriteProvenance::CHANNEL_API,
            default => WikiWriteProvenance::CHANNEL_WEB,
        };

        return new WikiWriteProvenance($channel, $this->tokens->currentTokenName());
    }
}
```

```yaml
# config/services.yaml
Nowo\WikiBundle\Provenance\WikiWriteProvenanceProviderInterface: '@App\Wiki\AppWriteProvenance'
Nowo\WikiBundle\Member\WikiMemberBadgeProviderInterface: '@App\Wiki\AppMemberBadges'
```

DDD / hexagonal host: implement both interfaces in your infrastructure layer as plain adapters over your own request
context and user read model (they only see `WikiMemberRef` / `WikiWriteProvenance` value objects), and alias them the
same way. Badges are keyed by the class + id your user references already use, so a host that stores wiki authors by
id answers from its own table:

```php
final readonly class MemberBadges implements WikiMemberBadgeProviderInterface
{
    public function __construct(private ServiceAccountIds $serviceAccounts) {}

    public function badgesFor(WikiMemberRef $member): array
    {
        return $this->badgesForMany([$member])[0] ?? [];
    }

    public function badgesForMany(array $members): array
    {
        $agents = $this->serviceAccounts->among(array_map(static fn (WikiMemberRef $m): string => $m->id, $members));
        $badges = [];
        foreach ($members as $index => $member) {
            if (in_array($member->id, $agents, true)) {
                $badges[$index] = [new WikiMemberBadge('Agent', WikiMemberBadge::VARIANT_AGENT, 'Service account')];
            }
        }

        return $badges;
    }
}
```

### Personal data

The actor label is personal data. The bundle's user-erasure path covers it (audit PQ-L1):

- `wiki:comments:erase-user <user>` exports the labels of the user's revisions (`--export`: `revision_actor_labels`)
  and erases them, together with the labels of revisions whose author was already deleted (`author_id` set to NULL by
  the database). With comments disabled the command handles the labels only.
- `WikiCommentService::exportAuthorData()` (the host's personal-data exporter) includes them as
  `revision_actor_labels`, and `WikiCommentService::eraseAuthor()` (the host's eraser) erases them: a listener on
  `nowo_wiki.comment.author_erased` calls `WikiWriteProvenanceErasure::eraseActor()` and `eraseOrphaned()`.
- `WikiWriteProvenanceErasure` (public service) has `exportActor($user)`, `eraseActor($user)` and `eraseOrphaned()`
  for hosts with their own flow.

Labels of a deleted author are never shown nor answered, even before they are erased. The channel is not personal data
and stays. Page exports (`wiki:export`) do not carry provenance. A write answer stored for an `Idempotency-Key` replay
keeps the `token_name` it had until that record is purged (`nowo:wiki:api:purge-idempotency`, see
[API](API.md#idempotency)).

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
| `manage/page_diff.html.twig` | Diff of two revisions (UiKit show scaffold); 1.45: the rendered diff (`rendered_diff`) with `history.rendered_diff` |
| `manage/search.html.twig` | Search (UiKit list scaffold: filters + results) |
| `manage/ai_ask.html.twig` | AI question (UiKit form scaffold) with the answer and sources |
| `manage/_sidebar.html.twig` | Sidebar card with the page tree and the "tree truncated" notice |
| `manage/_quick_access.html.twig` | 1.45: the Favourites and Recently viewed disclosures above the tree (`quick_access`) |
| `manage/_backlinks.html.twig` | 1.45: "Linked from" after the page body (`backlinks`, block `wiki_page_backlinks`) |
| `manage/_page_tree.html.twig` | One level of the tree (`<ul class="nowo-wiki-tree">`) |
| `manage/_page_tree_item.html.twig` | One page of the tree: link, depth class, `aria-current`, its children |
| `manage/_ai_dialog.html.twig` | AI question `<dialog>` of the index (1.53: empty UiKit `remote_form_dialog`, loads `ai_ask` → `manage/_ai_ask_form.html.twig`) |
| `manage/_member_label_suffix.html.twig` | Host badges after a member's name (history authors, comment authors); block `member_label_suffix` |

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
| `manage/ai_ask.html.twig` (`page.form`), `manage/_ai_ask_form.html.twig` (`form`, 1.53; `_ai_dialog.html.twig` `ai_form` deprecated) | `page.form` / `ai_form` | `WikiAiAskType` (flat `space` / `q` / `_token`, token id `wiki_ai_ask`) |

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
- **Collapsible page tree** (1.38, the nested list): each page with sub-pages gets a chevron button before its title
  that opens and closes them (mouse, or Tab then Enter / Space). `tree.default_expanded` picks what starts open
  (`current_path`, default; `all`; `none`); the current page's ancestors always start open and each reader's choices
  are remembered per space in the browser. Without JavaScript the whole tree is open. Tokens:
  `--nowo-wiki-tree-fold-color`, `--nowo-wiki-tree-fold-size` (the room a row without sub-pages keeps, 1.5rem; 2rem
  on touch screens).
- **Full-width card** (1.38, `web_ui.reading_width: text`): the card fills the main column on any screen width and
  only its text keeps `--nowo-wiki-reading-width` (with `reading_align: start`, against the card's left edge), so a
  wide screen shows no empty band beside the page.
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
  (`web_ui.page_header.toolbar_position: card`). With all of them:

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
  footer (`.nowo-wiki-page-meta` in the UiKit `card_footer` slot of `.nowo-wiki-page-card` since 1.38, `<time datetime>`), in the reader's locale (`IntlDateFormatter` medium date + short
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

#### 1.33 additions

Every option is off by default (the 1.32 edit form).

- **Edit in the read layout** (`web_ui.edit_view.layout: read`): the edit page is the read page with the editor in
  place of the content. The same tree aside (folded or hidden with `tree_rail` / `tree_collapsed_mode`, the same
  remembered state and expand button), the same reading column, the same card toolbar (`toolbar_position: card`) and
  the same path above the content; the title field, the editor and Save / Cancel sit where the body is, and the editor
  takes the read typography (`--nowo-wiki-read-*` tokens, `.nowo-wiki-edit-body`). Creating a page keeps the form.
- **Edit toolbar** (`web_ui.page_header.edit_toolbar`): `current_tab: hidden` drops the Edit tab while editing (the
  tabs read Page / History); `back: view` adds a "View page" eye button (with `tabs: toolbar` it takes the place of
  "Back to page").
- **Filling the form in a host template.** The block `wiki_page_form_body` holds the whole form in the read layout
  (default: the UiKit form). Override `nowo_ui_form_body` to change the fields only; override `wiki_page_form_body` to
  draw the form yourself (`page.form` is untouched then), or add around it with `{{ parent() }}`:

  ```twig
  {% extends '@NowoWikiBundle/manage/page_edit.html.twig' %}

  {% block wiki_page_form_body %}
      <p class="app-edit-hint">{{ 'app.wiki.edit_hint'|trans }}</p>
      {{ parent() }}
  {% endblock %}
  ```
- **BreadcrumbKit on tenant routes** (fix): with `breadcrumb_current_label: true`, the edit / history / compare / move
  pages of tenant routes (`tenant_paths`, e.g. `app_project_nowo_wiki_page_edit`) read "Project › Wiki › <title> ›
  Edit" instead of a generic "Page".

Beacon (replaces a host `.wiki-edit-layout`):

```yaml
nowo_wiki:
    web_ui:
        edit_view:
            layout: read
        page_header:
            edit_toolbar:
                current_tab: hidden
                back: view
```

### Theming the page card (1.43)

The page card (the content card of the read view), its title, its path row and the reading text take their look from
CSS custom properties, so a host themes them from its own stylesheet without targeting `nowo-wiki-*` classes. Set them
on any ancestor (the page, `.beacon-wiki`, `:root`). **Without any of them set, the computed styles are those of 1.42**:
every default below is the value the rule had before (a test compares the rules with the 1.42 declarations).

| Token | Part | Default |
|-------|------|---------|
| `--nowo-wiki-page-card-padding` | card (`.nowo-wiki-page-card`, with `page_meta: card`) | `0` (the body and the footer pad themselves) |
| `--nowo-wiki-page-card-gap` | card, space between the body and the footer line (a top margin on the later children; the card stays a block) | `0` |
| `--nowo-wiki-page-card-bg` | card | `var(--nowo-ui-card-bg, var(--nowo-ui-surface))` |
| `--nowo-wiki-page-card-border` | card (full `border` shorthand) | `1px solid var(--nowo-ui-card-border, var(--nowo-ui-border))` |
| `--nowo-wiki-page-card-radius` | card | `var(--nowo-ui-card-radius, var(--nowo-ui-radius))` |
| `--nowo-wiki-page-card-shadow` | card | `none` |
| `--nowo-wiki-page-card-margin-end` | card, bottom margin (only the nowo-ui theme's card has one; Bootstrap's does not) | `1rem` |
| `--nowo-wiki-page-card-title-size` | the card title (`.nowo-wiki-page-title`, the h1 shown when the body has no own h1) | `var(--nowo-wiki-read-h1-size, 2em)` |
| `--nowo-wiki-page-card-title-weight` | title | `var(--nowo-wiki-read-heading-weight, 600)` |
| `--nowo-wiki-page-card-title-line-height` | title | `var(--nowo-wiki-read-heading-line-height, 1.25)` |
| `--nowo-wiki-page-card-title-padding` | title (all sides) | `0` |
| `--nowo-wiki-page-card-title-padding-end` | title, inline end inside the card frame: keeps clear of the floating toolbar | `calc(var(--nowo-wiki-card-toolbar-width, 12rem) + var(--nowo-wiki-card-toolbar-inset, 0.75rem))` |
| `--nowo-wiki-page-card-title-padding-end-narrow` | same, under 40rem (the toolbar is its own row) | `0` |
| `--nowo-wiki-page-card-title-gap` | title, bottom margin (alias of the older `--nowo-wiki-page-title-gap`, which keeps working) | `var(--nowo-wiki-page-title-gap, 0.75rem)` |
| `--nowo-wiki-page-card-head-gap` | page head (path and sub-pages chip row), `gap` | `0.25rem 0.75rem` |
| `--nowo-wiki-page-card-head-padding` | page head | `0` |
| `--nowo-wiki-page-card-head-padding-end` | page head, inline end inside the card frame (toolbar clearance) | as the title's |
| `--nowo-wiki-page-card-head-min-height` | page head inside the card frame, so the path lines up with the toolbar | `2.5rem` |
| `--nowo-wiki-page-card-head-min-height-narrow` | same, under 40rem | `0` |
| `--nowo-wiki-read-max-width` (new) | reading text measure (`web_ui.reading_width: text`); falls back to `--nowo-wiki-reading-width`, then `--nowo-ui-reading-width`, then `75ch` | `75ch` |
| `--nowo-wiki-read-h1-weight`, `--nowo-wiki-read-h2-weight`, `--nowo-wiki-read-h3-weight` (new) | one heading's weight in the read / edit body | `var(--nowo-wiki-read-heading-weight, 600)` |

The reading text already had tokens, unchanged: `--nowo-wiki-read-font-size`, `--nowo-wiki-read-line-height`,
`--nowo-wiki-read-h1-size` … `-h6-size`, `--nowo-wiki-read-heading-weight`, `--nowo-wiki-read-heading-line-height`,
`--nowo-wiki-read-heading-margin`, `--nowo-wiki-read-block-margin` and the rest listed at the top of `wiki.css`; the
toolbar clearance reads `--nowo-wiki-card-toolbar-width` / `--nowo-wiki-card-toolbar-inset`.

Beacon-like example. It replaces the host rules that targeted `.nowo-wiki-page-card`, `.nowo-wiki-page-head` and the
title (the card frame itself, `.nowo-wiki-card-frame`, stays the host's own box):

```scss
.beacon-wiki {
  // The page card sits flat inside Beacon's content card: no frame of its own.
  --nowo-wiki-page-card-border: 0;
  --nowo-wiki-page-card-radius: inherit;
  --nowo-wiki-page-card-bg: transparent;
  --nowo-wiki-page-card-shadow: none;
  --nowo-wiki-page-card-margin-end: 0;

  // The card title: the body's h1 look, padded like the path above it.
  --nowo-wiki-page-card-title-padding: 0.5rem 1.25rem 0;
  --nowo-wiki-page-card-title-size: 1.5em;
  --nowo-wiki-page-card-title-weight: 600;
  --nowo-wiki-page-card-title-line-height: 1.3;
  --nowo-wiki-page-card-title-gap: 0;

  // The path row.
  --nowo-wiki-page-card-head-padding: 0.75rem 0 0 1.25rem;

  // Reading text.
  --nowo-wiki-read-font-size: 0.9375rem;
  --nowo-wiki-read-line-height: 1.65;
  --nowo-wiki-read-h1-size: 1.5em;
  --nowo-wiki-read-h2-size: 1.3em;
  --nowo-wiki-read-h3-size: 1.125em;
  --nowo-wiki-read-heading-weight: 600;
  --nowo-wiki-read-max-width: 80ch;
}
```

Two Beacon rules stay outside the tokens because they depend on a sibling: the extra top padding of the body under a
page head, and the footer line's own padding and dashed rule (`.nowo-wiki-page-card__footer`, a UiKit `card_footer`).

## Demo smoke (REQ-TEST-011)

To check the bundle in a running host, boot the demo and assert HTTP 200 on the wiki home:

```bash
make demo-smoke   # boots demo/symfony8 and polls http://127.0.0.1:8025/tools/wiki
```

The port comes from `PORT` in `demo/symfony8/.env`. The `Demo smoke` GitHub workflow runs the same command weekly, on tags and on demand. See [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md#demo-smoke-req-test-011).

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

Dispatched after the change is flushed, except `nowo_wiki.quota.check` and the read-only `nowo_wiki.search_kit.spaces`.

- `nowo_wiki.search_kit.spaces` — `WikiSearchSpacesEvent` (`userIdentifier`, `scope`, `locale`, `spaces()`, `exclude(spaceId)`, `excludeAll()`; 1.46, `search_kit_provider`): before the SQL of the SearchKit group; a listener may leave spaces out ([Host rules](#host-rules-146))
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
final class IssueLinkResolver implements WikiObjectLinkSourceInterface   // autoconfigured
{
    public function supports(string $objectType): bool { return $objectType === 'issue'; }

    // One query for every id; leave out what the viewer may not open ($user->id is the viewer's id).
    public function resolveVisible(WikiUserRef $user, string $objectType, array $objectIds): array
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

**Page references and backlinks:** since 1.45 the bundle keeps its own backlink index (`backlinks.enabled`, see [Favourites, recents and backlinks](#favourites-recents-and-backlinks-145)). The `<prefix>_page_links` table still holds only host objects linked by hand. A host that keeps an index of its own reads the references on save:

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

## Favourites, recents and backlinks (1.45)

Three opt-in switches (see [CONFIGURATION](CONFIGURATION.md#favourites-recents-and-backlinks-145)); each adds its own
table, so run your migrations after turning one on (DDL in [UPGRADING](UPGRADING.md#to-1450)).

- **Favourites** (`favourites.enabled`): the page actions get *Add to favourites* / *Remove from favourites* (a POST
  form with its CSRF token; no JavaScript needed), and the sidebar a **Favourites** section above the page tree, newest
  first. Adding a page that is already a favourite (another tab) changes nothing; past `favourites.max` the add is
  refused with a message. JSON clients (`Accept: application/json`) get `{"favourite": true, "result": "added"}`
  (`removed`, `unchanged`) or `422 {"error": "limit_reached"}`. Only the favourites the person can still read in this
  tenant count toward the limit. `POST {favourite_remove}` (`/tools/wiki/favourites/{favouriteId}/remove`, CSRF token id
  `wiki_favourite_remove`) removes one by its id whatever its page (`{"result": "done"}` either way); the sidebar
  rows carry the id in `data-wiki-favourite-id`.
- **Recently viewed** (`recents.enabled`): every page view records the visit (never a `HEAD` request or a browser
  prefetch); the sidebar lists the pages opened last (the current page left out). At most `recents.keep` visits are kept
  per person, none older than `recents.retention_days` (90); schedule `bin/console wiki:recents:purge` (daily) for
  people who stopped visiting.
- **Linked from** (`backlinks.enabled`): after the page body (Twig block `wiki_page_backlinks`, after
  `wiki_page_after_body`), the pages whose **current** revision links this one with `[[wiki:<id>]]` or
  `[[wiki:<id>#anchor]]` (never inside code), by title, with the space name for another space. The index is written
  after every save (create, edit, restore, import, conversion); fill it once with `bin/console wiki:backlinks:rebuild`
  (safe to run beside live saves, and again at any time; a page that keeps meeting lock conflicts is retried, then
  skipped and listed, and the command exits with FAILURE: run it again, or resume with `--after=<page id>`). HTML
  (Tiptap) revisions hold no `[[wiki:…]]` references, so such pages are never sources. A Markdown revision over the
  renderer's `max_input_length` / `max_inline_markers` is read as text, so the index keeps the links it had until a
  smaller revision is saved.

Each list is built once per view (`QuickAccess\WikiViewerScope`) and only shows pages of the **current tenant** the
viewer may read now, filtered before it is cut: the tenant's readable spaces and live pages in SQL, then `View` on the
page and every ancestor (see [CONFIGURATION](CONFIGURATION.md#favourites-recents-and-backlinks-145)). Nothing is kept
in memory between requests (FrankenPHP worker mode).

The sidebar sections are native disclosures (`<details open>`, `_quick_access.html.twig`), so they fold without
JavaScript. Classes for theming: `.nowo-wiki-quick`, `.nowo-wiki-quick__summary`, `.nowo-wiki-quick__link` (the
current page has `aria-current="page"`), `.nowo-wiki-backlinks`, `.nowo-wiki-backlinks__link`. A host that showed its own
backlinks in `wiki_page_after_body` (Beacon: `wiki_backlinks()`) removes them when it turns `backlinks.enabled` on, or
overrides `wiki_page_backlinks` with an empty block to keep its own.

**Concurrency.** The favourite toggle, the visit and the index are written through the wiki's runner on the connection
(nothing is flushed; your entity manager is never flushed, cleared or reset). A lock conflict on the favourite route
answers `409` with `X-Conflict-Code: CONFLICT_001` and `Retry-After` (nothing was saved; JSON body
`{"error": "conflict", "code": "CONFLICT_001"}`), or `CONFLICT_002` without `Retry-After` when an earlier write of the
same request was already committed. A visit or an index write that meets a lock conflict is logged and skipped: the
page is shown, and the index catches up on the page's next save or a rebuild. See [HIGH-LOAD](HIGH-LOAD.md).

### Rendered diff (1.45)

With `history.rendered_diff: true` and `editor.type: milkdown`, *Compare* in the history shows the two versions as
rendered content: the editor's `MarkdownDiffRenderer` (MilkdownEditorBundle) matches top-level blocks and diffs a
changed paragraph word by word; inserted text is in `<ins>`, deleted text in `<del>`, each with a visually hidden
"Inserted:" / "Deleted:" label for screen readers. Every block is rendered **for the viewer** (a link to a page the
viewer may not read shows as hidden content, as on the page view) and sanitized with the page's allow-list; the whole
diff is then sanitized again with the page allow-list plus only the diff markup (`ins`, `div[data-change]`), so no
revision content can inject script. Versions over the renderer's `max_input_length`, with more than 2000 blocks or
more than 500 changed blocks get the editor's escaped line diff; versions with more than 300 top-level blocks
(each block is rendered for the viewer on its own) keep the wiki's line diff. A comparison that involves an HTML revision keeps
the line diff. The page loads the editor's read-side styles (`nowo_wiki_rich_content_assets()`).

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
3. Its `WikiSpaceAccessPolicyInterface` maps `ViewComments` → `project.wiki.view` / `wiki.view`, `Comment` → edit,
   `ResolveComment` and `ModerateComments` → manage (B-Q3).
4. The two routes (`page_comments`, `space_panel_state`) behind the wiki firewall; tenant paths get them automatically.
5. 1.21: `comments.moderation_panel: true` and `comments.error_notices: true` replace a forked panel body and Stimulus
   controller.
5. Optional: `comments.state_store` → its user-preferences store (`WikiPanelStatesInterface`), `comments.shortcut`.
   Keep `comments.read_view_source: false` (the default) while `ViewComments` maps to view: readers then never get the
   page source; editors keep inline highlights on the read view.
6. Live updates come with co-editing (the comments topic shares the co-editing subscriber cookie).
7. Notifications from `nowo_wiki.comment.*`; deep links with `?comments=1`.
8. Avatars: alias `WikiUserAvatarSourceInterface` to its `WikiAvatarUrls` adapter of the core `AvatarUrls`
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

## Browser console

The bundle's script logs one line when it loads (`📦 [wiki] script loaded, build: 2026-11-23 14:05 UTC`) so a developer can tell which build a page runs. To silence it, set `<html data-nowo-console="quiet">` or `globalThis.NOWO_CONSOLE = 'quiet'` before the script loads; nothing is printed then. The default is unchanged.
