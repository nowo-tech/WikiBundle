# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/wiki-bundle`  
**Last audited**: 2026-07-07

This file proves that **every production source artifact** under `src/` is referenced by the baseline specification. Test-only files under `tests/` and `*.test.ts` under `src/` are out of Packagist scope. Built assets under `Resources/public/` are documented as Vite/build outputs.

## Bundle & DI

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `DependencyInjection/Compiler/TwigPathsPass.php` | Compiler pass | FR-DI-002 |
| `DependencyInjection/Configuration.php` | Config tree | FR-CFG-001 |
| `DependencyInjection/WikiExtension.php` | DI extension | FR-CFG-002 |
| `WikiBundle.php` | Bundle entry | FR-BUNDLE-001 |

## CLI

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Command/WikiExportCommand.php` | CLI command | FR-CLI-001 |
| `Command/WikiImportCommand.php` | CLI command | FR-CLI-001 |

## Controllers

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Controller/WikiCsrfTrait.php` | CSRF trait | FR-SEC-003 |
| `Controller/WikiManageController.php` | Manage UI controller | FR-UI-001 |

## Persistence

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Entity/WikiPage.php` | Persistence model | FR-ENTITY-001 |
| `Entity/WikiPageRevision.php` | Persistence model | FR-ENTITY-001 |
| `Entity/WikiSpace.php` | Persistence model | FR-ENTITY-001 |
| `Repository/DoctrineOrmWikiPageRepository.php` | Repository implementation | FR-REPO-002 |
| `Repository/DoctrineOrmWikiPageRevisionRepository.php` | Repository implementation | FR-REPO-002 |
| `Repository/DoctrineOrmWikiSpaceRepository.php` | Repository implementation | FR-REPO-002 |
| `Repository/WikiPageRepositoryInterface.php` | Repository contract | FR-REPO-001 |
| `Repository/WikiPageRevisionRepositoryInterface.php` | Repository contract | FR-REPO-001 |
| `Repository/WikiSpaceRepositoryInterface.php` | Repository contract | FR-REPO-001 |

## Forms

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Form/WikiPageFormType.php` | Symfony form type | FR-FORM-001 |

## Domain models

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Dto/WikiExportReport.php` | Transfer object | FR-DTO-001 |
| `Dto/WikiImportReport.php` | Transfer object | FR-DTO-001 |
| `Dto/WikiPageFormData.php` | Transfer object | FR-DTO-001 |
| `Enum/WikiInterchangeFormat.php` | Domain enum | FR-MDL-001 |
| `Enum/WikiSpaceOwnerScope.php` | Domain enum | FR-MDL-001 |

## Application services

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Event/WikiEvents.php` | Domain events | FR-EVT-001 |
| `Resources/assets/src/wiki.css` | Wiki editor styles | FR-UI-011 |
| `Service/WikiAuthorResolver.php` | Author display name | FR-PAGE-002 |
| `Service/WikiPageArchivedEvent.php` | Page archived event | FR-EVT-002 |
| `Service/WikiPageSavedEvent.php` | Page saved event | FR-EVT-002 |
| `Service/WikiPageService.php` | Page CRUD + revisions | FR-PAGE-001 |
| `Service/WikiPageTreeBuilder.php` | Page tree builder | FR-TREE-001 |
| `Service/WikiRevisionDiffService.php` | Revision diff | FR-REV-001 |
| `Service/WikiSearchService.php` | Full-text search | FR-SEARCH-001 |
| `Service/WikiSpaceAccessResolver.php` | Space ACL resolver | FR-SEC-001 |
| `Service/WikiSpaceAccessResolverInterface.php` | Space ACL contract | FR-SEC-001 |
| `Service/WikiSpaceService.php` | Space CRUD | FR-SPACE-001 |

## Security

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Security/ConfigurableWikiAccessChecker.php` | Configurable access checker | FR-SEC-001 |
| `Security/NullWikiTeamMembershipResolver.php` | Team membership resolver | FR-SEC-006 |
| `Security/WikiAccessCheckerInterface.php` | Access checker contract | FR-SEC-001 |
| `Security/WikiHtmlSanitizer.php` | HTML sanitizer impl | FR-SEC-005 |
| `Security/WikiHtmlSanitizerInterface.php` | Sanitizer contract | FR-SEC-005 |
| `Security/WikiTeamMembershipResolverInterface.php` | Team membership contract | FR-SEC-006 |

## AI

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Ai/Exception/WikiAiUnavailableException.php` | AI unavailable exception | FR-AI-003 |
| `Ai/NullWikiAiAssistant.php` | Null AI assistant | FR-AI-004 |
| `Ai/SymfonyAiWikiAssistant.php` | Symfony AI assistant | FR-AI-001 |
| `Ai/Tool/WikiKnowledgeSearchTool.php` | AI knowledge tool | FR-AI-002 |
| `Ai/WikiAiAnswer.php` | AI answer DTO | FR-AI-001 |
| `Ai/WikiAiAssistantInterface.php` | AI assistant contract | FR-AI-001 |
| `Ai/WikiContextRetriever.php` | AI context retrieval | FR-AI-005 |

## Routing

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Routing/WikiRouteLoader.php` | Route loader | FR-ROUTE-001 |

## Persistence integration

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Doctrine/WikiMetadataListener.php` | Persistence integration | FR-DB-001 |
| `Interchange/WikiArchiveHelper.php` | Import/export interchange | FR-IX-001 |
| `Interchange/WikiDocumentExporter.php` | Import/export interchange | FR-IX-001 |
| `Interchange/WikiDocumentImporter.php` | Import/export interchange | FR-IX-001 |
| `Interchange/WikiDocumentNode.php` | Import/export interchange | FR-IX-001 |
| `Interchange/WikiDocumentTreeReader.php` | Import/export interchange | FR-IX-001 |
| `Interchange/WikiFormatDetector.php` | Import/export interchange | FR-IX-001 |
| `Interchange/WikiFrontMatterParser.php` | Import/export interchange | FR-IX-001 |
| `Interchange/WikiMarkdownConverter.php` | Import/export interchange | FR-IX-001 |

## Support utilities

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Util/WikiSlugger.php` | Support utility | FR-UTIL-001 |
| `ValueObject/Uuid.php` | Support utility | FR-UTIL-001 |

## Frontend TypeScript

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/assets/src/wiki.ts` | Wiki Tiptap editor | FR-UI-010 |
| `Resources/public/wiki.css` | Built frontend asset | FR-BUILD-001 |
| `Resources/public/wiki.js` | Built frontend asset | FR-BUILD-001 |

## Symfony config

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/config/services.yaml` | Service wiring | FR-DI-001 |

## Translations

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/translations/NowoWikiBundle.de.yaml` | i18n messages | FR-I18N-004 |
| `Resources/translations/NowoWikiBundle.en.yaml` | i18n messages | FR-I18N-004 |
| `Resources/translations/NowoWikiBundle.es.yaml` | i18n messages | FR-I18N-004 |
| `Resources/translations/NowoWikiBundle.fr.yaml` | i18n messages | FR-I18N-004 |
| `Resources/translations/NowoWikiBundle.it.yaml` | i18n messages | FR-I18N-004 |
| `Resources/translations/NowoWikiBundle.nl.yaml` | i18n messages | FR-I18N-004 |
| `Resources/translations/NowoWikiBundle.pt.yaml` | i18n messages | FR-I18N-004 |

## Twig views

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/views/layout.html.twig` | Layout template | FR-VIEW-001 |
| `Resources/views/manage/_ai_ask_form_fields.html.twig` | Manage UI template | FR-VIEW-005 |
| `Resources/views/manage/_ai_ask_modal.html.twig` | Manage UI template | FR-VIEW-005 |
| `Resources/views/manage/_page_header.html.twig` | Manage UI template | FR-VIEW-005 |
| `Resources/views/manage/_page_tree.html.twig` | Manage UI template | FR-VIEW-005 |
| `Resources/views/manage/ai_ask.html.twig` | Manage UI template | FR-VIEW-005 |
| `Resources/views/manage/index.html.twig` | Manage UI template | FR-VIEW-005 |
| `Resources/views/manage/page_diff.html.twig` | Manage UI template | FR-VIEW-005 |
| `Resources/views/manage/page_edit.html.twig` | Manage UI template | FR-VIEW-005 |
| `Resources/views/manage/page_history.html.twig` | Manage UI template | FR-VIEW-005 |
| `Resources/views/manage/page_view.html.twig` | Manage UI template | FR-VIEW-005 |
| `Resources/views/manage/search.html.twig` | Manage UI template | FR-VIEW-005 |
| `Resources/views/manage/space.html.twig` | Manage UI template | FR-VIEW-005 |

## Coverage summary

| Category | Files | Mapped |
| --- | ---: | ---: |
| Bundle & DI | 4 | 4 |
| CLI | 2 | 2 |
| Controllers | 2 | 2 |
| Persistence | 9 | 9 |
| Forms | 1 | 1 |
| Domain models | 5 | 5 |
| Application services | 12 | 12 |
| Security | 6 | 6 |
| AI | 7 | 7 |
| Routing | 1 | 1 |
| Persistence integration | 9 | 9 |
| Support utilities | 2 | 2 |
| Frontend TypeScript | 3 | 3 |
| Symfony config | 1 | 1 |
| Translations | 7 | 7 |
| Twig views | 13 | 13 |
| **Total production sources** | **84** | **84** |

Audit: `find src -type f ! -path '*/assets/dist/*' ! -name '*.test.ts' | wc -l`
