# Architecture: standard Symfony and DDD hosts

WikiBundle works out of the box in a **standard Symfony application** (attribute-mapped entities, a Doctrine
`user_class`, the bundle's controllers and services on your entity manager, Twig overrides) and can be used from a
**strict DDD / hexagonal host** (plain-PHP domain, framework-free ports, XML mapping, users referenced by id, the wiki as
a separate bounded context). The standard path is the zero-config default; the DDD path is opt-in and never forced
(REQ-ARCH-001, since 1.36.0). Both work in FrankenPHP worker mode with the kernel kept alive and not reset
(REQ-RUNTIME-002, see [FRANKENPHP-WORKER-AUDIT](FRANKENPHP-WORKER-AUDIT.md)), and every string identity the bundle stores
or matches is compared byte for byte (REQ-SEC-006, see [SECURITY](SECURITY.md)).

## Table of contents

- [At a glance](#at-a-glance)
- [Host ports](#host-ports)
- [Operations by id](#operations-by-id)
- [Read ports and page access by id](#read-ports-and-page-access-by-id)
- [Events](#events)
- [Persistence](#persistence)
- [Mapping and user references](#mapping-and-user-references)
- [Both host kinds, tested](#both-host-kinds-tested)

## At a glance

| | Standard host (default) | DDD host (opt-in) |
|---|---|---|
| Host ports | the framework-free ports (`WikiUserRef`, snapshots, request-context VOs; 2.0: the only host ports) | the same |
| Calling the wiki | `WikiPageServiceInterface`, `WikiSpaceServiceInterface`, `WikiPageLinkServiceInterface` (entities) | `WikiPageOperationsInterface`, `WikiSpaceOperationsInterface`, `WikiPageLinkOperationsInterface` (ids, snapshots) |
| Lists, counts, page access | the repositories (persistence adapters), `WikiSpaceGuard` (entities) | `WikiSpaceQueryInterface`, `WikiPageQueryInterface`, `WikiPageAccessInterface` (1.51: ids, snapshots) |
| Events | entity payloads, `actorId()` | `spaceId()`, `pageId()`, `pageSnapshot()`, `actorRef()`, ... |
| Mapping of the 16 wiki entities | ORM attributes | `database.mapping_type: xml` |
| Host users on wiki rows | `ManyToOne` to `user_class` (a Doctrine entity) | `database.user_reference: id` + `user_directory` |
| Entity manager | the wiki's private manager on `database.entity_manager` (2.0: the only mode; your manager is never flushed) | the same |
| Bundle base classes / traits for host entities | none | none |

The wiki's entities are bundle-owned: a host never extends them and references them by id (`WikiPageSnapshot::$id`,
`WikiSpaceSnapshot::$id`, the id accessors of the events). The bundle ships no mapped superclass and no trait for host
entities.

## Host ports

A host extends the wiki through ports it implements (configuration keys or service aliases). Since 1.36 every port that
took a Symfony `UserInterface`, `Request`, `Response`, `UploadedFile` or a wiki entity has a **twin** that takes and
returns only bundle value objects, enums and scalars; 2.0 removed the 1.x interfaces, so the twins are the only host
ports:

| Configuration key / alias | Removed 1.x interface (deprecated 1.36, removed in 2.0) | Port (1.36 twin) |
|---|---|---|
| `security.access_checker` | `Security\WikiAccessCheckerInterface` | `Security\WikiAccessPolicyInterface` |
| `WikiSpaceAccessPolicyInterface` alias | `Security\WikiSpaceAccessCheckerInterface` | `Security\WikiSpaceAccessPolicyInterface` |
| `team_membership_resolver` | `Security\WikiTeamMembershipResolverInterface` | `Security\WikiTeamMembershipSourceInterface` |
| `WikiSpaceReachInterface` alias | `Service\WikiSpaceAccessResolverInterface` | `Service\WikiSpaceReachInterface` (space ids) |
| `WikiSpaceIdLocatorInterface` alias | `Service\WikiSpaceLocatorInterface` | `Service\WikiSpaceIdLocatorInterface` (a space id) |
| tag `nowo_wiki.object_link_resolver` (autoconfigured) | `Link\WikiObjectLinkResolverInterface` | `Link\WikiObjectLinkSourceInterface` |
| `WikiUserAvatarSourceInterface` alias | `Avatar\WikiUserAvatarResolverInterface` | `Avatar\WikiUserAvatarSourceInterface` |
| `comments.state_store` | `Panel\WikiPanelStateStoreInterface` | `Panel\WikiPanelStatesInterface` |
| `tree.state_store` | `Tree\WikiTreeStateStoreInterface` | `Tree\WikiTreeStatesInterface` (space id) |
| `collaboration.subscriber_authorizer` | `Collaboration\WikiCollabSubscriberAuthorizerInterface` / `WikiCollabTopicsAuthorizerInterface` | `Collaboration\WikiCollabSubscriptionPolicyInterface` (returns `WikiCollabCookie`s) |
| `api.hooks.authenticator` | `Api\Hook\WikiApiAuthenticatorInterface` | `Api\Hook\WikiApiRequestAuthenticatorInterface` |
| `api.hooks.files` | `Api\Hook\WikiApiFilesInterface` | `Api\Hook\WikiApiFileStoreInterface` |
| `api.hooks.plan_gate` | `Api\Hook\WikiApiPlanGateInterface` | `Api\Hook\WikiApiPlanPolicyInterface` |
| `api.hooks.directory` | `Api\Hook\WikiApiDirectoryInterface` | `Api\Hook\WikiApiWikiDirectoryInterface` (space ids) |
| `WikiAiAnswererInterface` alias | `Ai\WikiAiAssistantInterface` | `Ai\WikiAiAnswererInterface` (returns `Ai\WikiAiReply`) |
| `WikiAttachmentLimitsPolicyInterface` alias | `Attachment\WikiAttachmentLimitsProviderInterface` | `Attachment\WikiAttachmentLimitsPolicyInterface` |
| `user_directory` (id mode, new) | — | `User\WikiUserDirectoryInterface` |

Value objects of the twins: `ValueObject\WikiUserRef` (class, id, identifier and roles, copied when the port is called;
`user()` gives back the original object; compare users with `isSameUserAs()`, class and id byte for byte),
`WikiSpaceSnapshot`, `WikiPageSnapshot`, `WikiRevisionSnapshot` (ids and plain values, never an entity or a body),
`WikiRequestContext` (method, path, route, client IP and an allow-list of plain headers,
`WikiRequestContexts::ALLOWED_HEADERS`, the referrer reduced to scheme, host and path; no query string, no credential,
session, CSRF or infrastructure token header: the REST API credential reaches the authenticator only as `$credential`), `Api\Value\WikiApiUpload`, `Api\Value\WikiApiDownload`, `Collaboration\WikiCollabCookie`,
`Ai\WikiAiReply`.

**Wiring (2.0).** The bundle asks each port through an `@internal` bundle-side interface of the removed name in
`Port\Internal\` (no BC promise): its own defaults implement it, and a host service is asked through an internal gate
(`Port\*Gate`). `DependencyInjection\Compiler\WikiPortsPass` stops the build when a configured host service does not
implement the twin, naming the configuration key; a factory or synthetic service, whose class the build cannot know, is
checked when it is built and fails closed. A host may also alias the twin interface itself; two different host services
(the key and the alias) fail the build. `WikiRemovedPortsPass` fails the build on every trace of a removed 1.x
interface: a service or alias under its id, a decorator of it (also `onInvalid: IGNORE`), and an autodiscovered class
that still implements it, which Symfony would otherwise drop.

**What a twin returns is checked.** Space ids are loaded and kept only when a stored space has exactly that id (the
locator also needs exactly the asked slug); an unknown directory space id fails closed. Linked objects are kept only for
the asked ids and type. AI sources are kept only inside the asked space and the user's reach. The 1.31.1 owner-id rules
(`ValueObject\WikiOwnerScopeId`, the `===` re-checks of `findAccessible()` / `findBySlug()`) apply unchanged
(GHSA-xpr3-grw5-6h9c).

The other ports (`WikiAttachmentStorageInterface`, `WikiAttachmentScannerInterface`, the co-editing stores and
publisher, the Markdown extensions and renderers, the REST API users / rate limiter / idempotency store / wiki
management hooks) already take values only. The repository interfaces (`Repository\*Interface`) are the persistence
adapters of the bundle's own entities, not host ports: they are entity-typed by design, and a host that replaces them
reimplements the wiki's persistence. `Markdown\WikiRenderReach` is internal.

## Operations by id

The 1.27 service ports take and return wiki entities. Their 1.36 id twins (public aliases) take ids and `WikiUserRef`
users and return snapshots, with the same events and checks:

| 1.27 entity port | 1.36 id operations |
|---|---|
| `Service\WikiPageServiceInterface` (and the 1.35 `WikiPageMover::createAt()` / `createNextTo()`) | `Service\WikiPageOperationsInterface`: `findPage`, `createPage`, `createPageAt`, `createPageNextTo`, `saveRevision`, `restoreRevision`, `archivePage`, `unarchivePage`; 1.55 reads `findPageRevision`, `findCommentThread`, `findComment`, `findThreadComments` |
| `Service\WikiSpaceServiceInterface` | `Service\WikiSpaceOperationsInterface`: `findSpace`, `createSpace`, `findSpaceBySlug`; 1.55 `createSpaceFrom` (returns the id; erase with `WikiSpaceLifecycleInterface::eraseSpace`) |
| `Link\WikiPageLinkServiceInterface` | `Link\WikiPageLinkOperationsInterface`: `linkPage`, `unlinkPage`, `usedIn` |

Ids are matched byte for byte; an unknown id, a revision of another page or a parent in another space fails closed
(`InvalidArgumentException`). `createPageAt` / `createPageNextTo` place the new page under the space lock with the ordering and
`tree.max_depth` of the page tree (they need `tree.enabled`).

The 1.34 hooks are framework-free already and work in both host kinds: `Member\WikiMemberBadgeProviderInterface` is
asked about `WikiMemberRef` values (class and id; a stored id reference is a member of `user_class`, a framework-free
security user the member its `WikiUserRef` names), `Provenance\WikiWriteProvenanceProviderInterface` returns a
`WikiWriteProvenance` value. Their columns are in the XML mapping, and the provenance reads and the erasure of actor
labels match users by exact id in the id mode.

## Read ports and page access by id

The repository interfaces are the persistence adapters of the wiki's entities, so an application that lists or counts
wiki rows through them, or through its own queries, depends on the wiki's schema. Since 1.51 three public services
answer those reads by id, framework-free ([USAGE](USAGE.md#read-ports-and-page-access-by-id-151)):

| Service | Answers |
|---|---|
| `Query\WikiSpaceQueryInterface` | `findSpaces` / `countSpaces` (`WikiSpaceCriteria`: owner kind, owner ids, space ids, archived filter, sort; offset paging), `findSpacesByIds`, `usage` per owner (`WikiOwnerUsage`) |
| `Query\WikiPageQueryInterface` | `findPages` / `countPages` (`WikiPageCriteria`), `findPagesByIds`, `pageStats`, `ancestors`, `pageContents`, `backlinks` and `searchPages` (optional viewer) |
| `Security\WikiPageAccessInterface` | `isPageReadable(WikiUserRef, pageId)`, `readablePageIds(WikiUserRef, pageIds)`: the twin of `WikiSpaceGuard::isPageReadable()`, same rule and checker, same decisions |

They read through the wiki's private entity manager and never write or lock. Lists, counts,
trees, contents and usage read columns and build snapshots, so no entity enters any manager; ids and owner ids read
back are compared byte for byte in PHP (REQ-SEC-006), owner ids are validated as `WikiOwnerScopeId`. Stateless
(REQ-RUNTIME-002): the decisions of one `readablePageIds()` call are a local memo. The page rule asks the bundle-side space checker (`Port\Internal\WikiSpaceAccessCheckerInterface`), which
`WikiSpaceGuard` asks too (a twin policy through its gate, decorated by the
archived-space rule), so the two answer alike by construction; `WikiPageAccessEquivalenceTest` proves it decision by
decision.

## Events

Every event that carries a wiki entity also answers with ids and snapshots, built on call: `spaceId()`, `pageId()`,
`pageSnapshot()`, `revisionId()`, `revisionSnapshot()`, `threadId()`, `commentId()`, `attachmentId()`, ... and the actor
as `actorRef()` (next to the 1.27 `actorId()`). The Symfony-typed members (`WikiQuotaCheckEvent::$user`,
`WikiEditorOptionsEvent::$user`, `WikiApiPrincipal::$user`) were removed in 2.0: read `userRef()`. The entity members
stay (a listener that changes a page still needs them) and are not deprecated.

Write events are dispatched after the write is committed (`WikiEventDeferral`): never from inside the bundle's own
flush, and not at all when the write rolls back. `QUOTA_CHECK` runs before a metered write by design (it can refuse
it), `EDITOR_OPTIONS` before the editor is built (no write), and `PAGE_WRITTEN` is internal (the co-editing
bookkeeping, inside the transaction); hosts listen to `PAGE_SAVED`.

## Persistence

**The private entity manager is the only mode (REQ-ARCH-001 rule 6, 2.0).** Up to 1.35 the wiki wrote through
`database.entity_manager` and a wiki save flushed that manager, including what the application left pending in it; 1.36
added the private manager as an opt-in, 1.49 a notice and a host-flush probe for the shared mode, and 2.0 removed the
shared mode (`database.private_entity_manager: false` fails the build; `true` is a deprecated no-op until 3.0).

**Private entity manager.** Every wiki service
reads and writes through `Doctrine\WikiEntityManager`, a private manager created on the connection, mapping, caches,
proxies and event manager of `database.entity_manager` (REQ-ARCH-001 rule 6). The tables do not move and the bundle's
and your Doctrine listeners still run. Adapted from TaskBoardBundle 2.15.0 / AuthKitBundle 1.46.0:

- it never flushes, clears or closes your manager; a failed wiki write closes only the private one, which is created
  again on the next call (also when its connection changed, or when a rollback undid rows it wrote: DBAL 4 middleware
  `PrivateEntityManagerMiddleware` on the source manager's connection only);
- it is dropped at every outermost unit-of-work boundary (start and end of the main request, Messenger message handled
  or failed, `console.terminate`; `WikiRequestBoundaryResetter`, after the request memos) and at `kernel.reset`:
  dropped FIRST, then its pending changes are logged (entity classes and counts only, never a value) and cleared, each
  step guarded;
- the source manager's SQL filters are mirrored, with their parameters, on every call;
- repositories: service repositories come from your configuration's factory, every other one is built and kept per
  manager (`PrivateManagerRepositoryFactory`; no growth in a kept-alive worker);
- your security user and other host objects on wiki rows, and saved wiki rows your manager loaded, are adopted as the
  private manager's references before each flush, by the identifier your unit of work holds (an uninitialised proxy is
  never loaded); your instances are never written. A host object without an identifier yet (persisted, not flushed) or
  of another class than the association's target fails with a `LogicException` before anything is written (2.0, W1-2).
  Users read back from wiki rows are the private manager's instances: compare users by id, never with `===`;
- 2.0 (W1-2): a wiki row your manager loaded and handed to a wiki service (`WikiPageServiceInterface`,
  `WikiPageLinkServiceInterface`, the trash, attachment, page mover and page icon services) is loaded again by its id in
  the private manager (`WikiEntityManager::attach()`, `WikiTransactionRunner::attach()`), so it is never inserted a
  second time; a row gone meanwhile answers the not-found exception. Persisting, refreshing or locking such a row through
  the private manager fails with a `LogicException` naming the class, never a duplicate `INSERT`;
- the wiki's bulk statements inside your open transaction run in a savepoint (`WikiSavepoint`); a failure rolls back to
  it and the ORIGINAL exception is rethrown. A rollback of a wiki savepoint that fails (the server already ended your
  transaction) is logged, never thrown over the original failure.

## Mapping and user references

| | Standard host (default) | DDD host (opt-in) |
|---|---|---|
| Mapping of the 16 wiki entities | ORM attributes | `database.mapping_type: xml` (`Resources/config/doctrine-xml`, generated from the attributes by `scripts/generate-xml-mapping.php`, identical metadata; `XmlMappingParityTest`) |
| Other entity managers | wiki entities transient unless mapped there explicitly (`WikiMappingManagerPass`, DoctrineBundle 3.4 `auto_discover_entities`) | wiki entities transient (`WikiExcludedEntityNamespaceDriver`) |
| Host users on wiki rows | `ManyToOne` to `user_class` (a Doctrine entity in the wiki's manager) | `database.user_reference: id`: the id in a string column (`WikiStoredUserRef`, type `wiki_user_ref`, 128 characters, indexed, no foreign key), found again through `user_directory` |

With the id mode the bundle never joins your user table: DQL compares the id column, lookups go through the directory
(each answer re-checked with `===` on `getId()`), every user-keyed result is re-checked with `===` in PHP before any
limit, erasures select the exact rows first and write by primary key, and the columns compare in binary NO PAD on MySQL
(`utf8mb4_0900_bin`) and MariaDB (`utf8mb4_nopad_bin`). Rows outlive their user. The same holds in both modes for the
1.45 favourites and recent visits (`user_id`, always a string column): every write selects the person's rows, keeps
those whose id is `===` the person's, and writes or deletes them by primary key, so a PAD SPACE fallback (`utf8mb4_bin`,
`alice` = `alice `) never lets one person touch another person's row.

## Both host kinds, tested

- **Standard host**: the whole unit, integration, database and API suite (attribute mapping, the `TestUserEntity`
  Doctrine user, the private entity manager), plus its replay with every API host port through its twin only
  (`WIKI_TEST_PORTS=twin`, job `twin-ports`). The removed 1.x interfaces are covered by `RemovedPortsKernelTest` (real
  bundle build, autodiscovered fixtures) and `WikiPortsPassTest`; the W1-2 cases (host-loaded wiki rows, unsaved users,
  pending host changes) by `WikiPrivateManagerHostEntitiesTest` on SQLite, MySQL 8.4, MariaDB 11.4 and PostgreSQL 16.
- **DDD host**: `tests/Api/DddHost` (`DddHostKernel`: a plain-PHP security user with no mapping and no user table, the
  XML mapping, users stored by id with a directory, the private entity manager, the twin ports only, the id
  operations; a two-person kept-alive kernel run through `http_kernel->handle()` / `terminate()`), plus
  `XmlMappingParityTest`, `WikiUserReferenceByIdTest` (also on MySQL and MariaDB) and the private entity manager tests
  (`tests/Api/PrivateEntityManager`).
- **The 1.51 read ports and page access**, both legs: `ApiReadModelTest` (standard host kernel, the application's own
  checker) and `DddHostKernelTest::testTheReadPortsAndThePageRuleWorkOnTheDddHost` (XML mapping, user ids, private
  manager, twin policy, kept-alive kernel), plus `WikiReadModelTest` and `WikiPageAccessEquivalenceTest` on SQLite,
  MySQL 8.4, MariaDB 11.4 and PostgreSQL 16.
