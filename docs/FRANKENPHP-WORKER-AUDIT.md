# FrankenPHP worker mode audit (kernel not reset between requests)

| Field | Value |
|-------|-------|
| Package | `nowo-tech/wiki-bundle` (`symfony-bundle`) |
| Audited revision | `v1.3.4` / `f9ccad7` |
| Audit date | 2026-09-23 |
| Method | Manual review of every PHP file under `src/` (DI extension, compiler pass, `services.yaml`, controller, services, repositories, Doctrine listener, security, AI services, interchange, form type, route loader, commands); entities, DTOs and enums skimmed |
| Remediation (2026-09-23) | W-01, W-02, W-03 and W-04 fixed (uncommitted, `[Unreleased]`); W-05 unchanged (Info). Regression tests simulate consecutive requests on one container without `reset()` |
| **Verdict** | ✅ **Viable under scenario B** — repositories and search resolve the EntityManager per call (closed manager recovered, reads refreshed from the database), access decisions ignore a token left by a previous request, failed exports leave no ZIP, and AI calls can use a bundle HTTP client with timeout. Hosts still own the identity-map lifetime and firewall layout (see usage recommendations). |

## Execution model assumed

FrankenPHP worker mode boots the Symfony kernel once per worker and serves many requests with the same container. This audit assumes the **strict** variant: the kernel is **not** rebooted between requests, so every shared service, static property and PHP global survives from one request to the next. Two scenarios are evaluated:

- **A — kernel not rebooted, `services_resetter` still runs:** services tagged `kernel.reset` (or implementing `ResetInterface`) are reset between requests.
- **B — no reset at all:** nothing is reset; any per-request state kept in a service leaks into the next request.

A bundle that is safe under **B** is safe under **A** and under classic mode / PHP-FPM.

## Summary

| Area | Status | Notes |
|------|--------|-------|
| Mutable state in shared services | ✅ | Almost every service is `final readonly`; the only mutable properties are `WikiRouteLoader::$loaded` (route build time) and the FormKit trait builder, restored in `finally` |
| Static properties / `static` locals | ✅ | None; only pure static factories in `ValueObject\Uuid` and `static` closures |
| `ResetInterface` / `kernel.reset` coverage | ✅ N/A | No bundle service needs a reset; no longer relies on Doctrine / Security resets (W-01, W-02 resolved) |
| Request / user / locale captured in services | ✅ | User comes from `getUser()` / `Security::getUser()` at call time, only when `WikiTokenGuard` confirms the main request went through a secured firewall; nothing stored |
| Superglobals, `$_ENV`, `putenv`, `ini_set`, `setlocale`, timezone | ✅ | None used; config is compiled into container parameters |
| Doctrine / EntityManager | ✅ | Repositories and `WikiSearchService` resolve the manager per call through `WikiEntityManagerProvider` (closed manager reset); reads use `refresh()` / `HINT_REFRESH` (W-01 resolved) |
| Output, headers, `exit`, shutdown functions | ✅ | None; responses are Symfony `Response` / `BinaryFileResponse` |
| Resources (files, sockets, cURL) held open | ✅ | `ZipArchive` closed per call; failed exports remove the ZIP in `catch` (W-03 resolved) |
| Memory growth across requests | ✅ Accepted | Bundle has no caches; the identity map of the host's EntityManager is not cleared by the bundle on purpose (clearing would detach host entities) — see W-01 |
| Blocking I/O and timeouts | ✅ | `nowo_wiki.ai.http_client` (`ai.http_timeout`, default 30 s idle + max duration) for the AI platform (W-04 resolved; host wires it) |
| Third-party static state | ✅ | `league/commonmark` / `league/html-to-markdown` converters built once per service and reused; no static state touched |
| PHPStan FrankenPHP rulesets | ✅ | `ruleset-classic.neon` + `ruleset-worker.neon` included in `phpstan.neon.dist` |

## Services reviewed

`src/Resources/config/services.yaml` registers everything under `src/` (minus the excluded folders) as private, autowired, shared services; controllers are public. `WikiExtension` registers repositories, the Doctrine listener, access checkers, the space access resolver and the AI services explicitly.

| Service | Shared | Mutable state | Scenario A | Scenario B |
|---------|--------|---------------|------------|------------|
| `Controller\WikiManageController` | yes (public) | none (`readonly` deps + config arrays) | ✅ | ✅ (W-01, W-02 resolved) |
| `Repository\DoctrineOrmWikiSpaceRepository`, `DoctrineOrmWikiPageRepository`, `DoctrineOrmWikiPageRevisionRepository` | yes | none (`final readonly`, `WikiEntityManagerProvider`) | ✅ | ✅ (W-01 resolved) |
| `Doctrine\WikiEntityManagerProvider` | yes | none (`final readonly`, `ManagerRegistry` + manager name) | ✅ | ✅ |
| `Service\WikiSearchService` | yes | none (`final readonly`, `WikiEntityManagerProvider`) | ✅ | ✅ (W-01 resolved) |
| `Service\WikiAuthorResolver` | yes | none (`final readonly`, EM reference); only used by the CLI import command | ✅ | ✅ Accepted (CLI, see W-01) |
| `Service\WikiPageService`, `WikiSpaceService`, `WikiRevisionDiffService`, `WikiPageTreeBuilder` | yes | none | ✅ | ✅ (depend on repositories) |
| `Service\WikiSpaceAccessResolver` | yes | none (`readonly` scope string); queries accessible spaces on every call, no caching | ✅ | ✅ |
| `Security\ConfigurableWikiAccessChecker` | yes | none (`readonly` role arrays + `Security`) | ✅ | ✅ (W-02 resolved) |
| `Security\WikiTokenGuard` | yes | none (`RequestStack` + `?Security`, read per call) | ✅ | ✅ |
| `Security\AllowAllWikiAccessChecker`, `NullWikiTeamMembershipResolver`, `WikiHtmlSanitizer` | yes | none | ✅ | ✅ |
| `Doctrine\WikiMetadataListener` (`loadClassMetadata`) | yes | none (`readonly` table names); runs only when metadata is loaded | ✅ | ✅ |
| `Ai\SymfonyAiWikiAssistant`, `Ai\WikiContextRetriever`, `Ai\NullWikiAiAssistant` | yes | none (`readonly`); `MessageBag` created per call | ✅ | ✅ |
| `Ai\Tool\WikiKnowledgeSearchTool` | yes | none; reads user from `Security` at call time, guarded by `WikiTokenGuard` | ✅ | ✅ (W-02 resolved) |
| `Interchange\WikiDocumentImporter`, `WikiDocumentExporter`, `WikiDocumentTreeReader`, `WikiFormatDetector`, `WikiFrontMatterParser`, `WikiArchiveHelper` | yes | none; per-call maps (`$pathMap`, `$slugMap`) are local variables | ✅ | ✅ |
| `Interchange\WikiMarkdownConverter` | yes | `readonly` CommonMark + HtmlConverter built in constructor, reused per call | ✅ | ✅ |
| `Form\WikiPageFormType` | yes | FormKit trait: `$formKitBoundBuilder` set and restored in `finally`; config name memoised (constant per class) | ✅ | ✅ |
| `Routing\WikiRouteLoader` | yes | `$loaded` flag (W-05) | ✅ | ✅ |
| `Util\WikiSlugger` | yes | none | ✅ | ✅ |
| 2 console commands | yes | `readonly` deps; CLI only | ✅ N/A | ✅ N/A |

Entities (`WikiSpace`, `WikiPage`, `WikiPageRevision`), DTOs, events and `Uuid` are created per call and are never stored in a service property. Because the resource block does not exclude `Entity/`, `Dto/` or `ValueObject/`, those classes are also registered as private services; they are never injected, so the container removes them at compile time.

## Findings

### W-01 — Relies on Doctrine's reset for a closed or bloated EntityManager (Medium)

- **Where:** `src/DependencyInjection/WikiExtension.php:125-136` injects `doctrine.orm.<entity_manager>_entity_manager` directly into the three repositories (`src/Repository/DoctrineOrm*Repository.php:13-16`); `src/Service/WikiSearchService.php:19-22` and `src/Service/WikiAuthorResolver.php:17-21` are autowired with `EntityManagerInterface`. Every `save()` calls `persist()` + `flush()` (`src/Repository/DoctrineOrmWikiPageRepository.php:18-22`, same in the other two). `WikiDocumentImporter::import()` calls `WikiPageService::create()` / `saveRevision()` in a loop and only catches `InvalidArgumentException` (`src/Interchange/WikiDocumentImporter.php:73-88`).
- **Worker impact:**
  - Scenario A: DoctrineBundle's `doctrine` registry is reset between requests, which clears the EntityManager and recreates it if it was closed. The bundle is fine.
  - Scenario B: a DBAL error during any `flush()` (unique constraint on slug under concurrency, lost connection, etc.) closes the EntityManager and every later wiki request on that worker fails with "EntityManager is closed". The identity map is never cleared, so pages, revisions and spaces loaded by earlier requests stay in memory (unbounded growth) and are returned stale: `findOneBy()` / `find()` return the already-managed instance without refreshing it, so a page archived or renamed by another worker can still be shown as active here.
- **Recommendation:** keep `services_resetter` enabled (scenario A). If an application runs without it, it must call `ManagerRegistry::resetManager()` / `EntityManager::clear()` at the end of each request. In the bundle, injecting `ManagerRegistry` and resolving the manager per call would make recovery from a closed manager explicit.
- **Status:** Resolved — new `Doctrine\WikiEntityManagerProvider` (`ManagerRegistry` + `database.entity_manager`) resolves the manager on every call and resets it through the registry when a previous request closed it. The three repositories and `WikiSearchService` receive the provider (constructors still accept an `EntityManagerInterface`, BC). Freshness: `findById()` does `find()` + `refresh()`, and `findBySlug()`, `findFirstBySlug()`, `findActiveBySpace()`, `findAccessible()` and search queries set `Query::HINT_REFRESH`, so a page archived/renamed or a space renamed by another worker is not served stale. The bundle does not clear or detach the application's EntityManager (wiki entities are referenced by the host and by later writes in the same request); identity-map lifetime stays the host's responsibility. `WikiAuthorResolver` keeps the injected EntityManager: it is only used by the `nowo:wiki:import` CLI command — Accepted. Tests: `tests/Integration/WikiRepositoryWorkerTest.php` (two EntityManagers on one SQLite file = two workers; second request on worker B sees the other worker's changes; closed manager recovered), `tests/Unit/Doctrine/WikiEntityManagerProviderTest.php`.

### W-02 — Security decisions depend on the token storage being reset (Medium)

- **Where:** `src/Controller/WikiManageController.php:496-530` (`denyUnlessFeature()`, `requireUser()` via `getUser()`), `src/Security/ConfigurableWikiAccessChecker.php:87-110` (`Security::isGranted()`), `src/Ai/Tool/WikiKnowledgeSearchTool.php:39` (`Security::getUser()`). The bundle itself stores no user; `requireAccessibleSpace()` (`:532-542`) re-queries accessible spaces on every request.
- **Worker impact:** correct under scenario A, because Symfony tags `security.token_storage` with `kernel.reset`. Under scenario B the previous request's token can survive if the firewall of the current request does not overwrite it (for example a wiki route placed outside any firewall, or a stateless firewall that fails before setting a token). Then user X could be treated as user Y for space listing, page view, edit and AI search. This is a framework-level behaviour, not a bug in the bundle, but the bundle's access control depends on it.
- **Recommendation:** keep `services_resetter` enabled, and always put the wiki routes (`route_prefix`) behind a firewall. Never run this bundle in scenario B with `security.allow_unauthenticated: false` expecting per-user isolation.
- **Status:** Resolved — new `Security\WikiTokenGuard` trusts the token only when the main request is handled by a firewall with security enabled (`Security::getFirewallConfig($request)->isSecurityEnabled()`; without SecurityBundle it checks the `_firewall_context` request attribute; outside HTTP it trusts the storage). `WikiManageController` (`getUser()` → `currentUser()`), `ConfigurableWikiAccessChecker` (`isGranted()` returns deny) and `WikiKnowledgeSearchTool` treat an untrusted token as anonymous. The guard is an optional last constructor argument (BC). Residual, host responsibility: a *stateless* secured firewall that fails before setting a new token still runs behind a firewall, so the stale token is not detected by the bundle; such firewalls must not keep the previous token (Symfony's authenticators replace it on success and the firewall rejects on failure). Tests: `tests/Unit/Security/WikiTokenGuardTest.php`, consecutive-request tests in `WikiManageControllerTest`, `ConfigurableWikiAccessCheckerRolesTest`, `WikiKnowledgeSearchToolTest`.

### W-03 — Temporary export ZIP can be left behind (Low)

- **Where:** `src/Controller/WikiManageController.php:343-365`. The working directory is removed in `finally` (`:354-356`), and the ZIP is deleted by `BinaryFileResponse::deleteFileAfterSend(true)` (`:363`). If `export()` or `createZipFromDirectory()` throws after the ZIP file is created, or the response is never fully sent, `$zipPath` stays in `sys_get_temp_dir()`. Import cleans up correctly in `finally` (`:424-428`, `src/Interchange/WikiDocumentImporter.php:90-94`).
- **Worker impact:** no state leak, but in a long-lived worker container nobody cleans `/tmp` at process exit, so failed exports accumulate on disk.
- **Recommendation:** remove `$zipPath` in a `catch` when building the archive fails; mount a tmpfs or cron-clean `wiki-export-*` files.
- **Status:** Resolved — partly not a bug: `ZipArchive` only writes the file in `close()` (last step of `createZipFromDirectory()`), and `BinaryFileResponse` deletes it in `sendContent()`'s `finally`, so the realistic leak window was small. `exportSpace()` now removes `$zipPath` in a `catch (Throwable)` and rethrows; the working directory is still removed in `finally`. Test: `WikiManageControllerTest::testExportSpaceFailureLeavesNoTemporaryFiles`. A response that is built but never sent (client abort before `sendContent()`) remains a host concern (tmpfs / cleanup).

### W-04 — AI requests have no bundle-level timeout (Low)

- **Where:** `src/Ai/SymfonyAiWikiAssistant.php:66` (`$this->agent->call($messages)`), called from `WikiManageController::askAi()` (`src/Controller/WikiManageController.php:473`).
- **Worker impact:** a slow LLM provider blocks one of the limited worker threads for the whole HTTP client timeout configured in `symfony/ai` / `framework.http_client` (the bundle sets none). No state leaks; errors are caught and shown as `wiki.ai.error`.
- **Recommendation:** configure `timeout` / `max_duration` on the HTTP client used by the AI platform, and cap queueing with FrankenPHP `max_wait_time`.
- **Status:** Resolved — `symfony/ai` platforms have no per-call timeout option, so when `ai.enabled` is true the bundle registers `nowo_wiki.ai.http_client` (`http_client->withOptions(timeout, max_duration)`), configurable with the new `ai.http_timeout` (seconds, default 30, 1–600). Hosts set it as `http_client` of their platform in `config/packages/ai.yaml` (the demo does it for OpenAI and Ollama). Tests: `WikiExtensionTest::testAiHttpClientUsesConfiguredTimeout`, `ConfigurationTest`.

### W-05 — `WikiRouteLoader` uses a one-shot `$loaded` flag (Info)

- **Where:** `src/Routing/WikiRouteLoader.php:15`, `:31-35`.
- **Worker impact:** routes are loaded when the router cache is built, not per request, and the Router memoises its collection, so this is not reached in normal worker traffic. It would throw `Wiki routes already loaded.` only if the same loader instance were asked to load routes twice in one process (for example a custom tool that rebuilds routes inside a running worker). This is the standard Symfony custom-loader pattern.
- **Recommendation:** none required.
- **Status:** Accepted — no change.

Good patterns observed: all services are `final readonly` or have no properties; no in-process caches; space access is re-evaluated on every request; the FormKit builder binding is restored in `finally`; imports clean their extracted directory in `finally`.

## Usage recommendations in worker mode

- The bundle works without `services_resetter` (scenario B). Keeping it enabled is still recommended for the rest of the application (Doctrine identity map, token storage).
- Put all wiki routes behind a secured firewall: outside one the bundle now treats the user as anonymous. Use a real `security.access_checker` or the default role-based checker; `security.allow_unauthenticated: true` intentionally allows everything.
- Custom `WikiTeamMembershipResolverInterface`, `WikiAccessCheckerInterface` or `WikiSpaceAccessResolverInterface` implementations must stay stateless (no per-user cache in properties) or implement `ResetInterface`; otherwise team/space membership of one user can leak to the next request.
- Set `http_client: 'nowo_wiki.ai.http_client'` on the AI platform (tune `nowo_wiki.ai.http_timeout`) and set FrankenPHP `max_wait_time`.
- Large imports run inside the request: keep `import_export.max_upload_bytes` reasonable and prefer `nowo:wiki:import` (CLI) for big archives.
- If you cannot keep the Doctrine reset, set a low worker `max_requests` (or `FRANKENPHP_LOOP_MAX`) to bound identity-map growth; closed managers are already recovered by the bundle.
- The demo (`demo/symfony8/Caddyfile`) runs FrankenPHP with a `worker` block (`file /app/public/index.php`, `watch`), and `docker-compose.yml` defaults `FRANKENPHP_MODE` to `worker`.

## Re-audit triggers

Re-run this audit when a change adds: properties or caches to any service (especially the access resolver, access checker, search service or AI retriever), an event listener/subscriber, a Twig extension with state, a repository or service that injects `EntityManagerInterface` directly instead of `WikiEntityManagerProvider`, a new access decision that reads the token without `WikiTokenGuard`, `ResetInterface` implementations, or any use of `$_SERVER` / `$_ENV` at runtime.
