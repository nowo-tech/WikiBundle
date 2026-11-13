# Architecture: standard Symfony and DDD hosts

WikiBundle works out of the box in a **standard Symfony application** (attribute-mapped entities, a Doctrine
`user_class`, the bundle's controllers and services on your entity manager, Twig overrides) and can be used from a
**strict DDD / hexagonal host** (plain-PHP domain, framework-free ports, XML mapping, users referenced by id, the wiki as
a separate bounded context). The standard path is the zero-config default; the DDD path is opt-in and never forced
(REQ-ARCH-001, since 1.33.0). Both work in FrankenPHP worker mode with the kernel kept alive and not reset
(REQ-RUNTIME-002, see [FRANKENPHP-WORKER-AUDIT](FRANKENPHP-WORKER-AUDIT.md)), and every string identity the bundle stores
or matches is compared byte for byte (REQ-SEC-006, see [SECURITY](SECURITY.md)).

## Table of contents

- [At a glance](#at-a-glance)
- [Host ports](#host-ports)
- [Operations by id](#operations-by-id)
- [Events](#events)
- [Persistence](#persistence)
- [Mapping and user references](#mapping-and-user-references)
- [Both host kinds, tested](#both-host-kinds-tested)

## At a glance

| | Standard host (default) | DDD host (opt-in) |
|---|---|---|
| Host ports | the 1.x interfaces (Symfony `UserInterface`, `Request`, wiki entities) | their framework-free twins (`WikiUserRef`, snapshots, request-context VOs) |
| Calling the wiki | `WikiPageServiceInterface`, `WikiSpaceServiceInterface`, `WikiPageLinkServiceInterface` (entities) | `WikiPageOperationsInterface`, `WikiSpaceOperationsInterface`, `WikiPageLinkOperationsInterface` (ids, snapshots) |
| Events | entity payloads, `actorId()` | `spaceId()`, `pageId()`, `pageSnapshot()`, `actorRef()`, ... |
| Mapping of the 13 wiki entities | ORM attributes | `database.mapping_type: xml` |
| Host users on wiki rows | `ManyToOne` to `user_class` (a Doctrine entity) | `database.user_reference: id` + `user_directory` |
| Entity manager | `database.entity_manager` (flushed by wiki writes) | `database.private_entity_manager: true` |
| Bundle base classes / traits for host entities | none | none |

The wiki's entities are bundle-owned: a host never extends them and references them by id (`WikiPageSnapshot::$id`,
`WikiSpaceSnapshot::$id`, the id accessors of the events). The bundle ships no mapped superclass and no trait for host
entities.

## Host ports

A host extends the wiki through ports it implements (configuration keys or service aliases). Since 1.33 every port that
took a Symfony `UserInterface`, `Request`, `Response`, `UploadedFile` or a wiki entity has a **twin** that takes and
returns only bundle value objects, enums and scalars:

| Configuration key / alias | Deprecated interface (removed in 2.0) | 1.33 twin |
|---|---|---|
| `security.access_checker` | `Security\WikiAccessCheckerInterface` | `Security\WikiAccessPolicyInterface` |
| `WikiSpaceAccessCheckerInterface` alias | `Security\WikiSpaceAccessCheckerInterface` | `Security\WikiSpaceAccessPolicyInterface` |
| `team_membership_resolver` | `Security\WikiTeamMembershipResolverInterface` | `Security\WikiTeamMembershipSourceInterface` |
| `WikiSpaceAccessResolverInterface` alias | `Service\WikiSpaceAccessResolverInterface` | `Service\WikiSpaceReachInterface` (space ids) |
| `WikiSpaceLocatorInterface` alias | `Service\WikiSpaceLocatorInterface` | `Service\WikiSpaceIdLocatorInterface` (a space id) |
| tag `nowo_wiki.object_link_resolver` (autoconfigured) | `Link\WikiObjectLinkResolverInterface` | `Link\WikiObjectLinkSourceInterface` |
| `WikiUserAvatarResolverInterface` alias | `Avatar\WikiUserAvatarResolverInterface` | `Avatar\WikiUserAvatarSourceInterface` |
| `comments.state_store` | `Panel\WikiPanelStateStoreInterface` | `Panel\WikiPanelStatesInterface` |
| `tree.state_store` | `Tree\WikiTreeStateStoreInterface` | `Tree\WikiTreeStatesInterface` (space id) |
| `collaboration.subscriber_authorizer` | `Collaboration\WikiCollabSubscriberAuthorizerInterface` / `WikiCollabTopicsAuthorizerInterface` | `Collaboration\WikiCollabSubscriptionPolicyInterface` (returns `WikiCollabCookie`s) |
| `api.hooks.authenticator` | `Api\Hook\WikiApiAuthenticatorInterface` | `Api\Hook\WikiApiRequestAuthenticatorInterface` |
| `api.hooks.files` | `Api\Hook\WikiApiFilesInterface` | `Api\Hook\WikiApiFileStoreInterface` |
| `api.hooks.plan_gate` | `Api\Hook\WikiApiPlanGateInterface` | `Api\Hook\WikiApiPlanPolicyInterface` |
| `api.hooks.directory` | `Api\Hook\WikiApiDirectoryInterface` | `Api\Hook\WikiApiWikiDirectoryInterface` (space ids) |
| `WikiAiAssistantInterface` alias | `Ai\WikiAiAssistantInterface` | `Ai\WikiAiAnswererInterface` (returns `Ai\WikiAiReply`) |
| `WikiAttachmentLimitsProviderInterface` alias | `Attachment\WikiAttachmentLimitsProviderInterface` | `Attachment\WikiAttachmentLimitsPolicyInterface` |
| `user_directory` (id mode, new) | — | `User\WikiUserDirectoryInterface` |

Value objects of the twins: `ValueObject\WikiUserRef` (class, id, identifier and roles, copied when the port is called;
`user()` gives back the original object; compare users with `isSameUserAs()`, class and id byte for byte),
`WikiSpaceSnapshot`, `WikiPageSnapshot`, `WikiRevisionSnapshot` (ids and plain values, never an entity or a body),
`WikiRequestContext` (method, path, route, client IP, headers and query; never `Authorization`,
`Proxy-Authorization`, `Cookie`, `X-Api-Key` or `PHP-Auth-*`: the REST API credential reaches the authenticator only as
`$credential`), `Api\Value\WikiApiUpload`, `Api\Value\WikiApiDownload`, `Collaboration\WikiCollabCookie`,
`Ai\WikiAiReply`.

**Selection rule.** The same configuration key or alias takes either interface. A service implementing the deprecated
interface is asked exactly as in 1.32, also when it implements the twin too. One implementing only the twin is asked
through an internal gate (`Port\*Gate`). A host may also alias the twin interface itself; two different host services
(one per interface) fail the build. `DependencyInjection\Compiler\WikiPortsPass` stops the build when a configured
service implements neither, naming the configuration key; a factory or synthetic service, whose class the build cannot
know, is checked when it is built and fails closed.

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

The 1.27 service ports take and return wiki entities. Their 1.33 id twins (public aliases) take ids and `WikiUserRef`
users and return snapshots, with the same events and checks:

| 1.27 entity port | 1.33 id operations |
|---|---|
| `Service\WikiPageServiceInterface` | `Service\WikiPageOperationsInterface`: `findPage`, `createPage`, `saveRevision`, `restoreRevision`, `archivePage`, `unarchivePage` |
| `Service\WikiSpaceServiceInterface` | `Service\WikiSpaceOperationsInterface`: `findSpace`, `createSpace`, `findSpaceBySlug` |
| `Link\WikiPageLinkServiceInterface` | `Link\WikiPageLinkOperationsInterface`: `linkPage`, `unlinkPage`, `usedIn` |

Ids are matched byte for byte; an unknown id, a revision of another page or a parent in another space fails closed
(`InvalidArgumentException`).

## Events

Every event that carries a wiki entity also answers with ids and snapshots, built on call: `spaceId()`, `pageId()`,
`pageSnapshot()`, `revisionId()`, `revisionSnapshot()`, `threadId()`, `commentId()`, `attachmentId()`, ... and the actor
as `actorRef()` (next to the 1.27 `actorId()`). The Symfony-typed members (`WikiQuotaCheckEvent::$user`,
`WikiEditorOptionsEvent::$user`, `WikiApiPrincipal::$user`) are deprecated in favour of `userRef()`. The entity members
stay (a listener that changes a page still needs them) and are not deprecated.

Write events are dispatched after the write is committed (`WikiEventDeferral`): never from inside the bundle's own
flush, and not at all when the write rolls back. `QUOTA_CHECK` runs before a metered write by design (it can refuse
it), `EDITOR_OPTIONS` before the editor is built (no write), and `PAGE_WRITTEN` is internal (the co-editing
bookkeeping, inside the transaction); hosts listen to `PAGE_SAVED`.

## Persistence

**Standard host (default).** The wiki reads and writes through `database.entity_manager` (`default`), exactly as up to
1.32: a wiki save flushes that manager, including what your application left pending in it.

**Private entity manager (opt-in, `database.private_entity_manager: true`; the only mode in 2.0).** Every wiki service
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
- your security user and other host objects on wiki rows, and saved wiki rows a legacy host port loaded through your
  manager (a space from a 1.x directory), are adopted as the private manager's references before each flush; your
  instances are never written. Users read back from wiki rows are the private manager's instances: compare users by
  id, never with `===`. Load wiki rows you pass to the wiki services through the wiki (its repositories or the id
  operations), not through your own manager;
- the wiki's bulk statements inside your open transaction run in a savepoint (`WikiSavepoint`); a failure rolls back to
  it and the ORIGINAL exception is rethrown. A rollback of a wiki savepoint that fails (the server already ended your
  transaction) is logged, never thrown over the original failure.

## Mapping and user references

| | Standard host (default) | DDD host (opt-in) |
|---|---|---|
| Mapping of the 13 wiki entities | ORM attributes | `database.mapping_type: xml` (`Resources/config/doctrine-xml`, generated from the attributes by `scripts/generate-xml-mapping.php`, identical metadata; `XmlMappingParityTest`) |
| Other entity managers | wiki entities transient unless mapped there explicitly (`WikiMappingManagerPass`, DoctrineBundle 3.4 `auto_discover_entities`) | wiki entities transient (`WikiExcludedEntityNamespaceDriver`) |
| Host users on wiki rows | `ManyToOne` to `user_class` (a Doctrine entity in the wiki's manager) | `database.user_reference: id`: the id in a string column (`WikiStoredUserRef`, type `wiki_user_ref`, 128 characters, indexed, no foreign key), found again through `user_directory` |

With the id mode the bundle never joins your user table: DQL compares the id column, lookups go through the directory
(each answer re-checked with `===` on `getId()`), every user-keyed result is re-checked with `===` in PHP before any
limit, erasures select the exact rows first and write by primary key, and the columns compare in binary NO PAD on MySQL
(`utf8mb4_0900_bin`) and MariaDB (`utf8mb4_nopad_bin`). Rows outlive their user.

## Both host kinds, tested

- **Standard host**: the whole unit, integration, database and API suite (attribute mapping, the `TestUserEntity`
  Doctrine user, the deprecated ports, the application's manager), plus its replays, each a CI job: every API host port
  through its twin only (`WIKI_TEST_PORTS=twin`, job `twin-ports`) and the private entity manager on
  (`WIKI_TEST_PRIVATE_EM=1`, job `private-entity-manager`, SQLite and MySQL).
- **DDD host**: `tests/Api/DddHost` (`DddHostKernel`: a plain-PHP security user with no mapping and no user table, the
  XML mapping, users stored by id with a directory, the private entity manager, the twin ports only, the id
  operations; a two-person kept-alive kernel run through `http_kernel->handle()` / `terminate()`), plus
  `XmlMappingParityTest`, `WikiUserReferenceByIdTest` (also on MySQL and MariaDB) and the private entity manager tests
  (`tests/Api/PrivateEntityManager`).
