# PHP-FIG PSR evaluation (REQ-CS-007)

Package: `nowo-tech/wiki-bundle` (`symfony-bundle`)

This document records which [PHP-FIG PSRs](https://www.php-fig.org/psr/) apply to this package.
Only contracts that add clear interoperability or maintainability value are **Adopted**.
Others are **N/A** (or already covered by Symfony) so the decision stays auditable.

## Table of contents

- [Baseline (always)](#baseline-always)
- [Interface / contract PSRs](#interface--contract-psrs)
- [Summary](#summary)

## Baseline (always)

| PSR | Decision | How |
| --- | -------- | --- |
| PSR-12 (coding style) | **Adopted** | `@PSR12` in `.php-cs-fixer.dist.php` (Nowo REQ-CS-001). |
| PSR-4 (autoloading) | **Adopted** | `composer.json` `autoload` / `autoload-dev` PSR-4 map for `src/` and `tests/`. |

## Interface / contract PSRs

| PSR | Decision | Notes |
| --- | -------- | ----- |
| PSR-3 Logger | **Adopted** | `psr/log` in `require`. `WikiDocumentImporter`, `WikiDocumentExporter`, `WikiPageService` and `SymfonyAiWikiAssistant` accept an optional `Psr\Log\LoggerInterface` (wired `@?logger`, Monolog channel `nowo_wiki`, `NullLogger` fallback). Tests use a PSR-3 `AbstractLogger` recorder. |
| PSR-6 / PSR-16 Cache | **N/A** | No package-owned cache layer. |
| PSR-7 / PSR-17 HTTP messages | **N/A** | The manage UI is plain Symfony HttpFoundation controllers; no HTTP-message API for consumers. |
| PSR-18 HTTP client | **N/A — Symfony HttpClient SPI** | The only outbound HTTP is the LLM call made by `symfony/ai-platform`, which requires a Symfony `HttpClientInterface`; the bundle exposes `nowo_wiki.ai.http_client` (`http_client->withOptions(timeout, max_duration)`) as that client. A PSR-18 type would not fit the platform API. Timeouts per REQ-RUNTIME-001 ([CONFIGURATION.md](CONFIGURATION.md#timeout-hierarchy-req-runtime-001)). |
| PSR-11 Container | **N/A** | No container locator in the public API; constructor injection only. |
| PSR-14 Event dispatcher | **Already satisfied via Symfony** | `WikiPageService` dispatches `WikiPageSavedEvent` / `WikiPageArchivedEvent` through Symfony `EventDispatcherInterface` (PSR-14 compatible). |
| PSR-15 HTTP middleware | **N/A** | No middleware pipeline. |
| PSR-20 Clock | **N/A** | Timestamps (`createdAt`, `updatedAt`, `archivedAt`) are plain `DateTimeImmutable` set by entities; no time-dependent logic (TTL, tokens, scheduling) that would need an injectable clock. |

## Summary

- **Adopted beyond baseline:** PSR-3 (logging of imports, exports, archiving and AI calls).
- **Rule:** do not add `psr/*` Composer dependencies without matching type-hints and DI wiring.
- **Re-evaluate** when the package gains cache, clock or HTTP-message SPIs.

---

_REQ-CS-007 evaluation date: 2026-10-29._
