# Upgrading

## Table of contents

- [Unreleased](#unreleased)
- [To 2.0.0](#to-200)
  - [Before you upgrade (on 1.55.2)](#before-you-upgrade-on-1552)
  - [Default flip: the private entity manager is the only mode](#default-flip-the-private-entity-manager-is-the-only-mode)
  - [Private manager only](#private-manager-only)
  - [REST API: the firewall user and the token's user must be the same person](#rest-api-the-firewall-user-and-the-tokens-user-must-be-the-same-person)
  - [From 1.55.2: host-loaded rows, batch space reads, `createInSpace()`](#from-1552-host-loaded-rows-batch-space-reads-createinspace)
  - [Removed](#removed)
  - [Deprecated for 3.0](#deprecated-for-30)
  - [Composer](#composer)
  - [Unchanged](#unchanged)
  - [Host checklist](#host-checklist)
  - [1.x support](#1x-support)
- [To 1.56.0](#to-1560)
  - [Space icon by id (1.56)](#space-icon-by-id-156)
- [To 1.55.2](#to-1552)
  - [Private entity manager: host-loaded spaces and pages (1.55.2)](#private-entity-manager-host-loaded-spaces-and-pages-1552)
- [To 1.55.1](#to-1551)
  - [Erasure checks (1.55.1)](#erasure-checks-1551)
- [To 1.55.0](#to-1550)
  - [Space create, erasure and comment reads by id (1.55)](#space-create-erasure-and-comment-reads-by-id-155)
- [To 1.54.0](#to-1540)
- [To 1.53.0](#to-1530)
  - [Dialogs load on demand](#dialogs-load-on-demand)
- [To 1.52.0](#to-1520)
  - [The bundle declares its own identity / access-key columns (REQ-SEC-006)](#the-bundle-declares-its-own-identity--access-key-columns-req-sec-006)
- [To 1.51.1](#to-1511)
- [To 1.51.0](#to-1510)
- [To 1.50.0](#to-1500)
- [To 1.49.1](#to-1491)
- [To 1.49.1](#to-1491-1)
  - [Missing foreign keys on MySQL / MariaDB (1.36.0 to 1.49.0)](#missing-foreign-keys-on-mysql--mariadb-1360-to-1490)
- [To 1.49.0](#to-1490)
- [To 1.49.0](#to-1490-1)
  - [The private entity manager notice](#the-private-entity-manager-notice)
  - [Reading the host-flush probe as a migration list](#reading-the-host-flush-probe-as-a-migration-list)
  - [Other notices of members 2.0 removes](#other-notices-of-members-20-removes)
- [To 1.48.1](#to-1481)
- [To 1.48.0](#to-1480)
- [To 1.47.1](#to-1471)
- [To 1.47.0](#to-1470)
- [To 1.46.0](#to-1460)
- [To 1.45.0](#to-1450)
- [To 1.44.0](#to-1440)
- [To 1.43.0](#to-1430)
- [To 1.42.0](#to-1420)
- [To 1.41.0](#to-1410)
- [To 1.40.2](#to-1402)
- [To 1.40.1](#to-1401)
- [To 1.40.0](#to-1400)
- [To 1.39.0](#to-1390)
- [To 1.38.1](#to-1381)
- [To 1.38.0](#to-1380)
- [To 1.37.0](#to-1370)
  - [High load (Beacon spec 239)](#high-load-beacon-spec-239)
  - [Optional: exact collation of the page link columns](#optional-exact-collation-of-the-page-link-columns)
- [To 1.36.0](#to-1360)
- [To 1.36.0](#to-1360-1)
  - [Host ports: move to the framework-free twins (optional until 2.0)](#host-ports-move-to-the-framework-free-twins-optional-until-20)
  - [XML mapping (`database.mapping_type: xml`)](#xml-mapping-databasemapping_type-xml)
  - [User references by id (`database.user_reference: id`)](#user-references-by-id-databaseuser_reference-id)
  - [Private entity manager (`database.private_entity_manager: true`; the only mode in 2.0)](#private-entity-manager-databaseprivate_entity_manager-true-the-only-mode-in-20)
- [To 1.35.0](#to-1350)
- [To 1.34.0](#to-1340)
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

## To 2.0.0

From **1.55.2** (the last 1.x release; a major version: widen your constraint):

```bash
composer require nowo-tech/wiki-bundle:^2.0 -W
php bin/console cache:clear
```

2.0 contains only changes that 1.x announced through deprecation notices: an application that runs the last 1.x minor
without deprecation notices from this bundle upgrades without code changes. After 2.0.0 is released, the 1.x line
receives security fixes for 6 months: fixes rated Moderate and above are backported to the last 1.x minor; Low ones are
fixed in 2.x only.

No schema change and no migration: the tables, columns and indexes are those of 1.55.2.

### Before you upgrade (on 1.55.2)

Clear every `nowo-tech/wiki-bundle` notice from your deprecation log (`var/log/*.log` with the `php` channel, the
profiler's "Deprecations" tab, or your test suite's deprecation report), for a full deploy cycle of your tests and
production traffic:

1. **The private entity manager notice** (once per container build):

   > Since nowo-tech/wiki-bundle 1.49: nowo_wiki.database.private_entity_manager is false (the 1.x default): every wiki
   > write flushes your entity manager "default", including what your application left pending in it. 2.0 removes the
   > option: the private entity manager becomes the only mode, and wiki writes never flush, clear or close your manager.
   > Opt in now with "nowo_wiki: { database: { private_entity_manager: true } }" once the host-flush probe notices
   > ("flushed by NowoWikiBundle", "rows with its own entity manager") are gone; see UPGRADING "To 1.49.0".

   Set `nowo_wiki.database.private_entity_manager: true` on 1.55.2 first, once the two probe notices below are gone, and
   run with it for a deploy cycle.
2. **The host-flush probe** (the migration list, see [Reading the host-flush probe](#reading-the-host-flush-probe-as-a-migration-list)):
   - "Your pending changes to App\X were flushed by NowoWikiBundle; 2.0 no longer does.": flush your own changes
     yourself;
   - "Your application inserted / updated / deleted Nowo\WikiBundle\Entity\X rows with its own entity manager, outside a
     wiki write. ...": write those rows through the wiki (the id operations), never through your manager.
3. **The port interfaces** ("The service "app.x" (App\X) implements the deprecated ..., which 2.0 removes: implement ...
   instead ..."): implement the twin ([Host ports](#host-ports-move-to-the-framework-free-twins-optional-until-20)).
4. **The `$user` members** ("Reading ...::$user (a Symfony user) is deprecated, 2.0 removes it: read userRef() (a
   framework-free WikiUserRef) instead."): read `userRef()`. In 2.0 the member is private: reading it throws an `Error`.
5. **`WikiStorageFailureSubscriber::committed()`** and **`new WikiAlignedHeaderConverter()`** ("... is deprecated, 2.0
   removes it ..."): use `ConflictCode::forRequest()` / `WikiHeadingIdConverter`.
6. **Configuration keys** deprecated in 1.6 and 1.10 ("The "nowo_wiki.x" option is deprecated ..."): see the table below.
7. **The REST API log notice** "Wiki API: the firewall user (…) and the token's user (…) are matched by their id only:
   add … to nowo_wiki.accepted_user_classes only if it carries user_class ids (an adapter or wrapper); otherwise the
   firewall and the authenticator must return the same user. This will be refused in 2.0." (a `notice` of the
   `nowo_wiki` log channel since 1.36, not a deprecation): fix it as it says (see "REST API: same person" below).
8. **Without a notice**, check by hand: a `web_ui.layout_template` that renders only `{% block body %}` (it reported a
   notice per page render since 1.10), host template overrides that include the dialog partials with the 1.35–1.52
   variables (`new_page_dialog.form`, `import_form` / `export_links` in the Import & export dialog, `ai_form`,
   `tree_menu.icon_form` / `move_form`; see "To 1.53.0"), and host code that reads wiki rows with your own entity manager
   and hands them to the wiki (below).

### Default flip: the private entity manager is the only mode

| | 1.x default | 2.0 |
|---|---|---|
| `database.private_entity_manager` | `false`: wiki writes flush `database.entity_manager` (your manager) | no option: the wiki's private manager on the same connection, mapping and tables |
| `false` | the shared manager | the build fails |
| `true` | the private manager | no effect, a deprecation notice ("has no effect since 2.0"); remove the key (it goes in 3.0) |

There is no way to keep the 1.x behaviour: the private manager is the only mode.

### Private manager only

- **Your pending changes are no longer flushed by the wiki.** A change of yours that was only stored because a wiki
  write flushed your manager is no longer stored: typically in a listener of a wiki event (`WikiEvents::*`), in a port of
  yours, or in code that changes an entity and then calls a wiki service. Flush your manager yourself. The 1.49 probe
  ("Your pending changes to App\X were flushed by NowoWikiBundle") listed these cases.
- **The reverse, which the probe cannot see:** host code that loads a wiki row through the wiki's repository services
  (now on the private manager), changes it and flushes its own manager saves nothing (the private manager drops it at
  the next boundary with only a warning in the log). Change wiki rows through the wiki's services and id operations
  (`WikiPageOperationsInterface`, `WikiSpaceOperationsInterface`, `WikiPageLinkOperationsInterface`), or through the
  wiki's own manager (`WikiEntityManagerProvider::get()`) and flush that.
- **Wiki rows your manager loaded** (a route value resolver, a repository of yours, a query on your manager) and hand to a
  wiki service (`WikiPageServiceInterface`, `WikiPageLinkServiceInterface`, the trash, attachment, page mover and page
  icon services) are loaded again by their id in the private manager: the wiki writes the row itself and never inserts it
  a second time. What you changed on your instance before the call is not carried over: change wiki rows through the
  wiki. A row deleted meanwhile answers `WikiPageNotFoundException` / `WikiSpaceNotFoundException`. Persisting,
  refreshing or locking such a row through the wiki's manager yourself fails with a `LogicException` that says so (never
  a duplicate `INSERT`).
- **Your entities on wiki rows** (the revision author, the link creator, the uploader, the comment author of
  `database.user_reference: entity`): the wiki writes a reference to the same id in its private manager and never your
  instance. A user you persisted but did not flush yet (no id) fails with a `LogicException` before anything is written:
  flush your user first. Events still hand you the user object you passed.
- **Your entities that point at wiki rows** (a `ManyToOne` from one of yours to `WikiPage` / `WikiSpace`): set them with
  `$entityManager->getReference(WikiPage::class, $id)` on your own manager (the id from a snapshot or an id operation),
  never with an instance the wiki returned (it belongs to the private manager, and your flush would take it for a new
  entity).
- Users and wiki rows read back through the wiki are the private manager's instances: compare them by id, never with
  `===`.
- **Ports of yours whose 1.x correctness relied on the wiki's flush** (a `WikiApiWikiDirectoryInterface::provision()`
  that only changed a managed row of yours, a membership source or a quota listener that wrote your entities): your
  implementation must commit its own change.
- In a FrankenPHP worker with the kernel kept alive and not reset, the private manager is dropped at every request,
  Messenger message and console command boundary and at `kernel.reset`, as on 1.36–1.55.2 with the option on.

### REST API: the firewall user and the token's user must be the same person

1.36 compared the firewall's user and the user your `api.hooks.authenticator` returns by class and id, but still let
two users of **different classes that only share an id** through (a security adapter that is not in
`accepted_user_classes`), with the log notice above. 2.0 refuses them (audit OX-D1-L1): an id that looks the same in
another user class is another person, so the request is a configuration error and answers 500, with `Wiki API:
configuration error: the firewall user (…) and the token's user (…) share only their id; 2.0 refuses this: …` in the
log. Nothing is written.

- If the firewall's class is an adapter or wrapper that carries the `user_class` ids, add it to
  `nowo_wiki.accepted_user_classes`.
- Otherwise make the firewall and the authenticator return the same user.
- When you move to `WikiApiPrincipal::of()` (the 2.0 deprecation of the constructor), put the **authenticated user
  object** in the `WikiUserRef` (`new WikiUserRef($user::class, $id, $user->getUserIdentifier(), $user->getRoles(),
  $user)`): an id-only ref (`WikiUserRef::ofId(...)`) is refused by this check, so every API call answers 500 (audit
  ZBL-3).

Unchanged: the same object, two users of `user_class` / an accepted class with the same usable id, and two users of
one other class with the same exact id still match; ownership and the API id are still judged on the token's user.

### From 1.55.2: host-loaded rows, batch space reads, `createInSpace()`

- The 1.55.2 re-reads of host-loaded spaces and pages are the 2.0 rule above (a wiki row your manager loaded is loaded
  again by id): the internal `Doctrine\WikiManagedEntities` of 1.55.2 is gone, `WikiTransactionRunner::attach()` does
  it for `WikiPageServiceInterface`, `WikiPageLinkServiceInterface`, `WikiPageIconService`, `WikiPageMover`,
  `WikiPageDuplicator`, `WikiTrashService` and `WikiAttachmentService`. A row of yours that no longer exists is
  `WikiPageNotFoundException` / `WikiSpaceNotFoundException` (the mover: `page_missing` / `parent_missing`); a space you
  persisted without flushing is `WikiSpaceNotFoundException` (1.55.2: `LogicException`); a detached row (no manager
  holds it) is loaded again by its id. The API and UI guards need no re-read: their spaces come from the framework-free
  ports by id; a `WikiSpace` your `WikiApiWikiDirectoryInterface` puts on a `WikiApiWiki` is only its id (the gate reads
  that space through the wiki), and a wiki whose space is no longer stored is a missing wiki (404).
- `WikiSpaceRepositoryInterface` extends `WikiSpaceBatchFinderInterface` (1.55.2): a space repository of your own must
  implement `findByIds(array $ids): array` (keyed by id, exact ids only) and `findByIdWithoutRefresh(string $id)`. The
  gates check the exact id of every answer again.
- `WikiPageServiceInterface` gains `createInSpace(string $spaceId, …)` (on the class since 1.55.2): a class of your own
  implementing the interface must add it.

### Removed

| Removed | Deprecated since | Replacement |
|---|---|---|
| `database.private_entity_manager: false`, the host-flush probe (`Doctrine\WikiHostFlushProbe`) and its notices | 1.36 (docs), 1.49 (notice) | nothing to configure: the private manager is the only mode |
| `Security\WikiAccessCheckerInterface` | 1.36, notice 1.49 | `Security\WikiAccessPolicyInterface` (`security.access_checker`) |
| `Security\WikiSpaceAccessCheckerInterface` | 1.36, notice 1.49 | `Security\WikiSpaceAccessPolicyInterface` (alias it) |
| `Security\WikiTeamMembershipResolverInterface` | 1.36, notice 1.49 | `Security\WikiTeamMembershipSourceInterface` (`team_membership_resolver`) |
| `Service\WikiSpaceAccessResolverInterface` | 1.36, notice 1.49 | `Service\WikiSpaceReachInterface` (alias it) |
| `Service\WikiSpaceLocatorInterface` | 1.36, notice 1.49 | `Service\WikiSpaceIdLocatorInterface` (alias it) |
| `Link\WikiObjectLinkResolverInterface` | 1.36, notice 1.49 | `Link\WikiObjectLinkSourceInterface` (autoconfigured with the tag `nowo_wiki.object_link_resolver`) |
| `Avatar\WikiUserAvatarResolverInterface` | 1.36, notice 1.49 | `Avatar\WikiUserAvatarSourceInterface` (alias it) |
| `Panel\WikiPanelStateStoreInterface` | 1.36, notice 1.49 | `Panel\WikiPanelStatesInterface` (`comments.state_store`) |
| `Tree\WikiTreeStateStoreInterface` | 1.36, notice 1.49 | `Tree\WikiTreeStatesInterface` (`tree.state_store`) |
| `Collaboration\WikiCollabSubscriberAuthorizerInterface`, `Collaboration\WikiCollabTopicsAuthorizerInterface` | 1.36, notice 1.49 | `Collaboration\WikiCollabSubscriptionPolicyInterface` (`collaboration.subscriber_authorizer`) |
| `Api\Hook\WikiApiAuthenticatorInterface` | 1.36, notice 1.49 | `Api\Hook\WikiApiRequestAuthenticatorInterface` (`api.hooks.authenticator`) |
| `Api\Hook\WikiApiFilesInterface` | 1.36, notice 1.49 | `Api\Hook\WikiApiFileStoreInterface` (`api.hooks.files`) |
| `Api\Hook\WikiApiPlanGateInterface` | 1.36, notice 1.49 | `Api\Hook\WikiApiPlanPolicyInterface` (`api.hooks.plan_gate`) |
| `Api\Hook\WikiApiDirectoryInterface` | 1.36, notice 1.49 | `Api\Hook\WikiApiWikiDirectoryInterface` (`api.hooks.directory`) |
| `Ai\WikiAiAssistantInterface` | 1.36, notice 1.49 | `Ai\WikiAiAnswererInterface` (alias it) |
| `Attachment\WikiAttachmentLimitsProviderInterface` | 1.36, notice 1.49 | `Attachment\WikiAttachmentLimitsPolicyInterface` (alias it) |
| `WikiApiPrincipal::$user`, `WikiQuotaCheckEvent::$user`, `WikiEditorOptionsEvent::$user` (and their `@internal` `securityUser()`) | 1.36, notice 1.49 | `userRef()` (a `WikiUserRef`; `userRef()->user()` gives back the object) |
| `WikiStorageFailureSubscriber::committed()` | 1.48, notice 1.49 | `Nowo\TransactionDoctrine\ConflictCode::forRequest()` |
| `Interchange\HtmlToMarkdown\WikiAlignedHeaderConverter` | 1.40, notice 1.49 | `WikiHeadingIdConverter` |
| `route_prefix` | 1.10 | `web_ui.path_prefix` |
| `templates.layout`, `templates.<page>` | 1.10 | `web_ui.layout_template`, `web_ui.templates.<page>` |
| `web_ui.css_framework` (and its seeding of `nowo_ui_kit.css_framework` / `icon_set`, the parameter `nowo_wiki.web_ui.css_framework`) | 1.10 | `nowo_ui_kit.css_framework` / `icon_set` |
| `security.admin_roles`, `list_roles`, `create_roles`, `edit_roles`, `history_roles`, `archive_roles`, `ai_roles`, `import_roles`, `export_roles` | 1.10 | `security.roles.<action>` |
| `firewall` (and the parameter `nowo_wiki.firewall`), `database.driver`, `database.platform` | 1.6 | remove them (they never had an effect) |
| `@NowoWikiBundle/manage/_legacy_layout.html.twig`, `WikiPages::LEGACY_ADMIN_BRIDGE`, the variables `nowo_wiki_legacy_layout` / `nowo_wiki_legacy_blocks` | 1.10 | a layout that renders the UiKit canonical blocks (or `web_ui.layout_template: null`) |
| The inline forms of `_new_page_dialog.html.twig` (`new_page_dialog.form`), `_import_export_dialog.html.twig` (`import_form`, `export_links`), `_ai_dialog.html.twig` (`ai_form`), `_tree_menu.html.twig` (`icon_form`, `move_form`) | 1.53 | the on-demand dialogs (`new_page_dialog.url`, `import_export_src`, `ai_dialog`, `tree_menu.icon_src` / `move_src`) |
| `wiki-code-blocks.ts` and the `data-code-label` attribute of the `wiki.js` script tags | 1.29 | UiKit `js/nowo-ui-scroll-region.js` (loaded by the read view) |
| The REST API match of a firewall user and a token user of different classes that only share an id | 1.36 (log notice) | the same user, or the adapter class in `accepted_user_classes` |
| `Doctrine\WikiSpaceCollationListener::postGenerateSchemaTable()` | 1.49.1 | none (a no-op; the collation is applied on `postGenerateSchema`) |

What fails loudly:

- a removed configuration key fails the build with the key to use (`The option "nowo_wiki.route_prefix" was removed in
  2.0 (deprecated since 1.10): use "nowo_wiki.web_ui.path_prefix"`);
- `database.private_entity_manager: false` fails the build;
- a service configured for a port (its configuration key, or an alias of the twin) whose class does not implement the
  twin fails the build naming the key and the twin; so do a service or alias registered under a removed interface's
  id, a decorator of a removed id (`#[AsDecorator]`, `decorates:`, also with `onInvalid: IGNORE`) and an autodiscovered
  class that still implements a removed interface (Symfony would otherwise drop it as unused, and a restriction of yours
  would silently stop applying);
- a layout without the UiKit canonical blocks fails when a wiki page renders, naming the layout;
- a host template that includes a dialog partial with only the 1.35–1.52 variables renders the empty on-demand dialog
  (pass the 1.53 variables).

**Decorators.** A decorator of a removed port id (for example of `WikiSpaceAccessCheckerInterface` to restrict
access) has no replacement hook: implement the twin with your whole rule and configure it with the port's key or alias
the twin to it. The bundle's own services are asked through `@internal` interfaces of the same names in
`Nowo\WikiBundle\Port\Internal\`; they carry no BC promise. A class of yours configured as a port (its key, or an
alias of the twin) that implements one instead of the twin is refused; decorating a bundle service through one of them
works, but carries no BC promise either.

**Consumers of the removed interfaces.** Code of yours that injected a removed interface to ask it (for example
`WikiSpaceAccessCheckerInterface` or `WikiSpaceAccessResolverInterface`) asks the read ports instead
(`WikiPageAccessInterface`, `WikiSpaceQueryInterface`, `WikiPageQueryInterface`, 1.51) or your own twin service.

### Deprecated for 3.0

- Building a `WikiApiPrincipal` with its constructor (a Symfony user) triggers a deprecation notice; 3.0 removes it.
  Answer `WikiApiPrincipal::of()` with a `WikiUserRef` from your authenticator (REQ-ARCH-001 rule 3:
  `new WikiUserRef($user::class, $id, $user->getUserIdentifier(), $user->getRoles(), $user)` keeps your user object
  for the wiki's ownership checks).
- `database.private_entity_manager: true` (no effect since 2.0) triggers a deprecation notice; remove the key, 3.0
  refuses it.

### Composer

No package moves to `suggest`; the requirements are those of 1.55.2.

### Unchanged

The schema, the routes and their answers, the REST API, co-editing, comments, search, the conflict answers
(`CONFLICT_001` / `CONFLICT_002`), the HTML sanitizer, the access rules of the bundle's default checkers, and the
worker-mode resets.

### Host checklist

Search your application for each item; every hit is a change to make on 1.55.2 (where it works and the notices tell
you) or in the 2.0 bump.

| Search your code / config for | Change |
|---|---|
| `database.private_entity_manager` | on 1.55.1 set it to `true` and run a deploy cycle with a clean probe log; in the 2.0 bump drop the line (`false` fails the build, `true` is a deprecated no-op) and the deprecation-log allowance for its 1.49 notice |
| `persist(new WikiSpace(` / `WikiPage` / ... with your manager, `$space->set…()` / `$page->set…()` then your `flush()` | the wiki's id operations: `WikiSpaceOperationsInterface::createSpaceFrom()` / `createSpace()`, `WikiSpaceLifecycleInterface` (`renameSpace()`, `archiveSpace()`, `eraseSpace()`), `WikiPageOperationsInterface` |
| An entity of yours with a Doctrine relation to `WikiSpace` / `WikiPage` (a profile, a grant, a share), filled with an instance a wiki service returned | `$em->getReference(WikiSpace::class, $id)` on your manager, or store the id in a plain column |
| A wiki row loaded with your manager (`$em->getRepository(WikiSpace::class)->find…`, a route value resolver) handed to a wiki service | nothing to change for the write (the wiki loads it again by id), but what you changed on your instance is not carried over: change it through the wiki |
| A listener of a wiki event (`WikiEvents::*`) or a port of yours that changes your entities without flushing | flush your own manager (the wiki no longer does) |
| A Doctrine `onFlush` / `postFlush` listener of yours that reacts to wiki rows | it now runs on the wiki's private manager flush (same connection, mapping and listeners): it must not rely on your manager's unit of work there |
| The 17 removed interfaces ([Removed](#removed)) in `implements`, `#[AsAlias]`, `#[AsDecorator]`, `decorates:`, constructor types, service ids | the 1.36 twin |
| `->user` on `WikiApiPrincipal`, `WikiQuotaCheckEvent`, `WikiEditorOptionsEvent` | `userRef()` (`userRef()->user()` gives back the object) |
| `new WikiApiPrincipal($user, …)` in your API authenticator | still works with a deprecation notice (removed in 3.0): `WikiApiPrincipal::of(new WikiUserRef($user::class, $id, $user->getUserIdentifier(), $user->getRoles(), $user), …)`; keep the user object in the ref (an id-only `WikiUserRef::ofId()` answers 500 on every call, audit ZBL-3) |
| `implements WikiSpaceRepositoryInterface` / `implements WikiPageServiceInterface` | add `findByIds()` + `findByIdWithoutRefresh()` / `createInSpace()` |
| `symfony/ux-icons`, `twig/twig` in your own lock | update them (ux-icons sanitizer advisories CVE-2026-133775..133779; the wiki renders editor-chosen page icons through `ux_icon()`); the bundle's locks are updated, yours are not (audit ZBL-4) |
| `route_prefix`, `templates:` directly under `nowo_wiki`, `firewall`, `database.driver` / `platform`, `web_ui.css_framework`, `security.*_roles` | the key in [Removed](#removed) (each fails the build naming it) |
| `web_ui.layout_template` pointing at a layout without the UiKit canonical blocks | render the canonical blocks or set it to `null` |
| Overrides of `_new_page_dialog`, `_import_export_dialog`, `_ai_dialog`, `_tree_menu` passing the 1.35–1.52 variables | the 1.53 on-demand variables |
| `WikiStorageFailureSubscriber::committed()`, `WikiAlignedHeaderConverter`, `postGenerateSchemaTable()`, `data-code-label` | see [Removed](#removed) |
| A security adapter as firewall user whose class is not `user_class` and not in `accepted_user_classes` (REST API) | add it to `accepted_user_classes` or return the same user from the firewall and the authenticator |

### 1.x support

After 2.0.0 is released, the 1.x line receives security fixes for 6 months: fixes rated Moderate and above are
backported to the last 1.x minor; Low ones are fixed in 2.x only (upgrade to get them).

## To 1.56.0

From **1.55.2**:

```bash
composer update nowo-tech/wiki-bundle
```

### Space icon by id (1.56)

From **1.55.2**: `composer update nowo-tech/wiki-bundle`. Nothing to change unless you use the new call.

- Change a space's icon with `WikiSpaceOperationsInterface::setSpaceIcon($spaceId, $icon)` (or the lifecycle's, with the
  person and the expected version): never load the `WikiSpace` and call `setIcon()` + your own `flush()`, which the
  private entity manager does not see.
- `WikiSpaceOperationsInterface` and `WikiSpaceLifecycleInterface` gain `setSpaceIcon()`: a class of yours that
  implements either (a test double) must add it.
- `WikiSpaceLifecycleAction` gains the case `Icon`: a `WikiSpaceAccessCheckEvent` listener that `match`es every case
  without a `default` must handle it, and a listener that grants every action but some ("grant all except purge") now
  grants icon changes too: add `Icon` to its exceptions if that person must not change icons. A listener of
  `WikiSpaceLifecycleEvent` that tells the events apart by `$action` sees `Icon` with `SPACE_ICON_CHANGED`.
- `WikiSpaceStateException` gains an optional second argument (the action, for the message only): an icon change of
  an archived space says "…before changing its icon."; the reason stays `ARCHIVED`.

## To 1.55.2

From **1.55.1**:

```bash
composer update nowo-tech/wiki-bundle
```

### Private entity manager: host-loaded spaces and pages (1.55.2)

From **1.55.1**: `composer update nowo-tech/wiki-bundle`. Nothing to change with the shared entity manager (the 1.x
default), unless you hand over detached or never-persisted instances (last points). With `database.private_entity_manager: true` (required by 2.0):

- The wiki reads every space or page you hand it again by id through its own manager and writes to that instance, not
  to yours. Covered: the deprecated `WikiApiDirectoryInterface` (`find()`, `provision()`) and `WikiSpaceLocatorInterface`;
  `WikiPageService` (`create()`, `createInSpace()`, `saveRevision()`, `restore()`, `convertToMarkdown()`, `archive()`,
  `unarchive()`), `WikiPageLinkService` (`link()`, `unlink()`), `WikiPageIconService::setIcon()`, `WikiPageMover`
  (`move()`, `createAt()`, `createNextTo()`), `WikiPageDuplicator::duplicate()`, `WikiTrashService` (`delete()`,
  `restore()`, `purge()`) and `WikiAttachmentService::upload()`. So after `$pages->saveRevision($page, …)` with a page of your manager, read the returned revision (or load the page
  again) rather than your `$page`.
- A space must be **stored** before you hand it over: a `WikiApiDirectoryInterface::provision()` or
  `WikiSpaceLocatorInterface::locate()` that persists a new `WikiSpace` must flush it (and commit it, when your manager
  uses another connection than the wiki's) before returning it. A space the wiki cannot read is refused with a
  `LogicException` and nothing is written (1.55.1 failed later, with Doctrine's "A new entity was found through the
  relationship 'WikiPage#space'"). The API and UI guards answer such a space, or one purged meanwhile, with 404. Better: implement the framework-free twins (`WikiApiWikiDirectoryInterface`,
  `WikiSpaceIdLocatorInterface`), which return ids, or create spaces with `WikiSpaceOperationsInterface`.
- Shared manager too: a detached `WikiSpace` / `WikiPage` (no manager holds it) is read again by id and written through
  the managed instance; a `WikiSpace` you never persisted is refused early with the same `LogicException`.
- Holding a space id? `WikiPageService::createInSpace($spaceId, …)` (or `WikiPageOperationsInterface::createPage()`).
- Access checkers that load spaces one by one: autowire `Repository\WikiSpaceBatchFinderInterface` and use
  `findByIds($ids)` (one query per 500 ids, fresh). `findByIdWithoutRefresh()` only on read paths within one request: it may return a
  state another worker changed since (FrankenPHP worker mode included). Keep `findById()` on write paths.

## To 1.55.1

From **1.55.0**:

```bash
composer update nowo-tech/wiki-bundle
```

### Erasure checks (1.55.1)

From **1.55.0**: `composer update nowo-tech/wiki-bundle`.

- `eraseSpace()` refuses an id that is not a lowercase UUID (`InvalidArgumentException`). Pass `WikiSpaceSnapshot::$id`
  exactly as the wiki gave it; never normalise or rebuild it.
- Pass the expected owner on every erasure: `eraseSpace($id, null, WikiSpaceOwnerScope::Team, $ownerId)`; another owner
  is `WikiSpaceOwnerMismatchException` (nothing written).
- Read `$result->alreadyGone()` (nothing was there) and `$result->needsFileSweep()` (run
  `WikiAttachmentService::purgeStrayFiles()` and your page-file sweep) before reporting an erasure complete.
- A `WikiCommentByIdFinderInterface` of your own (new in 1.55.0) must add `findThreadComments(string $threadId, int $limit): array`;
  a class implementing `WikiSpaceLifecycleInterface` must take the new optional `eraseSpace()` parameters.

## To 1.55.0

From **1.54.0**:

```bash
composer update nowo-tech/wiki-bundle
```

### Space create, erasure and comment reads by id (1.55)

From **1.54.0**: `composer update nowo-tech/wiki-bundle`. Nothing to change unless you want the new operations; they
are what an application needs to turn on `database.private_entity_manager: true` without writing wiki rows itself
(see [USAGE › Creating and erasing spaces by id](USAGE.md#creating-and-erasing-spaces-by-id-155)):

| You did (with your entity manager) | Use instead |
|---|---|
| `persist(new WikiSpace(...))` + `flush()` | `WikiSpaceOperationsInterface::createSpaceFrom(new WikiNewSpace($slug, $name, $ownerId, icon: ...))` (keep the returned id) |
| `findBy(['ownerScopeId' => ...])` on `WikiSpace`, then archive / purge each | `WikiSpaceQueryInterface::findSpaces(WikiSpaceCriteria::forOwners(...))`, then `WikiSpaceLifecycleInterface::eraseSpace($id)` after your erasure committed |
| `getRepository(WikiComment::class)->findBy(['thread' => ...])` | `WikiPageOperationsInterface::findThreadComments($threadId)` (`findCommentThread()`, `findComment()`, `findPageRevision()`) |
| Your own rows that point at a `WikiSpace` (a `ManyToOne`) | a plain id column holding `WikiSpaceSnapshot::$id` |

Behaviour changes:

- `WikiSpaceOperationsInterface::createSpace()` now writes through the wiki's runner with one `INSERT`: a slug taken
  meanwhile is `WikiSlugTakenException` (still an `InvalidArgumentException`); a blank or longer than 255 characters
  name is refused (`InvalidArgumentException`), and the name is stored trimmed; Doctrine `prePersist` / `postPersist`
  listeners no longer see the new space (listen to nothing, or read it back by id).
- A slug that a configured `tenant_paths` `slug_requirement` matches is accepted by `createSpace()`, `createSpaceFrom()`
  and `renameSpace()` (lowercase letters, digits and dashes only).
- A class of your own that implements `WikiSpaceOperationsInterface`, `WikiPageOperationsInterface` or
  `WikiSpaceLifecycleInterface` (a test double) must add the new methods. A `WikiCommentRepositoryInterface` of your own
  needs nothing: without `WikiCommentByIdFinderInterface` the comment reads answer null / an empty list.

## To 1.54.0

From **1.53.0**:

```bash
composer update nowo-tech/wiki-bundle
```

## To 1.53.0

From **1.52.0**:

```bash
composer update nowo-tech/wiki-bundle
```

### Dialogs load on demand

```bash
composer update nowo-tech/wiki-bundle nowo-tech/ui-kit-bundle
php bin/console cache:clear
```

- Requires `nowo-tech/ui-kit-bundle` `^2.68`, and the UiKit dialog script (`js/nowo-ui-dialog.js`, loaded by the UiKit layouts) on the pages. Without it, every opener is a plain link to its own page.
- No configuration change and no new route. `space_import` also answers GET (the Import & export dialog's page). Host `access_control` rules on that path now also apply to the GET.
- Host template overrides:
  - **`manage/space.html.twig`, `page_view.html.twig`, `index.html.twig`** that keep `{{ parent() }}` in `nowo_ui_modals`: nothing to change.
  - Overrides that include the dialog partials themselves pass the new variables, or keep the deprecated inline forms:
    - `_new_page_dialog.html.twig`: `new_page_dialog` is now `{url, editor_type}` (no `form`);
    - `_import_export_dialog.html.twig`: `import_export_src`;
    - `_ai_dialog.html.twig`: no `ai_form` (the index now passes `ai_dialog: true` and `ai_form: null`);
    - `_tree_menu.html.twig`: `tree_menu.icon_src` / `move_src` (`icon_form` / `move_form` are null).
  - Overrides that print `import_form` in the space page with `web_ui.import_export: dialog`: the form is now null there, and the dialog's page builds it.
  - `wiki.js` reads `data-wiki-new-page-url`, `data-wiki-tree-icon-src` and `data-wiki-tree-move-src` on those dialogs. A host that copies the markup keeps those attributes.
- Host tests that look for the dialog forms in the page (`wiki_page_form`, `name="form[icon]"`, `#nowo-wiki-import-export-form`, the AI form) must open the dialog, or request its URL with `X-Nowo-Dialog: 1`.

## To 1.52.0

From **1.51.1**:

```bash
composer update nowo-tech/wiki-bundle
```

### The bundle declares its own identity / access-key columns (REQ-SEC-006)

Nothing to do: the declaration is discovered by autoconfiguration, and a host that does not install
`nowo-tech/migrations-kit-bundle` is unaffected.

What you can **remove**: if you excused this bundle's key columns by hand so the uniform collation gate would pass,
those entries are now redundant — a declared exact-key column is exempt from the uniform gate by construction, and
`nowo:migrations:check-key-collations` checks it under its real owner (`nowo-tech/wiki-bundle`) instead of yours.
Delete them:

```diff
 nowo_migrations_kit:
     collation_check:
         ignore_columns:
-            'wiki_spaces.owner_scope_id': 'Wiki space owner'
-            'wiki_page_links.object_type': 'Wiki linked host object'
-            'wiki_page_links.object_id': 'Wiki linked host object'
```

The quick-access columns (`wiki_page_favourites.user_id`, `wiki_page_visits.user_id`,
`wiki_page_references.target_page_id`) and the stored user id columns of `database.user_reference: id` go the same
way, and so do tables under your own `table_prefix`: the declaration builds every name from the configuration itself.
Keeping an entry is not an error, but it outlives the declaration and goes on excusing a column nothing checks any
more, so drop it.

If the gate now *fails* on one of these columns, it is telling the truth: that table predates the collation listener
(1.31.1 for the space owner, 1.36 for the user ids, 1.37 for the page links, 1.45 for the quick access), or it was
restored from a dump, and it still carries the host's folding default. Run the `ALTER` the check prints (the space
owner's is the one in [To 1.31.1](#to-1311)).

With comments, favourites, recents or the backlink index off — or with `database.user_reference: entity`, where the
user columns are foreign keys and not ids — the columns of that feature are reported as **skipped** with their
setting, never as a failure.

## To 1.51.1

From **1.51.0**:

```bash
composer update nowo-tech/wiki-bundle
```

## To 1.51.0

From **1.50.0**:

```bash
composer update nowo-tech/wiki-bundle
```

Nothing to change: the release only adds services ([USAGE › Read ports and page access by id](USAGE.md#read-ports-and-page-access-by-id-151)).
No schema change, no configuration key, no new dependency.

**Applications that query the wiki's tables** (spaces of an owner, page counts, attachment sizes, ancestors for
breadcrumbs, pages for an export, backlinks by `LIKE`, search) can move those reads to `Query\WikiSpaceQueryInterface`
and `Query\WikiPageQueryInterface`, and the entity-typed `WikiSpaceGuard::isPageReadable()` calls to
`Security\WikiPageAccessInterface` (same decisions, by ids). Then they no longer depend on the wiki's schema or load
its entities into their own entity manager, which also clears the way to `database.private_entity_manager: true`.
Notes when moving:

- The read ports make no access decision (the backlinks and the search do with a viewer): keep filtering for the person
  as before, or pass the result through `WikiPageAccessInterface::readablePageIds()`.
- `WikiPageAccessInterface` decides as the guard does, which does not check that a page is live: an archived or trashed
  page of an active space is readable. Code that passes raw ids filters archived and trashed pages itself
  (`findPagesByIds()` with `archivedAt === null`, or `WikiArchiveFilter::Active`), as the wiki's own pages do.
- Owner ids are refused when unusable (`InvalidArgumentException`): an empty string padding an `IN ()` list is no longer
  needed, an empty list already matches nothing.
- `WikiArchiveFilter` reads the wiki's own archive state (1.50 `WikiSpace::$archivedAt`, `WikiPage::$archivedAt`). An
  application that keeps its own "archived" flag for spaces filters on it as before.
- Without `backlinks.enabled`, `backlinks()` scans the current revisions (bounded by `backlinks.candidates`); turn the
  index on (and run `wiki:backlinks:rebuild` once) for large wikis.

## To 1.50.0

From **1.49.1**:

```bash
composer update nowo-tech/wiki-bundle
```

**Schema.** `WikiSpace` gets three columns: `archived_at` and `purge_started_at` (nullable timestamps) and
`lock_version` (integer, default 1). The bundle ships no migrations: generate one (`doctrine:migrations:diff`) or run
the statements for your database (adjust the table name if you changed `table_prefix`):

```sql
-- MySQL / MariaDB
ALTER TABLE wiki_spaces ADD archived_at DATETIME DEFAULT NULL, ADD purge_started_at DATETIME DEFAULT NULL, ADD lock_version INT DEFAULT 1 NOT NULL;
-- PostgreSQL
ALTER TABLE wiki_spaces ADD archived_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ADD purge_started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ADD lock_version INT DEFAULT 1 NOT NULL;
-- SQLite
ALTER TABLE wiki_spaces ADD COLUMN archived_at DATETIME DEFAULT NULL;
ALTER TABLE wiki_spaces ADD COLUMN purge_started_at DATETIME DEFAULT NULL;
ALTER TABLE wiki_spaces ADD COLUMN lock_version INTEGER DEFAULT 1 NOT NULL;
```

Existing spaces stay active (`archived_at` null, version 1). Without the columns every wiki query fails, so run the
migration with the update.

**Archived spaces.** Nothing changes until a space is archived through `Space\WikiSpaceLifecycleInterface`. An
archived space is then read-only and open only to the people your space checker grants `Manage` on it: the bundle
decorates `WikiSpaceAccessCheckerInterface` with `WikiArchivedSpaceAccessChecker`, around the service your alias names.
Code that injects `WikiSpaceAccessCheckerInterface` gets the decorated checker; code that injects your checker by its
own class does not.

**Page creates** now lock the space row (as moves and uploads already did) and are refused in an archived space.
**Host-object link lists** (`WikiPageLinkRepositoryInterface::findByHostObject(s)`) leave out the pages of archived
spaces.

**Applications that wrote the wiki's tables** (renaming a space, deleting a space with its pages) should call the
service instead: [USAGE › Space lifecycle](USAGE.md#space-lifecycle-rename-archive-purge-150). A space deleted with a
plain `DELETE` relied on foreign-key cascades and left the tables without foreign keys (references, co-editing,
idempotency records) behind; the purge clears them. An application that keeps its own "archived" flag can keep it, or
archive the space as well so the wiki hides it from search, favourites and recents. Call the purge outside any
transaction, and keep (or add) a nightly `WikiAttachmentService::purgeStrayFiles()` for files a failed unlink left.

## To 1.49.1

From **1.49.0**:

```bash
composer update nowo-tech/wiki-bundle
```

## To 1.49.1

From **1.49.0**:

```bash
composer update nowo-tech/wiki-bundle
```

### Missing foreign keys on MySQL / MariaDB (1.36.0 to 1.49.0)

Only MySQL / MariaDB databases whose wiki tables were created or migrated with doctrine/orm 3.7.0 to 3.8.0 and
doctrine/dbal 4.5 or later are affected (PostgreSQL and SQLite are not). After the update, check the schema:

```bash
php bin/console doctrine:schema:validate
# or, with migrations: php bin/console doctrine:migrations:diff
```

If it reports missing foreign keys, first remove the rows they would reject, then add the indexes and foreign keys.
The names below are those of the default `table_prefix: wiki_`; with another prefix the generated names differ, so
take them from `php bin/console doctrine:schema:update --dump-sql` (run the statements it prints for these tables only,
after the clean-up). Run the statements of the tables your database lacks the foreign key on.

**Every mode** (`wiki_page_links` from 1.37.0; favourites, visits and references from 1.45.0):

```sql
-- Rows of deleted pages (ON DELETE CASCADE would have deleted them): check, then delete.
SELECT COUNT(*) FROM wiki_page_links l LEFT JOIN wiki_pages p ON p.id = l.page_id WHERE p.id IS NULL;
DELETE l FROM wiki_page_links l LEFT JOIN wiki_pages p ON p.id = l.page_id WHERE p.id IS NULL;
DELETE f FROM wiki_page_favourites f LEFT JOIN wiki_pages p ON p.id = f.page_id WHERE p.id IS NULL;
DELETE v FROM wiki_page_visits v LEFT JOIN wiki_pages p ON p.id = v.page_id WHERE p.id IS NULL;
DELETE r FROM wiki_page_references r LEFT JOIN wiki_pages p ON p.id = r.source_page_id WHERE p.id IS NULL;

CREATE INDEX IDX_8B0B8A06C4663E4 ON wiki_page_links (page_id);
ALTER TABLE wiki_page_links ADD CONSTRAINT FK_8B0B8A06C4663E4 FOREIGN KEY (page_id) REFERENCES wiki_pages (id) ON DELETE CASCADE;
CREATE INDEX IDX_7954510CC4663E4 ON wiki_page_favourites (page_id);
ALTER TABLE wiki_page_favourites ADD CONSTRAINT FK_7954510CC4663E4 FOREIGN KEY (page_id) REFERENCES wiki_pages (id) ON DELETE CASCADE;
CREATE INDEX IDX_BE1D8DA2C4663E4 ON wiki_page_visits (page_id);
ALTER TABLE wiki_page_visits ADD CONSTRAINT FK_BE1D8DA2C4663E4 FOREIGN KEY (page_id) REFERENCES wiki_pages (id) ON DELETE CASCADE;
CREATE INDEX IDX_994DB9914599DB8C ON wiki_page_references (source_page_id);
ALTER TABLE wiki_page_references ADD CONSTRAINT FK_994DB9914599DB8C FOREIGN KEY (source_page_id) REFERENCES wiki_pages (id) ON DELETE CASCADE;
```

**`database.user_reference: entity`** (the default; from 1.37.0): the creator of a link points at your user table.
Replace `app_user` and `id` with your `user_class` table and its id column:

```sql
-- Links of deleted users keep the link, without its creator (ON DELETE SET NULL).
UPDATE wiki_page_links l LEFT JOIN app_user u ON u.id = l.created_by_id SET l.created_by_id = NULL WHERE l.created_by_id IS NOT NULL AND u.id IS NULL;

CREATE INDEX IDX_8B0B8A06B03A8386 ON wiki_page_links (created_by_id);
ALTER TABLE wiki_page_links ADD CONSTRAINT FK_8B0B8A06B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL;
```

**`database.user_reference: id`** (from 1.36.0): every table with a user id column lost its other foreign keys too.
Delete in this order (threads before comments before reactions):

```sql
DELETE r FROM wiki_page_revisions r LEFT JOIN wiki_pages p ON p.id = r.page_id WHERE p.id IS NULL;
DELETE t FROM wiki_comment_threads t LEFT JOIN wiki_pages p ON p.id = t.page_id WHERE p.id IS NULL;
DELETE c FROM wiki_comments c LEFT JOIN wiki_comment_threads t ON t.id = c.thread_id WHERE t.id IS NULL;
DELETE x FROM wiki_comment_reactions x LEFT JOIN wiki_comments c ON c.id = x.comment_id WHERE c.id IS NULL;
-- Attachment rows of deleted pages or spaces; their files stay in your attachment storage: list them first.
SELECT a.id, a.storage_key FROM wiki_attachments a LEFT JOIN wiki_pages p ON p.id = a.page_id LEFT JOIN wiki_spaces s ON s.id = a.space_id WHERE p.id IS NULL OR s.id IS NULL;
DELETE a FROM wiki_attachments a LEFT JOIN wiki_pages p ON p.id = a.page_id LEFT JOIN wiki_spaces s ON s.id = a.space_id WHERE p.id IS NULL OR s.id IS NULL;

CREATE INDEX IDX_6C9030C0C4663E4 ON wiki_page_revisions (page_id);
ALTER TABLE wiki_page_revisions ADD CONSTRAINT FK_6C9030C0C4663E4 FOREIGN KEY (page_id) REFERENCES wiki_pages (id) ON DELETE CASCADE;
CREATE INDEX IDX_914EFA5EC4663E4 ON wiki_comment_threads (page_id);
ALTER TABLE wiki_comment_threads ADD CONSTRAINT FK_914EFA5EC4663E4 FOREIGN KEY (page_id) REFERENCES wiki_pages (id) ON DELETE CASCADE;
CREATE INDEX IDX_B45893E5E2904019 ON wiki_comments (thread_id);
ALTER TABLE wiki_comments ADD CONSTRAINT FK_B45893E5E2904019 FOREIGN KEY (thread_id) REFERENCES wiki_comment_threads (id) ON DELETE CASCADE;
CREATE INDEX IDX_2DDD1DE8F8697D13 ON wiki_comment_reactions (comment_id);
ALTER TABLE wiki_comment_reactions ADD CONSTRAINT FK_2DDD1DE8F8697D13 FOREIGN KEY (comment_id) REFERENCES wiki_comments (id) ON DELETE CASCADE;
CREATE INDEX IDX_D247B880C4663E4 ON wiki_attachments (page_id);
ALTER TABLE wiki_attachments ADD CONSTRAINT FK_D247B880C4663E4 FOREIGN KEY (page_id) REFERENCES wiki_pages (id) ON DELETE CASCADE;
ALTER TABLE wiki_attachments ADD CONSTRAINT FK_D247B88023575340 FOREIGN KEY (space_id) REFERENCES wiki_spaces (id) ON DELETE CASCADE;
```

`wiki_attachments.space_id` keeps its `wiki_attachment_space` index, so it needs no new one. Run
`php bin/console doctrine:schema:validate` again: the mapping and the database are in sync once it passes.

## To 1.49.0

From **1.48.1**:

```bash
composer update nowo-tech/wiki-bundle
```

## To 1.49.0

From **1.48.1**:

```bash
composer update nowo-tech/wiki-bundle nowo-tech/transaction-doctrine
```

Requires `nowo-tech/transaction-doctrine` `^1.9.1`. No schema change and no behaviour change: every notice below is a
`E_USER_DEPRECATED` (`trigger_deprecation()`), collected by your deprecation log or the profiler, and host mode writes
exactly what it wrote in 1.48. Nothing is needed to keep working; the notices list what 2.0 changes for you.
`$user` on `WikiApiPrincipal`, `WikiQuotaCheckEvent` and `WikiEditorOptionsEvent` is no longer a declared property:
reflection, `get_object_vars()`, array casts and serializer normalisation no longer see it. `->user` still works (with
a notice); use `securityUser()`.

### The private entity manager notice

With `nowo_wiki.database.private_entity_manager` unset or `false` every container build reports:

> Since nowo-tech/wiki-bundle 1.49: nowo_wiki.database.private_entity_manager is false (the 1.x default): every wiki
> write flushes your entity manager "default", including what your application left pending in it. 2.0 removes the
> option: the private entity manager becomes the only mode, and wiki writes never flush, clear or close your manager.
> Opt in now with "nowo_wiki: { database: { private_entity_manager: true } }" once the host-flush probe notices
> ("flushed by NowoWikiBundle", "rows with its own entity manager") are gone; see UPGRADING "To 1.49.0".

To opt in now (the 2.0 behaviour, available since 1.36):

```yaml
nowo_wiki:
    database:
        private_entity_manager: true
```

Read [Private entity manager](#private-entity-manager-databaseprivate_entity_manager-true-the-only-mode-in-20) first,
and turn it on once the probe below reports nothing for a full deploy cycle of your test suite and production traffic.
With it on, the notice and the probe are gone (the probe is not even registered).

### Reading the host-flush probe as a migration list

While the shared manager is on, the bundle reports two kinds of notices, each at most once per class (and kind of
write) per request, Messenger message or console command; a FrankenPHP worker with kernel reset off reports every
request afresh. The probe only reads Doctrine's unit of work: it never flushes, clears, closes, resets or detaches
anything.

1. **Your changes that a wiki write flushed** (nowo-tech/transaction-doctrine's shared probe, right before each wiki
   flush of your manager):

   > Your pending changes to App\Entity\Invoice were flushed by NowoWikiBundle; 2.0 no longer does.

   Your code left `App\Entity\Invoice` changes pending and relied (knowingly or not) on a wiki save to write them. In
   2.0 they stay pending and are lost at the end of the request unless you flush them. Fix: call
   `$entityManager->flush()` yourself where you change them (before or after the wiki call).

2. **Wiki rows your own manager wrote** (a flush the wiki did not run that inserts, updates or deletes wiki rows):

   > Since nowo-tech/wiki-bundle 1.49: Your application inserted Nowo\WikiBundle\Entity\WikiSpace rows with its own
   > entity manager, outside a wiki write. In 2.0 the wiki reads and writes its rows only through its private entity
   > manager, whose instances are not yours: create spaces with
   > Nowo\WikiBundle\Service\WikiSpaceOperationsInterface::createSpace() and keep their id (then set
   > "nowo_wiki.database.private_entity_manager: true").

   The notice names the class, the kind of write and, where the bundle has one, the operation to use instead:

   | Class and write | Use instead |
   |---|---|
   | `WikiSpace` insert | `WikiSpaceOperationsInterface::createSpace()` |
   | `WikiPage` insert | `WikiPageOperationsInterface::createPage()` / `createPageAt()` / `createPageNextTo()` |
   | `WikiPage` update | `WikiPageOperationsInterface::saveRevision()` / `restoreRevision()` / `archivePage()` / `unarchivePage()` |
   | `WikiPageRevision` insert | `WikiPageOperationsInterface::saveRevision()` / `restoreRevision()` |
   | anything else | no id operation yet: load the rows through the wiki (its repositories or the id operations), write them through the wiki's services, and pass ids, not your instances |

   The operations take and return ids and snapshots, so your code no longer holds wiki entities of your manager; compare
   wiki rows by id, never with `===`.

Collect the notices from your deprecation log (`var/log/*.log` with the `php` channel, the profiler's "Deprecations"
tab, or your test suite's deprecation report) and treat each distinct message as one item to migrate. When none is
left, turn the private manager on.

### Other notices of members 2.0 removes

- **Port interfaces:** a service of yours that implements one of the 16 deprecated port interfaces is reported once per
  container build ("The service "app.x" (App\X) implements the deprecated ..., which 2.0 removes: implement ...
  instead"); see [Host ports](#host-ports-move-to-the-framework-free-twins-optional-until-20). The interfaces are
  marked `@deprecated`, so Symfony's debug class loader reports your classes that implement them too.
- **`$user` members:** reading `WikiApiPrincipal::$user`, `WikiQuotaCheckEvent::$user` or `WikiEditorOptionsEvent::$user`
  reports "Reading ...::$user (a Symfony user) is deprecated, 2.0 removes it: read userRef() (a framework-free
  WikiUserRef) instead." The members keep working and stay read-only. Code that builds these objects is unchanged
  (the constructor argument is still named `$user`).
- **`WikiStorageFailureSubscriber::committed()`** and **`new WikiAlignedHeaderConverter()`** report a notice.

## To 1.48.1

From **1.48.0**:

```bash
composer update nowo-tech/wiki-bundle
```

## To 1.48.0

From **1.47.1**:

```bash
composer update nowo-tech/wiki-bundle
```

**REST API clients:** a lock conflict (deadlock, lock wait timeout, serialization failure) now answers
`ECODE: CONFLICT_001` (nothing saved; retry after `Retry-After`) or `ECODE: CONFLICT_002` (part of the request may be
saved; read again before repeating, no `Retry-After`) instead of `SERVER_002`, and a page save that lost on a lock
answers `CONFLICT_001` instead of `WIKI_SAVE_COLLISION`. The same value is in the `X-Conflict-Code` response header.
Clients that retried on `SERVER_002` should retry on `CONFLICT_001` only. The status is unchanged (409, or 503 with
`nowo_transaction_doctrine.conflict_response.status: 503`).

**Internal JSON endpoints:** `{"error": "unavailable"}` gains `"code": "CONFLICT_001" | "CONFLICT_002"`. **Pages:** the
`HttpException` carries `X-Conflict-Code`. A cross-origin client that reads the header needs it in your
`Access-Control-Expose-Headers`.

`WikiStorageFailureSubscriber::committed()` is deprecated (removed in 2.0). No configuration or schema change.

## To 1.47.1

From **1.47.0**:

```bash
composer update nowo-tech/wiki-bundle
```

**Search tenant id of several tenants (1.47.1).** Listeners of SearchKit's item visibility event that read `$event->tenant` for a list of several current tenants now get `tenants:` + the SHA-256 of the JSON array of the sorted keys (it was the keys joined by a line feed). Do not compare it with a stored value; use `WikiPageSearchDefinition::tenantId()` or `WikiSearchSpacesEvent::$tenants`. The id of one key is unchanged. Keys containing control characters or starting with `tenants:` are dropped.

## To 1.47.0

From **1.46.0**:

```bash
composer update nowo-tech/wiki-bundle
```

From **1.46.0**:

```bash
composer update nowo-tech/wiki-bundle
```

Nothing to change: a `search_kit_provider.tenant_resolver` implementing the single-key
`WikiSearchTenantResolverInterface` keeps working (one current tenant).

**Optional — several current tenants.** If people search several wiki tenants at once (an organisation, its projects,
personal wikis, a general wiki), implement `Search\WikiSearchTenantsResolverInterface::currentTenants()` and return
every key (the same keys as your `WikiLinkTenantResolverInterface`); the palette then searches their union. See
[USAGE › Several current tenants](USAGE.md#several-current-tenants-147). Listeners of SearchKit's item visibility event
that read `$event->tenant` get `tenants:<sha256>` for several keys (the key itself for one); the keys are in
`WikiSearchSpacesEvent::$tenants`.

## To 1.46.0

From **1.45.0**:

```bash
composer update nowo-tech/wiki-bundle
```

From **1.45.0**:

```bash
composer update nowo-tech/wiki-bundle
```

Nothing changes unless `search_kit_provider` is on.

**With `search_kit_provider` on** — the SearchKit group now runs on SearchKit's provider base:

- **Update SearchKit to 1.10.1 or later** (`composer require nowo-tech/search-kit-bundle:^1.10.1`, PHP >= 8.3; a
  security requirement: older versions can mark a full candidate window as an exact total) and keep its bundle
  registered. With an older SearchKit (1.8 to 1.10.0) nothing is registered any more (the `wiki` group disappears) until
  you update, and `composer.json` now declares `conflict: nowo-tech/search-kit-bundle <1.10.1`.
- **`search.excluded_spaces`** does not hide spaces from the SearchKit group: exclude them with a
  `nowo_wiki.search_kit.spaces` listener.
- **Shared entity manager:** with the wiki on the host's own entity manager, a search re-reads its pages (`HINT_REFRESH`)
  and overwrites unflushed changes to a `WikiPage` made earlier in the same request; flush first (SECURITY.md).
- **Current tenant only (BC: narrows the results of multi-tenant hosts).** As the favourites / recents lists of 1.45
  (owner decision of 2026-11-19), the palette now searches only the spaces of the **current tenant**:
  `search_kit_provider.tenant: current` (default). A space is searched when its `WikiLinkTenantResolverInterface` key
  equals `Search\WikiSearchTenantResolverInterface::currentTenant()`.
  - Without `tenant_paths` and without your own `WikiLinkTenantResolverInterface`: nothing changes (one tenant).
  - With `tenant_paths`, or with your own tenant resolver: alias your current-tenant resolver
    (`search_kit_provider.tenant_resolver`, same keys) — until you do, the wiki group finds nothing — or set
    `tenant: all` to keep the 1.45 rows (every space the viewer may read).
- **Otherwise the same rows.** The viewer, the role gate, the page rules and the literal one-phrase match are
  unchanged. Differences you may notice:
  - the order: title matches first, then the latest change (before: SearchKit's score over two scans);
  - one window of `candidates` rows per search (before: up to twice that); the total is exact unless the visible rows fill
    the window;
  - "see all" appears with every group, labelled by SearchKit (`search.see_all`), and only links where the wiki's search
    page answers the same question (across spaces with `search.scope: all`, or the one space a `space:` filter or a
    `scope: slug` scope names). With an owner scope there is no link (SearchKit's own results page still pages through
    every row). If you overrode `wiki.search_kit.see_all`, that message is no longer used;
  - a scope id outside SearchKit's scope shape (`[A-Za-z0-9._:-]{1,128}`) finds nothing.
- **Host rules (optional).** To hide spaces (per tenant, owner or feature), listen to `nowo_wiki.search_kit.spaces`
  (`WikiSearchSpacesEvent::exclude()`); to hide single pages, SearchKit's `nowo_search_kit.item_visibility` (subject
  type `wiki`, the page id). Both only narrow, and a deny wins. See
  [USAGE › SearchKit provider › Host rules](USAGE.md#host-rules-146).
- **Recents kept in the browser.** If SearchKit keeps recents without revalidation (`recent.enabled: true`,
  `recent.revalidate: false`) and page titles must not be stored there, set `search_kit_provider.sensitive: true`: the
  group is then off in that mode.
- Hosts that call the provider directly: `WikiPageSearchKitProvider` keeps its class, service id, tags and interfaces,
  but its constructor now takes SearchKit's provider and the new `WikiPageSearchDefinition` (wired by the bundle).

## To 1.45.0

From **1.44.0**:

```bash
composer update nowo-tech/wiki-bundle
```

From **1.44.0**:

```bash
composer update nowo-tech/wiki-bundle nowo-tech/transaction-doctrine
```

`nowo-tech/transaction-doctrine` is now required at `^1.6` (was `^1.3`): the new favourite route answers lock
conflicts with its request-level code (`X-Conflict-Code: CONFLICT_001` / `CONFLICT_002`). Nothing else changes for
existing routes, and nothing changes at all until you turn one of the new switches on (all `false` by default; with
them off the generated schema has no new table, so `doctrine:migrations:diff` stays empty).

**Optional — favourites, recently viewed, backlinks and the rendered diff** (see
[CONFIGURATION](CONFIGURATION.md#favourites-recents-and-backlinks-145) and
[USAGE](USAGE.md#favourites-recents-and-backlinks-145)):

```yaml
nowo_wiki:
    favourites: { enabled: true }   # table <prefix>_page_favourites
    recents: { enabled: true, retention_days: 90 }   # table <prefix>_page_visits
    backlinks: { enabled: true }    # table <prefix>_page_references
    history:
        rendered_diff: true         # editor.type: milkdown only; no table
```

1. Turn the switches on (literal booleans), then **generate the migration** with `bin/console doctrine:migrations:diff`
   and review it: it lists only the tables of the switches you turned on, with your table prefix, and the column
   collations your server uses. That is the recommended path; the DDL below is a reference.
2. With `backlinks.enabled`, fill the index once: `bin/console wiki:backlinks:rebuild` (pages saved before have no
   rows; it is safe to run beside live saves, and again at any time). It exits with FAILURE and lists the pages it
   skipped after lock conflicts; run it again, or resume with `--after=<page id>`.
3. With `recents.enabled`, schedule `bin/console wiki:recents:purge` (for example daily): visits older than
   `recents.retention_days` (90) are deleted in bounded batches.
4. If your page view showed backlinks of its own in `wiki_page_after_body` (Beacon: `wiki_backlinks()` in
   `templates/wiki/manage/page_view.html.twig`), remove them, or override the new block `wiki_page_backlinks` with an
   empty block to keep yours.
5. Favourites and visits are personal data. `wiki:comments:erase-user` now exports (`quick_access`) and erases them with
   the rest of a person's wiki data, and `WikiCommentService::eraseAuthor()` erases them through
   `nowo_wiki.comment.author_erased`; `wiki:quick-access:erase-user <id>` does only these rows. Both are always
   registered and act on every table that exists, whatever the switches.
6. Lists show only pages of the **current tenant**: the tenant key of `LinkPicker\WikiLinkTenantResolverInterface`
   (`tenant_paths` by default). A host with real tenants (Beacon: organisations) aliases it to its own resolver, as
   for the link picker.
7. A host template that overrides `manage/_sidebar.html.twig` should include `manage/_quick_access.html.twig`
   (variable `quick_access`) above the tree, as the bundle's template does.

The person column `user_id` (favourites, visits) and the linked page id `target_page_id` (references; a page id, not a
user id: a link to a page that does not exist yet has no foreign key) get the exact binary collation on MySQL / MariaDB
(as `owner_scope_id`), so `Alice` is not `alice`; PostgreSQL compares exactly by default.

**Foreign key columns on MySQL / MariaDB.** `page_id` (favourites, visits) and `source_page_id` (references) must have
the same character set and collation as `wiki_pages.id`, or the foreign key is refused (MySQL error 3780, MariaDB errno
150). `doctrine:migrations:diff` takes them from your mapping and server; when you write the DDL by hand, read the
character set and collation first and put them in place of `<pages_id_charset>` and `<pages_id_collation>` below:

```sql
SHOW CREATE TABLE wiki_pages;   -- the `id` column line shows its CHARACTER SET / COLLATE (or the table default)
SELECT CHARACTER_SET_NAME, COLLATION_NAME FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wiki_pages' AND COLUMN_NAME = 'id';
```

The same two queries work on MySQL 8.4 and MariaDB 11.4 (for example `utf8mb4` with `utf8mb4_unicode_ci`,
`utf8mb4_0900_ai_ci` on a MySQL 8 default, `utf8mb4_uca1400_ai_ci` on a MariaDB 11.4 default; an older table may be
`utf8mb3` / `utf8`).

MySQL 8.4:

```sql
CREATE TABLE wiki_page_favourites (id VARCHAR(36) NOT NULL, created_at DATETIME NOT NULL, user_id VARCHAR(128) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_bin`, page_id VARCHAR(36) CHARACTER SET <pages_id_charset> NOT NULL COLLATE `<pages_id_collation>`, INDEX wiki_page_favourite_user (user_id, created_at), UNIQUE INDEX wiki_page_favourite_unique (user_id, page_id), INDEX IDX_7954510CC4663E4 (page_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4;
CREATE TABLE wiki_page_visits (id VARCHAR(36) NOT NULL, user_id VARCHAR(128) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_bin`, visited_at DATETIME NOT NULL, page_id VARCHAR(36) CHARACTER SET <pages_id_charset> NOT NULL COLLATE `<pages_id_collation>`, INDEX wiki_page_visit_user (user_id, visited_at), UNIQUE INDEX wiki_page_visit_unique (user_id, page_id), INDEX wiki_page_visit_time (visited_at), INDEX IDX_BE1D8DA2C4663E4 (page_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4;
CREATE TABLE wiki_page_references (id VARCHAR(36) NOT NULL, target_page_id VARCHAR(36) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_bin`, source_page_id VARCHAR(36) CHARACTER SET <pages_id_charset> NOT NULL COLLATE `<pages_id_collation>`, INDEX wiki_page_reference_target (target_page_id, source_page_id), UNIQUE INDEX wiki_page_reference_unique (source_page_id, target_page_id), INDEX IDX_994DB9914599DB8C (source_page_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4;
ALTER TABLE wiki_page_favourites ADD CONSTRAINT FK_7954510CC4663E4 FOREIGN KEY (page_id) REFERENCES wiki_pages (id) ON DELETE CASCADE;
ALTER TABLE wiki_page_visits ADD CONSTRAINT FK_BE1D8DA2C4663E4 FOREIGN KEY (page_id) REFERENCES wiki_pages (id) ON DELETE CASCADE;
ALTER TABLE wiki_page_references ADD CONSTRAINT FK_994DB9914599DB8C FOREIGN KEY (source_page_id) REFERENCES wiki_pages (id) ON DELETE CASCADE;
```

MariaDB 11.4: the same statements with `COLLATE utf8mb4_nopad_bin` instead of `utf8mb4_0900_bin` for `user_id` and
`target_page_id` (and your `<pages_id_charset>` / `<pages_id_collation>` for the foreign key columns). The index
`wiki_page_visit_time` serves `wiki:recents:purge` (visits older than the retention, across people).

PostgreSQL 16:

```sql
CREATE TABLE wiki_page_favourites (id VARCHAR(36) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id VARCHAR(128) NOT NULL, page_id VARCHAR(36) NOT NULL, PRIMARY KEY (id));
CREATE INDEX wiki_page_favourite_user ON wiki_page_favourites (user_id, created_at);
CREATE UNIQUE INDEX wiki_page_favourite_unique ON wiki_page_favourites (user_id, page_id);
CREATE INDEX IDX_7954510CC4663E4 ON wiki_page_favourites (page_id);
CREATE TABLE wiki_page_visits (id VARCHAR(36) NOT NULL, user_id VARCHAR(128) NOT NULL, visited_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, page_id VARCHAR(36) NOT NULL, PRIMARY KEY (id));
CREATE INDEX wiki_page_visit_user ON wiki_page_visits (user_id, visited_at);
CREATE UNIQUE INDEX wiki_page_visit_unique ON wiki_page_visits (user_id, page_id);
CREATE INDEX wiki_page_visit_time ON wiki_page_visits (visited_at);
CREATE INDEX IDX_BE1D8DA2C4663E4 ON wiki_page_visits (page_id);
CREATE TABLE wiki_page_references (id VARCHAR(36) NOT NULL, target_page_id VARCHAR(36) NOT NULL, source_page_id VARCHAR(36) NOT NULL, PRIMARY KEY (id));
CREATE INDEX wiki_page_reference_target ON wiki_page_references (target_page_id, source_page_id);
CREATE UNIQUE INDEX wiki_page_reference_unique ON wiki_page_references (source_page_id, target_page_id);
CREATE INDEX IDX_994DB9914599DB8C ON wiki_page_references (source_page_id);
ALTER TABLE wiki_page_favourites ADD CONSTRAINT FK_7954510CC4663E4 FOREIGN KEY (page_id) REFERENCES wiki_pages (id) ON DELETE CASCADE NOT DEFERRABLE;
ALTER TABLE wiki_page_visits ADD CONSTRAINT FK_BE1D8DA2C4663E4 FOREIGN KEY (page_id) REFERENCES wiki_pages (id) ON DELETE CASCADE NOT DEFERRABLE;
ALTER TABLE wiki_page_references ADD CONSTRAINT FK_994DB9914599DB8C FOREIGN KEY (source_page_id) REFERENCES wiki_pages (id) ON DELETE CASCADE NOT DEFERRABLE;
```

With another `table_prefix` the table names change; the generated migration names the indexes and keys for you.

## To 1.44.0

From **1.43.0**:

```bash
composer update nowo-tech/wiki-bundle
```

From **1.43.0**:

```bash
composer update nowo-tech/wiki-bundle
```

Nothing to change: the new SearchKit provider is off by default and registers nothing until you turn it on.

**Optional — wiki pages in SearchKit's "search everywhere"** (Beacon KIT-85). With SearchKitBundle >= 1.8
(`composer require nowo-tech/search-kit-bundle:^1.8`, PHP >= 8.3):

```yaml
nowo_wiki:
    search_kit_provider: true
```

Then remove any host provider that searched the wiki tables itself (Beacon: `App\Search\Provider\WikiPageSearchProvider`;
see [USAGE › SearchKit provider › Beacon](USAGE.md#beacon-replacing-wikipagesearchprovider)). Check, before you do:

- **Who is the viewer.** SearchKit hands providers a user identifier only. By default the bundle loads it through
  `security.user_providers`; the loaded user must carry exactly that identifier. Set `search_kit_provider.viewer_resolver`
  when your identifiers need another lookup.
- **Which spaces.** The provider searches the spaces your `team_membership_resolver` (or `space_access_resolver` /
  `space_reach` port) lists for the viewer and your space checker opens. A space they do not list is never searched.
- **The role gate.** The provider asks `security.access_checker` `canList()` for the viewer. 1.44: the default
  `ConfigurableWikiAccessChecker` decides a user other than the session's user on that user's own roles
  (`Security::isGrantedForUser()`) — this also applies to every other wiki call made for another user than the session's
  (it used to read the session's token). The session's own user is decided on the token as before; outside a request
  (a console `searchFor()`) it still denies. A host checker should likewise decide for its user argument.
- **The user provider.** With several Symfony user providers, set `search_kit_provider.user_provider` to the firewall's
  own provider (`security.user.provider.concrete.<name>`), not the chain.
- **The scope.** A SearchKit scope id selects the spaces whose owner id it is (`scope: owner`, e.g. a project UUID) or
  the space with that slug (`scope: slug`).

**Focus mode Pages drawer.** It now starts below the top bar and UiKit's focus bar, has a close button and a backdrop,
and one Escape closes only the drawer. Remove a host workaround that moved the drawer (Beacon:
`assets/styles/_focus_mode.scss`); with a UiKit older than 2.57, or a top bar UiKit does not measure, set
`--nowo-wiki-focus-drawer-top` on the shell. A host template that overrides `manage/_sidebar.html.twig` should add the
close button (see the bundle's template).

## To 1.43.0

From **1.42.0**:

```bash
composer update nowo-tech/wiki-bundle
```

From **1.42.0**, nothing to change: the new `--nowo-wiki-page-card-*`, `--nowo-wiki-read-max-width` and `--nowo-wiki-read-h{1,2,3}-weight` tokens default to the 1.42 values. A host that styled `.nowo-wiki-page-card`, `.nowo-wiki-page-title` or `.nowo-wiki-page-head` itself keeps working; it can replace those rules with the tokens (see [USAGE › Theming the page card](USAGE.md#theming-the-page-card-143)).

## To 1.42.0

From **1.41.0**:

```bash
composer update nowo-tech/wiki-bundle
```

From **1.41.0**:

```bash
composer update nowo-tech/wiki-bundle nowo-tech/transaction-doctrine
```

**409 (was 503).** A transient storage failure on a wiki route (deadlock, lock wait timeout, `TransientStorageFailure`)
now answers **409 Conflict + `Retry-After`**, the status of the shared `ConflictResponsePolicy` of
nowo-tech/transaction-doctrine 1.3 (required `^1.3`). JSON field names (`{"err", "ECODE": "SERVER_002"}` in the REST
API, `{"error": "unavailable"}` in the internal endpoints) and the unique-key 409 (`WIKI_CONFLICT`, `{"error": "conflict"}`)
are unchanged. `nowo_transaction_doctrine.conflict_response.status: 503` keeps the old status:

```yaml
# config/packages/nowo_transaction_doctrine.yaml
nowo_transaction_doctrine:
    conflict_response:
        status: 503   # 409 (default) or 503
        retry_after: 1
```

- **Hosts that never register `Nowo\TransactionDoctrine\Symfony\NowoTransactionDoctrineBundle` move to 409 too** (the
  bundle falls back to `new ConflictResponsePolicy()`: 409, 1 s). The only way back to 503 is to register that bundle in
  `config/bundles.php` and set `conflict_response.status: 503`.
- Clients that retry on 503 + `Retry-After` must also retry on 409 + `Retry-After`. API clients that treat a 409 as
  "my write lost to another" should tell `ECODE: SERVER_002` (retry the same request) from `WIKI_CONFLICT`.
- A write that already committed part of its work (`ConcurrencyConflict` with `committed: true`, commit detection of
  the transaction-doctrine bundle) is answered with the same status but **without** `Retry-After`, as a reload prompt
  (message "Part of your change may have been saved. Reload before trying again."): never retry it blindly.
- HTML pages now throw `HttpException(409|503)` with the storage error as previous, instead of
  `ServiceUnavailableHttpException`; match on the status, not the class, in a custom error page.
- New `WikiStorageFailureConflictFormatter` (tag `nowo_transaction_doctrine.conflict_response_formatter`): if you list
  wiki routes in `nowo_transaction_doctrine.controller_routes`, the responder's answer for them now uses the wiki's JSON
  shapes instead of `{"error": "concurrency_conflict"}`.

## To 1.41.0

From **1.40.2**:

```bash
composer update nowo-tech/wiki-bundle
```

Target: **1.41.0**. Nothing changes unless `tree.context_menu: true` (opt-in since 1.39).

- **UiKit ^2.54.** `composer update nowo-tech/ui-kit-bundle` (the wiki now requires `^2.54`, was `^2.48`).

- **More entries in the tree menu.** With `tree.context_menu: true` the menu also offers Open in new tab, Edit, New page
  above / below (two entries, also without `web_ui.new_page_dialog`), Duplicate, Move up / down / to…, Archive (with an
  Unarchive undo) and, with `trash.enabled`, Delete. Users see an entry only with the rights of the matching page:
  - Archive: the role gate `canArchive()` and `WikiAction::Archive` on the space; Delete: `canArchive()` and
    `WikiAction::Delete` (as the trash). Users with those rights but **without the edit role** now get a menu (with
    Archive / Delete, Open in new tab and Copy link) where 1.40 gave them none.
  - Duplicate: `canCreate()`, `canEdit()`, Create on the space and Edit on the parent; quota event `page_create`.
  - Move up / down / to…: a movable tree (`canEdit()` and Edit on the space).
  The endpoints are new actions of the tree route (`POST …/tree/{archive|unarchive|delete|duplicate|parents}`): a host
  that lists the wiki's routes in a firewall or an access rule needs no change (same route name and path prefix).
- **Hosts that override `_tree_menu.html.twig`** get the new variables `tree_menu.items` (which entries to render) and
  `tree_menu.move_form` (the "Move to…" parent field); without `items` the template renders the 1.40 entries. The
  entry `below` is now labelled "New page below" and `above` ("New page above") is rendered without the new-page
  dialog too.
- **Focus mode.** With nowo-tech/ui-kit-bundle >= 2.55 (focus mode) and `nowo_ui_kit.theme.focus_mode.enabled: true`, the page view
  and edit form show the focus toggle (area `wiki`); in focus mode the page tree is a drawer behind a "Pages" button.
  Nothing to configure in the wiki; with an older UiKit nothing changes.
- **`stale` moves answer 409.** A script that calls the tree's `move` endpoint and tests for `422` on a lost race should
  test the `error` code (`stale`) or accept 409.
- **Exceptions (audit SH-L1 follow-up).** `WikiPagePlacement` throws `ValueObject\WikiInvalidPlacementException`, the
  import / export classes `Interchange\WikiInterchangeException`, `WikiSlugger::slugify()` `WikiInvalidSlugException`,
  and `WikiTrashService::delete()` `WikiPageArchivedException` for a page already in the trash. All extend
  `InvalidArgumentException` with the same messages: code that catches that keeps working; catch the dedicated class to
  tell a refusal from an ORM error.

## To 1.40.2

From **1.40.1**:

```bash
composer update nowo-tech/wiki-bundle
```

Target: 1.40.2. No configuration or schema change.

- **Business refusals have their own exception classes (audit SH-L1).** `WikiPageService`, `WikiPageIconService` (and so
  the REST API) throw `Service\WikiPageArchivedException`, `WikiInvalidSlugException`, `WikiSlugTakenException`,
  `WikiUnknownFormatException`, `WikiRevisionMismatchException`, `WikiInvalidIconException` or
  `WikiPageNotFoundException`. Each extends `InvalidArgumentException` with the message thrown before, so a
  `catch (InvalidArgumentException)` keeps working; catch the new classes instead if Doctrine errors must not be read
  as a refusal (`ORMInvalidArgumentException` is an `InvalidArgumentException` too). The REST API now answers an ORM
  error in a create, save or restore `500 SERVER_001` (logged) instead of `409 WIKI_PAGE_ARCHIVED` or `409 WIKI_003`.
  An unexpected error in the icon unit (after the committed content write) still answers the write with the icon
  `not_applied`, and is now logged at error level.
- **Your own entities that point at wiki pages (audit SH-L2).** The trash purge deletes pages with DQL and then makes the
  wiki's entity manager forget every wiki row it removed. It does not touch your entities: in the shared entity manager
  mode (`database.private_entity_manager: false`), a managed entity of yours with a `ManyToOne(WikiPage)` to a purged
  page keeps pointing at a detached page, and the next flush of that manager fails with `ORMInvalidArgumentException`
  ("A new entity was found through the relationship …"); with a foreign key, that column needs `ON DELETE CASCADE` or
  `SET NULL`, else the purge itself fails. This is not new in 1.40.2. In a FrankenPHP worker without the
  services resetter, or in a long command, that manager outlives the purge. Either:
  - store the page id (a string column, as the wiki's own collab and idempotency tables do) instead of a relation;
  - or use `database.private_entity_manager: true`: your entities then cannot hold wiki entities of your manager;
  - or listen to `nowo_wiki.trash.purged` (`WikiEvents::TRASH_PURGED`, dispatched after the commit; `WikiTrashEvent::$pageIds`
    lists every purged page id) and detach (or `refresh()`) your managed rows that point at one of those pages:

        #[AsEventListener(WikiEvents::TRASH_PURGED)]
        public function onPurged(WikiTrashEvent $event): void
        {
            $purged = array_fill_keys($event->pageIds, true);
            foreach ($this->em->getUnitOfWork()->getIdentityMap()[MyPageNote::class] ?? [] as $note) {
                if (isset($purged[$note->getPage()->getId()])) {
                    $this->em->detach($note);
                }
            }
        }

## To 1.40.1

From **1.40.0**:

```bash
composer update nowo-tech/wiki-bundle
```

Target: 1.40.1. With `page_icon.picker` / `space_icon.picker: icon_selector`, upgrade IconSelector to 1.8
(`composer require nowo-tech/icon-selector-bundle:^1.8`): its picker texts then come from its own catalogue whatever
the wiki form's `translation_domain`, it takes the UiKit look by itself (`nowo_icon_selector.theme: auto`), sets no
inline style, and a Save button outside the page form is no longer ignored while the picker loads. No wiki
configuration changes. 1.6 / 1.7 keep working with `picker: text` (and emoji).

## To 1.40.0

From **1.39.0**:

```bash
composer update nowo-tech/wiki-bundle
```

Target **1.40.0**, from **1.39.0** (section anchors and the link picker):

```bash
composer update nowo-tech/wiki-bundle nowo-tech/milkdown-editor-bundle
php bin/console assets:install
```

- **Milkdown >= 1.18.** The bundle declares a composer `conflict` with `nowo-tech/milkdown-editor-bundle` < 1.18.0. It
  applies to **every** host that has Milkdown installed, also one whose wiki uses `editor.type: tiptap` or `markdown`
  (the other editor only being installed is enough): update Milkdown in the same `composer update`. Hosts without
  Milkdown are not affected.
- **Collaboration: one reload for open tabs.** The collaboration schema version (Milkdown `CollabSchema::VERSION`, 2)
  is now part of the log name (`s2-…`). A page's log named before 1.40 starts a new epoch at its next session (when
  the log is empty, or idle for `collaboration.reset_after_idle` and fully saved; a log with unsaved text waits for its
  next save). Tabs still open with the older editor then get `409 log_epoch` and reload once. No migration: the
  `log_epoch` column is unchanged and old logs read as before.
  - **Busy pages:** a page edited without pause keeps its old log until it goes idle; meanwhile a tab of the older
    editor in that session can still rewrite explicit heading ids from their text (no text is lost). Deploy when the
    wiki is quiet, or lower `collaboration.reset_after_idle` for the upgrade window (for instance to `60`) and put it
    back afterwards.
  - **Host collaboration stores:** only a store that implements `WikiCollabEpochStoreInterface` gets the fold (and it
    must name new logs with `WikiCollabSchema::newEpoch()`); a store without it keeps its logs as before, so open tabs
    of an older editor are not asked to reload. `WikiCollabConditionalResetStoreInterface` (optional) makes the drop
    at the session start conditional on the log the token read (audit RY-L3).
- **Stable heading ids** are on with Milkdown (`editor.milkdown.stable_heading_ids: true`): after a heading is
  renamed, or for a repeated heading, the editor saves `{#id}` at the end of the heading line (`## Install guide
  {#setup}`), so links to that section keep working. `stable_heading_ids: false` keeps the 1.39 editor behaviour; the
  read view honours `{#id}` either way.
- **Link picker (opt-in).** Turn it on with a rate limiter:

  ```yaml
  framework:
      rate_limiter:
          wiki_link_picker: { policy: sliding_window, limit: 120, interval: '1 minute' }
  nowo_wiki:
      editor:
          type: milkdown
          milkdown:
              link_picker: { enabled: true, rate_limiter: limiter.wiki_link_picker }
  ```

  It registers `nowo_wiki_link_candidates` and `nowo_wiki_link_sections` (and their `tenant_paths` copies) and sets
  `suggestions['[[']` / `link_sections` of the page editor. A host that already serves its own `[[` candidates through
  `WikiEvents::EDITOR_OPTIONS` keeps its URL; switch it to the bundle's route (or answer the `{here, others}` shape of
  Milkdown 1.18) to get grouped results and sections. Firewall: the routes live under the wiki's path prefix like every
  other space route. Other wikis are offered from the same tenant only: by default the `tenant_paths` row, which is
  **coarse** (every space matching one row is one tenant: with `/projects/{spaceSlug}/wiki` that is every project wiki
  of every organisation). A host with real tenants must alias
  `Nowo\WikiBundle\LinkPicker\WikiLinkTenantResolverInterface` to its own resolver before turning the picker on (or
  set `cross_wiki: false`).
- **Read view.** Links to a section point at `#nowo-h-<id>` and read `Title › Section text` (or `Title`); every heading
  gets a "link to this section" control. **Overridden `page_view.html.twig`**: add the filter after
  `nowo_wiki_safe_html`, as the bundle does —
  `{{ content_html|default('')|nowo_wiki_safe_html|nowo_wiki_section_links('wiki.page.section_link'|trans, 'wiki.page.section_link_copied'|trans)|… }}`.
  New translation keys `wiki.page.section_link`, `wiki.page.section_link_copied`.
- **Anchor grammar.** Page-link anchors and heading ids follow Milkdown 1.18's grammar (Unicode lower-case letters,
  marks and digits joined by single `-`, 1–90 characters). An anchor outside it (`a--b`, upper case) is dropped and
  the link kept; the sanitizer no longer keeps a heading id with an upper-case letter (the slug never wrote one).
- **REST API.** The page body has a new `sections` field (`[{id, level, text}]`, same view as the content). Clients that
  write Markdown with `PUT` should keep `{#id}` suffixes, or those headings' ids change (see [API](API.md)).
- **Reader's Markdown / API viewer view.** Links to a section come back as `[[wiki:<uuid>#<id>]]`, and headings whose
  id differs from their slug keep `{#id}`.
- `WikiManageController`, `MilkdownWikiMarkdownRenderer` and `WikiApiContent::revision()` gained optional trailing
  arguments; `WikiViewerMarkdownView::view()` returns the Markdown and the sections of one render.
  `WikiAlignedHeaderConverter` is deprecated (the dialect converter uses `WikiHeadingIdConverter`).

## To 1.39.0

From **1.38.1**:

```bash
composer update nowo-tech/wiki-bundle
```

- **Nothing changes by default.** Every 1.39 feature is opt-in: `tree.context_menu` (default `false`),
  `page_icon` / `space_icon` `.picker` and `.emoji`, `web_ui.page_nav` (default `none`). With them off, the pages are
  those of 1.38, except the edit view's title field, now at heading size (`nowo-wiki-title-input`, label kept). Turning `tree.context_menu` on replaces the 1.35 "New page above / below / as child" menu of
  `web_ui.new_page_dialog` (`data-wiki-new-page-menu` → `data-wiki-tree-menu`, «…» `data-wiki-new-page-more` →
  `data-wiki-tree-menu-more`): host CSS or tests that target the old menu, and host overrides of `page_view.html.twig`
  / `space.html.twig` (include `_tree_menu.html.twig` in `nowo_ui_modals` like the bundle), need updating first.
- **Icon picker / emoji** need `nowo-tech/icon-selector-bundle:^1.6` (`composer require`), its API routes imported
  and `assets:install`. On MySQL / MariaDB emoji need a `utf8mb4` connection.
- **Milkdown 1.18**: with `nowo-tech/milkdown-editor-bundle` >= 1.18, anchored page links render `#nowo-h-<id>` (the
  heading id) instead of `#<id>`, labelled with the page title or section text instead of "› <id>". Host CSS, tests
  or scripts that match the old `href="…#<id>"` or the "› <id>" label need updating.
- `WikiPageIconService`, `WikiSpaceIconType` and `WikiTreeController` gained optional trailing constructor arguments.
- Templates: new partials `_page_nav.html.twig`, `_tree_menu.html.twig`, `_tree_menu_button.html.twig`,
  `_icon_picker_script.html.twig`; `_page_icon.html.twig` renders `emoji:` values.

## To 1.38.1

From **1.38.0**:

```bash
composer update nowo-tech/wiki-bundle
```

## To 1.38.0

From **1.37.0**:

```bash
composer update nowo-tech/wiki-bundle
```

Target 1.38.0, from **1.37.0**: `composer require nowo-tech/wiki-bundle:^1.38`.

- **Revision line (`web_ui.page_meta: card`)**: the body card and the line are now wrapped in one UiKit card
  (`.nowo-wiki-page-card`) and the line is in its footer slot (`<div class="… nowo-wiki-page-card__footer"><p
  class="… nowo-wiki-page-meta">`). A host stylesheet that drew the line or the body card itself (for example a card
  frame with its own border around the body) may drop those rules, or flatten `.nowo-wiki-page-card` (`border: 0;
  background: transparent`) when its frame already draws the card.
- **Page icons**: `WikiPage` gets a nullable `icon` column (`VARCHAR(64)`, no index, no special collation). The bundle
  ships no migrations: generate one (`doctrine:migrations:diff`) or run the statement for your database (adjust the
  table name if you changed `table_prefix`):

  ```sql
  -- MySQL / MariaDB / PostgreSQL
  ALTER TABLE wiki_pages ADD icon VARCHAR(64) DEFAULT NULL;
  -- SQLite
  ALTER TABLE wiki_pages ADD COLUMN icon VARCHAR(64) DEFAULT NULL;
  ```

  Nothing else changes until a page gets an icon: existing pages keep `null`, the page form has no icon field unless
  `page_icon.form_field: true`, exports of pages without an icon are byte for byte as before, and old exports import
  as before. API clients see a new `icon` key (`null`) in page payloads. A host template that overrides
  `_page_tree_item.html.twig` or the UiKit tree call of `_sidebar.html.twig` shows no icon until it includes
  `@NowoWikiBundle/manage/_page_icon.html.twig` (`{icon: node.icon}`) or passes the `leading_template`
  `@NowoWikiBundle/manage/_tree_page_icon.html.twig`. With `nowo_ui_kit.icon_set: ux_icon` set
  `ux_icons.ignore_not_found: true` too (as for space icons): without `symfony/ux-icons` installed in the PHP process
  that writes, a well-formed name that does not exist cannot be refused.
- **Icon changes are not content**: they add no revision, dispatch no page event (`nowo_wiki.page.saved` and the
  others) and are not in the history. Only the REST API audits them (`icon` in the changed fields). A host that needs
  a trace of form or import changes records it itself (e.g. around `WikiPageIconService::setIcon()`).
- **API clients**: a write whose icon could not be written after the page or revision committed answers as usual (201
  / 200) with the warning `{"field": "icon", "reason": "not_applied"}`; retry the icon alone with a `PUT` carrying the
  current `base_revision` and the same body.
- **Space icons** are drawn without the UiKit canonical-kind lookup since this release (see CHANGELOG, Fixed): with
  `ux_icon` any allowed prefix reaches `ux_icon()` as stored; with `tabler-icons` / `bootstrap-icons` only `tabler:` /
  `bi:` icons are drawn (before, a broken class).
- **Collapsible page tree**: the nested page tree (`tree.enabled: false`) now folds once `wiki.js` runs: by default
  only the current page's ancestors are open. Set `nowo_wiki.tree.default_expanded: all` to keep every row open at
  first (the 1.37 look, with the buttons), or `none`. A host that prints the tree with its own markup (an override of
  `_page_tree_item.html.twig`) keeps its markup and no buttons until it adds the row's `<button
  data-wiki-tree-fold="{{ node.id }}" aria-controls="nowo-wiki-tree-group-{{ node.id }}" aria-expanded="true" hidden>`
  and the sub-list's `id` (see the bundle's template); one that includes `_page_tree.html.twig` gets it as is. A host
  that bundles the assets itself (`web_ui.assets.scripts: false`) rebuilds them from `src/Resources/assets/src`.
- **Wide screens**: set `web_ui.reading_width: text` (with `reading_align: start` for the text against the card's left
  edge) to let the card fill the main column while the text keeps the reading measure.

## To 1.37.0

From **1.36.0**:

```bash
composer update nowo-tech/wiki-bundle
```

### High load (Beacon spec 239)

```bash
composer update nowo-tech/wiki-bundle
```

No configuration, schema or code change is required. New dependencies: `nowo-tech/transaction-contract` ^1.0 and
`nowo-tech/transaction-doctrine` ^1.1 (`doctrine/dbal` ^3.8.2 || ^4). What changes at runtime (details in
[HIGH-LOAD.md](HIGH-LOAD.md)):

- Every wiki write is one transaction of the wiki's own runner (`nowo_wiki.transaction_runner`), with its rows locked
  in one order and read again under the lock. A runner your application binds to
  `Nowo\TransactionContract\TransactionRunnerInterface` is never used by the wiki.
- **DBAL 3:** set `doctrine.dbal.use_savepoints: true`, so a wiki failure inside your transaction rolls back to its
  savepoint instead of marking your whole transaction rollback-only.
- The wiki's routes answer `503` + `Retry-After` for a deadlock or lock wait and `409` for a unique-key conflict instead
  of `500` (REST API: `SERVER_002` / `WIKI_CONFLICT`). `IDEMP_004` now carries `Retry-After`.
- **Shared manager (the default):** a manager that a failed flush closed is no longer reset in the middle of the
  request (that detached every entity you had loaded). It stays closed until the next request, message or command
  boundary, where the wiki's runner replaces it; meanwhile a wiki write answers `503`. After a failed wiki unit only
  the wiki rows it touched are detached. The co-editing log and `wiki:search:backfill-text` no longer flush or clear
  the manager.
- A save without a base revision (the edit form, imports, `wiki:convert-markdown`) of a page archived or trashed
  meanwhile is refused (`Archived pages cannot be edited.`), as a save of an archived page always was.
- A page save that hits a deadlock or lock wait still throws `WikiRevisionCollisionException` (its previous exception is
  now a `TransientStorageFailure`).
- **Shared manager:** when a wiki unit that flushed changes of yours fails afterwards, the wiki closes the manager (the
  database undid those changes; Doctrine would otherwise take them as written). Your next flush in that request fails
  with `EntityManagerClosed`; the manager is replaced at the next request. Reload and retry, as after any failed flush.
- Wiki events of a unit that runs inside your own transaction are dispatched when the wiki's savepoint is released, not
  at your commit (as in 1.36); see HIGH-LOAD.md.
- Services you build by hand keep the 1.36 behaviour unless you pass the runner (`runner:` argument of
  `WikiPageService`, `WikiPageMover`, `WikiTrashService`, `WikiCommentService`, `WikiPageLinkService`,
  `WikiAttachmentService`).

### Optional: exact collation of the page link columns

New schemas give `object_type` and `object_id` of the page links the binary NO PAD collation on MySQL / MariaDB (house
rule LQ), and every lookup re-checks the ids with `===` either way, so this migration is **optional**: it only lets
the database itself keep `ABC` and `abc` (or `42` and `42 `) apart, including in the `(page_id, object_type,
object_id)` unique key. Until you run it, linking a look-alike of an object a page is already linked to is refused
by that key (409); nothing wrong is read. The `ALTER` is safe: going from a folding collation to a binary one never
creates duplicates. PostgreSQL and SQLite compare exactly already.

```sql
-- MySQL 8
ALTER TABLE wiki_page_links
  MODIFY object_type VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_bin NOT NULL,
  MODIFY object_id VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_bin NOT NULL;
-- MariaDB
ALTER TABLE wiki_page_links
  MODIFY object_type VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_nopad_bin NOT NULL,
  MODIFY object_id VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_nopad_bin NOT NULL;
```

Use your table prefix (`nowo_wiki.database.table_prefix`) if you set one. `doctrine:migrations:diff` generates the same
statement.

## To 1.36.0

From **1.35.0**:

```bash
composer update nowo-tech/wiki-bundle
```

## To 1.36.0

REQ-ARCH-001: the DDD / hexagonal host path, opt-in. With an unchanged configuration and unchanged host services the
wiki behaves as 1.35 (same schema, same mapping, same entity manager, every deprecated port asked exactly as before),
except for the REST API's same-person rule below. Overview of both host kinds: [ARCHITECTURE](ARCHITECTURE.md).

**Action recommended (REST API, `accepted_user_classes`).** The API now compares users by class and id (audit OX-L1):

- If your firewall's user and the user your `api.hooks.authenticator` returns are of different classes: add the class
  that is not `user_class` to `nowo_wiki.accepted_user_classes` only if it carries user_class ids (an adapter or
  wrapper); otherwise the firewall and the authenticator must return the same user.
- In 1.x such a pair with the same exact id still passes, with a notice in the log: `Wiki API: the firewall user (…)
  and the token's user (…) are matched by their id only: add … to nowo_wiki.accepted_user_classes only if it carries
  user_class ids (an adapter or wrapper); otherwise the firewall and the authenticator must return the same user. This
  will be refused in 2.0.`
- It never makes the token's user the owner of anything: "one's own page" and the token user's own API id are judged
  on the token's user alone, and need `user_class` or an accepted class.
- A firewall user with another id, or an id that cannot be matched exactly, is a configuration error
  (`Wiki API: configuration error: the firewall user (…) is not the token's user (…)`) and the request answers 500, as
  before.

- Member badges and write provenance (1.34) on the DDD path: nothing to change in your providers. With
  `database.mapping_type: xml` the `channel` / `actor_label` columns are mapped (same schema as the attributes: the
  1.34 migration applies); with `database.user_reference: id` your badge provider is asked about
  `WikiMemberRef(<user_class>, <id>)` for the authors stored by id, and `wiki:comments:erase-user <id>` erases that
  exact id's actor labels.
- Placing a new page by id (1.35 "create at a position"): `WikiPageOperationsInterface::createPageAt()` /
  `createPageNextTo()` (needs `tree.enabled`).
- Several entity managers on DoctrineBundle 3.4 (attribute and XML mapping alike): the wiki entities are now transient
  on every manager other than `database.entity_manager` that does not map them explicitly;
  `doctrine:schema:update --em=<other>` no longer lists the wiki tables.
- REST API (audit OX-L1): "one's own page" (archive / unarchive at Edit) and the token user's own host API id now need
  the same identity domain: a token user of `user_class` or of a listed `accepted_user_classes` entry with the same id,
  not merely the same id. If your API authenticator returns users of a class that is neither, list it in
  `accepted_user_classes` (see "Action recommended" above for the firewall's user).
- Twin ports that read `WikiRequestContext`: it carries only the allowed headers
  (`WikiRequestContexts::ALLOWED_HEADERS`) and no query string (audit OX-L3).
- `database.private_entity_manager` stays `false` by default in 1.x: switching it on is a behaviour change for an
  existing host (see below and [ARCHITECTURE › Persistence](ARCHITECTURE.md#persistence)).

### Host ports: move to the framework-free twins (optional until 2.0)

- Implement the twin of each port you serve (table in [ARCHITECTURE › Host ports](ARCHITECTURE.md#host-ports)) and
  point the same configuration key or alias to it (or alias the twin interface). A service may implement both while you
  migrate: the deprecated interface is used until you drop it. Two different services, one per interface, fail the
  build: keep one.
- Twins receive `ValueObject\WikiUserRef` users. Compare them with `isSameUserAs()` (class and id, byte for byte),
  never `==`; `user()` gives back the object the bundle had, for the migration period.
- A framework-free directory returns `new WikiApiWiki(..., spaceId: $id)` with `$space` null, and `provision()` returns
  the new space's id; an id that names no space throws a `LogicException`. `WikiCollabSubscriptionPolicyInterface`
  returns the cookies (e.g. the Mercure authorization cookie) instead of writing them. The AI twin returns
  `WikiAiReply` sources by page and space id.
- Call the wiki from your application layer through `WikiPageOperationsInterface`, `WikiSpaceOperationsInterface` and
  `WikiPageLinkOperationsInterface` (ids in, snapshots out) instead of the entity ports.
- Listeners: read `spaceId()`, `pageId()`, `pageSnapshot()`, `actorRef()` / `actorId()` instead of the entities and
  the user objects.
- **Removed in 2.0:** the 16 deprecated port interfaces, `WikiApiPrincipal::$user` as a `UserInterface`,
  `WikiQuotaCheckEvent::$user`, `WikiEditorOptionsEvent::$user`.

### XML mapping (`database.mapping_type: xml`)

- Set `nowo_wiki.database.mapping_type: xml` (a literal value, not a parameter or env var: the Doctrine mapping is
  prepended before the configuration is processed). The schema does not change, so no migration is needed. The wiki
  mapping is added to `database.entity_manager`.
- With a non-default `database.entity_manager` and the attribute mapping, keep mapping the bundle on that manager
  yourself as before (`WikiBundle: {type: attribute, is_bundle: true}`), or switch to `xml`, which maps it there.
- After changing an entity (bundle maintainers): `php scripts/generate-xml-mapping.php`; CI fails on drift.

### User references by id (`database.user_reference: id`)

This is a **schema and data migration**: plan a maintenance window and take a backup.

1. Set `nowo_wiki.database.user_reference: id` and, for names and avatars, `nowo_wiki.user_directory: <service id>`
   (a `User\WikiUserDirectoryInterface` whose `find(string $id)` returns the user with exactly that id, or null).
2. Generate the migration (`doctrine:migrations:diff`): it drops the seven foreign keys to the user table, turns the
   columns into `VARCHAR(128)` (binary collation on MySQL / MariaDB) and adds one index per column. Check that the
   values are kept (an integer id keeps its digits as text).
3. **Detection queries before the `ALTER` (REQ-SEC-006 item 9).** User ids that cannot be stored exactly (edge
   whitespace, control characters, more than 128 characters) must be fixed in your user table first, and ids that
   differ only by case, accents or trailing spaces must be checked so that two people never collapse into one:

   ```sql
   -- MySQL / MariaDB: user ids that fold onto another one (compare and group in binary; TRIM / LOWER / DISTINCT fold too)
   SELECT LOWER(id) AS folded, COUNT(*) AS n, GROUP_CONCAT(HEX(id)) AS ids
     FROM app_user GROUP BY LOWER(id) HAVING COUNT(DISTINCT CAST(id AS BINARY)) > 1;
   -- ids with an edge space, a tab / newline, or longer than 128 characters
   SELECT HEX(id) FROM app_user
    WHERE CAST(id AS BINARY) <> CAST(TRIM(id) AS BINARY) OR id REGEXP '[[:cntrl:]]' OR CHAR_LENGTH(id) > 128;
   ```

   (replace `app_user` / `id` by your user table and id column; PostgreSQL and SQLite compare exactly.)
4. The `ALTER` per column on MySQL 8 (MariaDB: `utf8mb4_nopad_bin`; MySQL 5.7: `utf8mb4_bin`, PAD SPACE):

   ```sql
   ALTER TABLE wiki_page_revisions DROP FOREIGN KEY <fk_name>;
   ALTER TABLE wiki_page_revisions MODIFY author_id VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_bin NULL;
   -- same for wiki_page_links.created_by_id, wiki_attachments.uploaded_by_id, wiki_comment_threads.created_by_id and
   -- .resolved_by_id, wiki_comments.author_id (all NULL) and wiki_comment_reactions.user_id (NOT NULL)
   ```

   The binary collation is required for safety: on a folding collation the bundle's PHP re-check still keeps every
   match exact, but lookups could not use the index exactly.
5. After the switch the bundle never queries your user table: a user you delete keeps their id on the wiki rows (shown
   as "Editor xxxx" when the directory no longer finds it), and `wiki:comments:erase-user <id>` still erases their
   comments (it acts on the stored id itself, whatever your directory returns; pass their handles with `--handle`). Values read back from rows are `WikiStoredUserRef`: compare users by `getId()`, never with `===`.

### Private entity manager (`database.private_entity_manager: true`; the only mode in 2.0)

- Set `nowo_wiki.database.private_entity_manager: true`. The tables do not move; no migration.
- Wiki writes no longer flush your entity manager: an entity of yours that you used to see saved "by" a wiki write
  must now be flushed by you.
- Load wiki rows you hand to the wiki services through the wiki (`Repository\*Interface`, the id operations), not
  through your own manager. Saved wiki rows and host users your manager loaded are adopted as references when they sit
  on a wiki row, but your instances are never written.
- Users and rows read back through the wiki are the private manager's instances: compare by id, never with `===`.
- With DBAL 4 a rollback of rows the private manager wrote replaces it at its next use (middleware on the source
  connection only); with DBAL 3 it is replaced at the next boundary.
## To 1.35.0

From **1.34.0**:

```bash
composer update nowo-tech/wiki-bundle
```

Target 1.35.0. No schema change; every default keeps the 1.34 pages.

- **New page dialog and tree menu** (opt-in): set `nowo_wiki.web_ui.new_page_dialog: true` (with `tree.enabled: true`
  for the tree menu). Hosts that override `page_view.html.twig` or `space.html.twig` and replace `nowo_ui_modals` call
  `{{ parent() }}` there or include `@NowoWikiBundle/manage/_new_page_dialog.html.twig` themselves (variables
  `new_page_dialog`, `with_menu`); hosts that override `_sidebar.html.twig` pass `actions_template:
  tree_ui.actions_template|default(null)` to the UiKit `tree` partial. With `web_ui.assets.scripts: false`, bundle
  `wiki-new-page.ts` (imported by `wiki.ts`) and, for the Tiptap editor, the TiptapEditorBundle script on the page view
  and the space home. Hosts that render the new-page form field by field render the hidden `place` (`form_rest()` /
  `form_end()` do).
- **Cost with the option on** (audit PT-L4): each page view and space home of a user who may create pages builds the
  new-page form (one `nowo_wiki.editor_options` event with `page` null, so host listeners run on read pages), mints its
  CSRF token, loads the editor script, and answers `Cache-Control: private, no-store` (those users' page views are no
  longer cached by the browser). The tree menu costs one Edit check on the space per page, not one per row.
- **API, behaviour changes**:
  - A create with `parent` (with `tree.enabled`) now checks `tree.max_depth` (422 `WIKI_MOVE_002`) and places the page
    last among the parent's children, instead of at position 0 (audit PT-M1).
  - `position` is now read: a client that sent a `position` before 1.35 got an `unknown` warning and the page created at
    0. Now it places the page; it must be an integer from 1 to 100000 (else 400 `INPUT_002`, e.g. `"2"`), and without
    `tree.enabled` it is 400 `INPUT_002`.
  - When a create or a move placed the page, the answer's `position` is the 1-based visible place, not the sort key.
  - `POST …/move` keeps its 0-based `index`.
- **PHP**: `WikiPageMover::createAt()` / `createNextTo()` (returning `WikiPageCreateResult`) are new;
  `WikiPageFormType` has a new `placement` option (default `false`).

## To 1.34.0

From **1.33.0**:

```bash
composer update nowo-tech/wiki-bundle
```

Target 1.34.0 (spec 234: member badges and write provenance).

`wiki_page_revisions` gets two nullable columns. The bundle ships no migrations: generate one
(`doctrine:migrations:diff`) or run the statements for your database (adjust the table name if you changed
`table_prefix`):

```sql
-- MySQL / MariaDB
ALTER TABLE wiki_page_revisions ADD channel VARCHAR(8) DEFAULT NULL, ADD actor_label VARCHAR(120) DEFAULT NULL;
-- PostgreSQL
ALTER TABLE wiki_page_revisions ADD channel VARCHAR(8) DEFAULT NULL;
ALTER TABLE wiki_page_revisions ADD actor_label VARCHAR(120) DEFAULT NULL;
-- SQLite
ALTER TABLE wiki_page_revisions ADD COLUMN channel VARCHAR(8) DEFAULT NULL;
ALTER TABLE wiki_page_revisions ADD COLUMN actor_label VARCHAR(120) DEFAULT NULL;
```

Existing revisions keep NULL (nothing recorded). Nothing else changes until you alias
`WikiWriteProvenanceProviderInterface` and / or `WikiMemberBadgeProviderInterface` to your services (see
[USAGE](USAGE.md#member-badges-and-write-provenance)): the history and the comments render the same markup, and the API
answers carry the new fields (`created_by`, `channel`, `token_name` on pages; `channel`, `token_name` on revisions) as
null, or `created_by` equal to the oldest revision's author. Clients that reject unknown fields must accept them.
`actor_label` is personal data. Export: `wiki:comments:erase-user --export` and `WikiCommentService::exportAuthorData()`
include the user's labels (`revision_actor_labels`). Erasure: `wiki:comments:erase-user` and
`WikiCommentService::eraseAuthor()` erase them and those of revisions whose author was already deleted. If your exporter
or eraser goes through neither, call `WikiWriteProvenanceErasure` (`exportActor()`, `eraseActor()`, `eraseOrphaned()`).

Actor labels (`token_name` in the API) are shown to the author and, with the new `history.show_actor_labels` (default:
`history.show_names`), beside real names only when they pass the safe-name rule; never beside an opaque "Editor xxxx".
Set `history.show_actor_labels: false` to show them to their author only.

If you override `manage/page_history.html.twig` or `manage/_comments_panel_body.html.twig`, include
`@NowoWikiBundle/manage/_member_label_suffix.html.twig` after the author name to show badges (see the bundle templates).

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
