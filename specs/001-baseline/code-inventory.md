# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/wiki-bundle`  
**Last audited**: 2026-11-25 (every file of `src/` mapped, including 1.42 to 1.55.2; audit: `find src -type f ! -path '*/assets/dist/*' ! -name '*.test.ts' | wc -l` → 610)

This file proves that **every production source artifact** under `src/` is referenced by the baseline specification. Test-only files under `tests/` and `*.test.ts` under `src/` are out of Packagist scope. Built assets under `Resources/public/` are documented as Vite/build outputs.

## Bundle & DI

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `DependencyInjection/Compiler/CsrfProtectionPass.php` | Refuses to compile the Web UI without form CSRF protection | FR-SEC-003 |
| `DependencyInjection/Compiler/TwigPathsPass.php` | Compiler pass | FR-DI-002 |
| `DependencyInjection/Configuration.php` | Config tree | FR-CFG-001 |
| `DependencyInjection/WikiExtension.php` | DI extension | FR-CFG-002 |
| `WikiBundle.php` | Bundle entry | FR-BUNDLE-001 |

## CLI

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Command/WikiCollabPurgeCommand.php` | `wiki:collab:purge`: deletes the co-editing logs of pages untouched for N days | FR-MD-004 |
| `Command/WikiCommentsEraseUserCommand.php` | `wiki:comments:erase-user`: exports or erases a user's comments and mentions (privacy requests) | FR-COMMENT-003 |
| `Command/WikiExportCommand.php` | CLI command | FR-CLI-001 |
| `Command/WikiImportCommand.php` | CLI command | FR-CLI-001 |
| `Command/WikiSearchBackfillTextCommand.php` | `wiki:search:backfill-text`: fills `content_text` of older revisions | FR-SEARCH-003 |

## Controllers

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Controller/WikiAttachmentController.php` | Upload and download of page attachments; 404 to every denied download | FR-ATT-001 / FR-EVT-003 |
| `Controller/WikiCommentController.php` | Comment endpoints of a page under `page_comments`, panel state endpoint | FR-COMMENT-001 / FR-PANEL-001 |
| `Controller/WikiCsrfTrait.php` | CSRF trait | FR-SEC-003 |
| `Controller/WikiManageController.php` | Manage UI controller | FR-UI-001 |

## Persistence

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Entity/WikiPage.php` | Persistence model | FR-ENTITY-001 |
| `Entity/WikiPageLink.php` | Link from a page to a host object (type and id) | FR-LINK-001 |
| `Entity/WikiPageRevision.php` | Persistence model | FR-ENTITY-001 |
| `Entity/WikiSpace.php` | Persistence model | FR-ENTITY-001 |
| `Repository/DoctrineOrmWikiPageLinkRepository.php` | Doctrine storage of page links | FR-LINK-001 |
| `Repository/DoctrineOrmWikiPageRepository.php` | Repository implementation | FR-REPO-002 |
| `Repository/DoctrineOrmWikiPageRevisionRepository.php` | Repository implementation | FR-REPO-002 |
| `Repository/DoctrineOrmWikiSpaceRepository.php` | Repository implementation | FR-REPO-002 |
| `Repository/WikiPageLinkRepositoryInterface.php` | Persistence contract of page links | FR-LINK-001 |
| `Repository/WikiPageRepositoryInterface.php` | Repository contract | FR-REPO-001 |
| `Repository/WikiPageRevisionRepositoryInterface.php` | Repository contract | FR-REPO-001 |
| `Repository/WikiSpaceRepositoryInterface.php` | Repository contract | FR-REPO-001 |

## Forms

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Form/WikiAiAskType.php` | AI question form (space / q / CSRF; REQ-TWIG-005) | FR-FORM-001 |
| `Form/WikiFormIdsTrait.php` | Shared element-id options of the wiki form types | FR-FORM-001 |
| `Form/WikiImportType.php` | Space import form (format / archive / update_existing; REQ-TWIG-005) | FR-FORM-001 |
| `Form/WikiPageFormType.php` | Symfony form type | FR-FORM-001 |
| `Form/WikiPostActionType.php` | Flat POST form with only its CSRF field (restore, unarchive) | FR-FORM-001 |
| `Form/WikiSearchType.php` | Search filter form (GET space / q; REQ-TWIG-005) | FR-FORM-001 |

## Domain models

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Dto/WikiExportReport.php` | Transfer object | FR-DTO-001 |
| `Dto/WikiImportReport.php` | Transfer object | FR-DTO-001 |
| `Dto/WikiPageFormData.php` | Transfer object | FR-DTO-001 |
| `Enum/WikiAction.php` | Actions asked of the space access checker | FR-SEC-010 |
| `Enum/WikiInterchangeFormat.php` | Domain enum | FR-MDL-001 |
| `Enum/WikiSpaceOwnerScope.php` | Domain enum | FR-MDL-001 |

## Application services

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Event/WikiEditorOptionsEvent.php` | Host options for the page editor, before it is built | FR-EVT-002 |
| `Event/WikiEvents.php` | Domain events | FR-EVT-001 |
| `Event/WikiPageCreatedEvent.php` | After a page was created | FR-EVT-002 |
| `Event/WikiPageDuplicatedEvent.php` | 1.41: after the tree menu's Duplicate wrote a copy (source, copy), for host access rules | FR-EVT-002 |
| `Event/WikiPageRestoredEvent.php` | After an old revision was restored as a new one | FR-EVT-002 |
| `Event/WikiPageUnarchivedEvent.php` | After a page was unarchived | FR-EVT-002 |
| `Event/WikiQuotaCheckEvent.php` | Cancellable check before a metered write (1.21) | FR-EVT-003 |
| `Interchange/WikiExportIndexWriter.php` | `README.md` index of an export, listing only exported pages | FR-IX-001 |
| `Resources/assets/src/wiki-comments-moderation.ts` | Comments panel extras: moderation list, error notices (1.21) | FR-UI-012 |
| `Resources/assets/src/wiki.css` | Wiki editor styles | FR-UI-011 |
| `Resources/views/manage/_import_export_dialog.html.twig` | Import & export dialog (1.21) | FR-UI-012 |
| `Search/WikiPlainText.php` | Plain text of a page body: what search matches and excerpts show | FR-SEARCH-003 |
| `Search/WikiSearchScopeListener.php` | `search.scope: space` (1.21) | FR-SEARCH-002 |
| `Service/AccessibleWikiSpaceLocator.php` | Default space locator among the user's accessible spaces | FR-SEC-010 |
| `Service/WikiAuthorResolver.php` | Author display name | FR-PAGE-002 |
| `Service/WikiPageArchivedEvent.php` | Page archived event | FR-EVT-002 |
| `Service/WikiPageSavedEvent.php` | Page saved event | FR-EVT-002 |
| `Service/WikiPageService.php` | Page CRUD + revisions | FR-PAGE-001 |
| `Service/WikiPageTreeBuilder.php` | Page tree builder | FR-TREE-001 |
| `Service/WikiQuotaGate.php` | Dispatches the quota check, resolves the refusal text (1.21) | FR-EVT-003 |
| `Service/WikiRevisionDiffService.php` | Revision diff | FR-REV-001 |
| `Service/WikiSearchService.php` | Full-text search | FR-SEARCH-001 |
| `Service/WikiSpaceAccessResolver.php` | Space ACL resolver | FR-SEC-001 |
| `Service/WikiSpaceAccessResolverInterface.php` | Space ACL contract | FR-SEC-001 |
| `Service/WikiSpaceLocatorInterface.php` | Finds the space a route slug names; replaceable for tenant slugs | FR-SEC-010 |
| `Service/WikiSpaceService.php` | Space CRUD | FR-SPACE-001 |

## Security

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Security/AllowAllWikiAccessChecker.php` | Permissive checker (allow_unauthenticated) | FR-SEC-001 |
| `Security/AttributeSanitizer/AnchorIdAttributeSanitizer.php` | Keeps only the `id` anchors the Markdown renderer writes (no DOM clobbering) | FR-SEC-007 |
| `Security/AttributeSanitizer/EmbedIframeSrcAttributeSanitizer.php` | iframe src allowlist | FR-SEC-007 |
| `Security/AttributeSanitizer/TextAlignStyleAttributeSanitizer.php` | TextAlign style filter | FR-SEC-007 |
| `Security/ConfigurableWikiAccessChecker.php` | Configurable access checker | FR-SEC-001 |
| `Security/LegacyWikiSpaceAccessChecker.php` | Default space checker: the 1.x decision plus space membership | FR-SEC-010 |
| `Security/NullWikiTeamMembershipResolver.php` | Team membership resolver | FR-SEC-006 |
| `Security/WikiAccessCheckerInterface.php` | Access checker contract | FR-SEC-001 |
| `Security/WikiHtmlSanitizer.php` | HTML sanitizer impl | FR-SEC-005 |
| `Security/WikiHtmlSanitizerInterface.php` | Sanitizer contract | FR-SEC-005 |
| `Security/WikiPageVisibility.php` | One read rule: View on the page and every ancestor | FR-SEC-009 |
| `Security/WikiSpaceAccessCheckerInterface.php` | Space- and page-aware access decision | FR-SEC-010 |
| `Security/WikiSpaceGuard.php` | Resolves a route's space and enforces the checker (404 / 403) | FR-SEC-010 |
| `Security/WikiTeamMembershipResolverInterface.php` | Team membership contract | FR-SEC-006 |
| `Security/WikiTokenGuard.php` | Stale token guard | FR-WORKER-001 |

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
| `Routing/WikiTenantPaths.php` | `tenant_paths`: copies of space-scoped routes under host paths | FR-ROUTE-002 |
| `Routing/WikiTenantUrlGenerator.php` | Router decorator generating a tenant's copy of a bundle route | FR-ROUTE-002 |

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
| `Interchange/WikiInterchangeException.php` | 1.41: the import / export refusal (an `InvalidArgumentException`, audit SH-L1 rule) | FR-IX-001 |
| `Interchange/WikiFrontMatterParser.php` | Import/export interchange | FR-IX-001 |
| `Interchange/WikiMarkdownConverter.php` | Import/export interchange | FR-IX-001 |
| `Doctrine/WikiEntityManagerProvider.php` | Per-call entity manager (closed-manager reset) | FR-WORKER-001 |

## Support utilities

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Util/WikiSlugger.php` | Support utility | FR-UTIL-001 |
| `ValueObject/Uuid.php` | Support utility | FR-UTIL-001 |

## Frontend TypeScript

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/assets/src/wiki-code-blocks.ts` | Focusable, labelled code blocks that overflow | FR-UI-011 |
| `Resources/assets/src/wiki-comments.ts` | Ties the Milkdown thread component to the UiKit side panel | FR-COMMENT-002 |
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
| `Resources/views/manage/_ai_dialog.html.twig` | AI question dialog (UiKit `_dialog`) | FR-VIEW-005 |
| `Resources/views/manage/_comments_panel.html.twig` | Comments side panel (UiKit `side_panel`) | FR-COMMENT-002 |
| `Resources/views/manage/_comments_panel_body.html.twig` | Panel body: thread mount, no-JavaScript list and form, 1.21 extras | FR-COMMENT-002 / FR-UI-012 |
| `Resources/views/manage/_comments_view.html.twig` | Read-only Milkdown mount carrying the highlights | FR-COMMENT-002 |
| `Resources/views/manage/_copy_markdown.html.twig` | "Copy as Markdown" of a page, for editors | FR-MD-002 |
| `Resources/views/manage/_legacy_layout.html.twig` | Deprecated bridge around a pre-1.10 host layout | FR-VIEW-001 |
| `Resources/views/manage/_page_tabs.html.twig` | Page / Edit / History tab row | FR-VIEW-001 |
| `Resources/views/manage/_page_tree.html.twig` | Page tree level | FR-VIEW-005 |
| `Resources/views/manage/_page_tree_item.html.twig` | Page tree item (override point) | FR-VIEW-005 |
| `Resources/views/manage/_sidebar.html.twig` | Space sidebar card with the page tree | FR-VIEW-005 |
| `Resources/views/manage/ai_ask.html.twig` | Manage page on the UiKit form scaffold | FR-VIEW-005 |
| `Resources/views/manage/index.html.twig` | Manage page on the UiKit list scaffold | FR-VIEW-005 |
| `Resources/views/manage/page_diff.html.twig` | Manage page on the UiKit show scaffold | FR-VIEW-005 |
| `Resources/views/manage/page_edit.html.twig` | Manage page on the UiKit form scaffold | FR-VIEW-005 |
| `Resources/views/manage/page_history.html.twig` | Manage page on the UiKit list scaffold | FR-VIEW-005 |
| `Resources/views/manage/page_view.html.twig` | Manage page on the UiKit show scaffold | FR-VIEW-005 |
| `Resources/views/manage/search.html.twig` | Manage page on the UiKit list scaffold | FR-VIEW-005 |
| `Resources/views/manage/space.html.twig` | Manage page on the UiKit show scaffold | FR-VIEW-005 |
| `Twig/WikiHtmlTwigExtension.php` | Render-time HTML re-sanitizing | FR-SEC-008 |
| `Ui/WikiPageFactory.php` | UiKit view models of the pages (value-free rows) | FR-VIEW-005 |
| `Ui/WikiPages.php` | UiKit page plumbing (layout, per-page templates, legacy bridge) | FR-VIEW-001 |

## Markdown and the Milkdown bridge (1.11 / 1.13)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Command/WikiConvertMarkdownCommand.php` | HTML → Markdown conversion, scope and report | FR-MD-001, FR-MD-003 |
| `DependencyInjection/Compiler/MilkdownBridgePass.php` | Milkdown options handed to the bridge (fail closed) | FR-MD-002 |
| `Interchange/Conversion/WikiConversionFidelity.php` | Fidelity of one conversion | FR-MD-003 |
| `Interchange/Conversion/WikiConversionFidelityAnalyzer.php` | Lost / approximated constructs, word comparison | FR-MD-003 |
| `Interchange/HtmlToMarkdown/WikiAlignedHeaderConverter.php` | Dialect alignment of headings | FR-MD-002 |
| `Interchange/HtmlToMarkdown/WikiAlignedParagraphConverter.php` | Dialect alignment of paragraphs | FR-MD-002 |
| `Interchange/HtmlToMarkdown/WikiAlignmentTrait.php` | Dialect alignment container | FR-MD-002 |
| `Interchange/HtmlToMarkdown/WikiDialectTextConverter.php` | Page-link / mention tokens kept | FR-MD-002 |
| `Interchange/HtmlToMarkdown/WikiEmbedConverter.php` | Embeds as links | FR-MD-002 |
| `Interchange/HtmlToMarkdown/WikiMarkConverter.php` | Highlight as `:mark[…]` | FR-MD-002 |
| `Interchange/HtmlToMarkdown/WikiStrikethroughConverter.php` | GFM strikethrough | FR-MD-001 |
| `Interchange/HtmlToMarkdown/WikiTaskItemConverter.php` | GFM task lists | FR-MD-001 |
| `Interchange/WikiMarkdownPortableLinks.php` | Page links of exports as file links | FR-MD-002 |
| `Markdown/Milkdown/MilkdownWikiMarkdownRenderer.php` | Bridge renderer | FR-MD-002 |
| `Markdown/Milkdown/WikiFootnoteIds.php` | Footnote ids the wiki sanitizer keeps | FR-MD-002 |
| `Markdown/Milkdown/WikiFootnoteStartParser.php` | Footnote definitions with non-ASCII labels | FR-MD-002 |
| `Markdown/Milkdown/WikiHeadingIds.php` | Heading ids with the editor's slugs | FR-MD-002 |
| `Markdown/Milkdown/WikiLeafDirectiveRenderer.php` | Leaf directives: host renderer, else readable text | FR-MD-002 |
| `Markdown/Milkdown/WikiMilkdownIconHydrator.php` | Icons after sanitizing | FR-MD-002 |
| `Markdown/Milkdown/WikiMilkdownPageResolver.php` | Page links per viewer | FR-MD-002 |
| `Markdown/Milkdown/WikiMilkdownRichContent.php` | Read-side assets | FR-MD-002 |
| `Markdown/Milkdown/WikiTablerIconRenderer.php` | Icon placeholders | FR-MD-002 |
| `Markdown/WikiMarkdownExtensionInterface.php` | Renderer extension point | FR-MD-001 |
| `Markdown/WikiMarkdownLimitsInterface.php` | Renderer limits | FR-MD-003 |
| `Markdown/WikiMarkdownReferenceExtractor.php` | Page references and mentions | FR-MD-001, FR-MD-002 |
| `Markdown/WikiMarkdownReferenceParserInterface.php` | References from the renderer's parser | FR-MD-002 |
| `Markdown/WikiMarkdownRenderer.php` | Default renderer | FR-MD-001 |
| `Markdown/WikiMarkdownRendererInterface.php` | Renderer contract | FR-MD-001 |
| `Markdown/WikiRevisionBodyRenderer.php` | Page body per viewer | FR-MD-002 |
| `Markdown/WikiTaskListNormalizer.php` | Task-list markup | FR-MD-001 |
| `Markdown/WikiViewerMarkdownRendererInterface.php` | Viewer render contract | FR-MD-002 |
| `Repository/WikiPageBatchFinderInterface.php` | Pages by ids in one query | FR-MD-002 |

## Co-editing (1.13)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Collaboration/DoctrineWikiCollabStateStore.php` | Doctrine Yjs log | FR-MD-004 |
| `Collaboration/MercureWikiCollabPublisher.php` | Private Mercure updates | FR-MD-004 |
| `Collaboration/MercureWikiCollabSubscriberAuthorizer.php` | Subscriber cookie for one topic | FR-MD-004 |
| `Collaboration/WikiCollabCountingStoreInterface.php` | Store that checks caps and appends in one locked write | FR-MD-004 |
| `Collaboration/WikiCollabException.php` | Refused request with HTTP status | FR-MD-004 |
| `Collaboration/WikiCollabPagePurgeInterface.php` | Purge reporting page ids (1.13.1, CW-I10) | FR-MD-004 |
| `Collaboration/WikiCollabPublisherInterface.php` | Publisher contract | FR-MD-004 |
| `Collaboration/WikiCollabSaveListener.php` | Collaborative save vs replacement | FR-MD-004 |
| `Collaboration/WikiCollabService.php` | Protocol, validation, seeding, reset | FR-MD-004 |
| `Collaboration/WikiCollabState.php` | Stored log | FR-MD-004 |
| `Collaboration/WikiCollabStateStoreInterface.php` | Store contract | FR-MD-004 |
| `Collaboration/WikiCollabSubscriberAuthorizerInterface.php` | Subscriber contract | FR-MD-004 |
| `Collaboration/WikiCollabTopicsAuthorizerInterface.php` | Subscriber authorizer granting several topics in one cookie | FR-MD-004 / FR-COMMENT-001 |
| `Controller/WikiCollabController.php` | Collaboration endpoints | FR-MD-004 |
| `Entity/WikiCollabDocument.php` | Log document per page | FR-MD-004 |
| `Entity/WikiCollabUpdate.php` | Log entry | FR-MD-004 |

## Page tree (1.14)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Controller/WikiTreeController.php` | Tree endpoints (move, state; 1.39 rename, icon; 1.41 archive, unarchive, delete, duplicate, parents) | FR-TREE-001, FR-TREE-002 |
| `Controller/WikiTreeMoveTrait.php` | Who may move | FR-TREE-001 |
| `Event/WikiPageMovedEvent.php` | Audit event of a move | FR-TREE-001 |
| `Form/WikiPageMoveType.php` | Move form | FR-TREE-001 |
| `Repository/WikiPageTreeWriterInterface.php` | Space-locked transaction | FR-TREE-001 |
| `Service/WikiPageMover.php` | Move validation and positions | FR-TREE-001 |
| `Service/WikiPageMoveResult.php` | Move outcome | FR-TREE-001 |
| `Service/WikiPageMoveScope.php` | Visible siblings and checked parent of a move | FR-TREE-001 |
| `Service/WikiPageMoveException.php` | Refused move | FR-TREE-001 |
| `Tree/WikiTreeStateStoreInterface.php` | Fold state contract | FR-TREE-001 |
| `Tree/SessionWikiTreeStateStore.php` | Session fold state | FR-TREE-001 |
| `Resources/views/manage/page_move.html.twig` | Move form page | FR-TREE-001 |

## Trash (1.14)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Command/WikiTrashPurgeCommand.php` | Retention command | FR-TRASH-001 |
| `Controller/WikiTrashController.php` | Delete, trash, restore, purge | FR-TRASH-001 |
| `Entity/WikiTrashEntry.php` | Trashed page of a deletion | FR-TRASH-001 |
| `Event/WikiTrashEvent.php` | Trash events | FR-TRASH-001 |
| `Repository/WikiTrashRepositoryInterface.php` | Trash store contract | FR-TRASH-001 |
| `Repository/DoctrineOrmWikiTrashRepository.php` | Doctrine trash store, purge | FR-TRASH-001 |
| `Trash/WikiTrashService.php` | Delete, restore, purge, retention | FR-TRASH-001 |
| `Resources/views/manage/trash.html.twig` | Trash page | FR-TRASH-001 |
| `Resources/views/manage/trash_purge.html.twig` | Purge confirmation page | FR-TRASH-001 |

## History timeline (1.15)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Command/WikiHistoryThinCommand.php` | Retention command | FR-HIST-001 |
| `Controller/WikiHistoryController.php` | Save version, copy to new page | FR-HIST-001 |
| `Entity/WikiRevisionLabel.php` | Named version | FR-HIST-001 |
| `Form/WikiVersionLabelType.php` | Save version form | FR-HIST-001 |
| `History/WikiAuthorLabel.php` | Author labels | FR-HIST-001 |
| `History/WikiHistoryTimeline.php` | Sessions, labels, authors per revision | FR-HIST-001 |
| `History/WikiHistoryThinner.php` | Retention (D-W6) | FR-HIST-001 |
| `Repository/WikiRevisionLabelRepositoryInterface.php` | Named-version store contract | FR-HIST-001 |
| `Repository/DoctrineOrmWikiRevisionLabelRepository.php` | Doctrine named-version store | FR-HIST-001 |
| `Resources/views/manage/page_version.html.twig` | Save version page | FR-HIST-001 |

## Attachments (1.9)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Attachment/ConfiguredWikiAttachmentLimitsProvider.php` | Default limits: the `attachments` configuration | FR-ATT-001 |
| `Attachment/LocalWikiAttachmentStorage.php` | Default storage: a private local directory | FR-ATT-001 |
| `Attachment/NullWikiAttachmentScanner.php` | Default scanner: accepts every file | FR-ATT-001 |
| `Attachment/WikiAttachmentLimits.php` | Upload limits of a space | FR-ATT-001 |
| `Attachment/WikiAttachmentLimitsProviderInterface.php` | Contract: limits per space, from host settings | FR-ATT-001 |
| `Attachment/WikiAttachmentReferenceFilter.php` | Drops references to another space's attachments from a page body | FR-ATT-002 |
| `Attachment/WikiAttachmentRejectedException.php` | Upload refused by limits, type allowlist or scanner | FR-ATT-001 |
| `Attachment/WikiAttachmentScannerInterface.php` | Contract: scans a file before it is stored | FR-ATT-001 |
| `Attachment/WikiAttachmentService.php` | Stores, deletes and cleans up attachments | FR-ATT-001 |
| `Attachment/WikiAttachmentStorageInterface.php` | Contract: private storage under server-generated keys | FR-ATT-001 |
| `Entity/WikiAttachment.php` | A file uploaded to a page | FR-ATT-001 |
| `Event/WikiAttachmentEvent.php` | After an attachment was uploaded or deleted | FR-ATT-001 / FR-EVT-002 |
| `Repository/DoctrineOrmWikiAttachmentRepository.php` | Doctrine storage of attachments | FR-ATT-001 |
| `Repository/WikiAttachmentRepositoryInterface.php` | Persistence contract of attachments | FR-ATT-001 |

## Links to host objects (1.9)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Event/WikiPageLinkEvent.php` | After a page was linked to or unlinked from a host object | FR-LINK-001 / FR-EVT-002 |
| `Link/WikiLinkedObject.php` | A host object as described for a viewer allowed to open it | FR-LINK-001 |
| `Link/WikiObjectLinkResolverInterface.php` | Contract: describes and authorizes host objects by type | FR-LINK-001 |
| `Link/WikiPageLinkService.php` | Links pages to host objects, lists "Used in" for a viewer | FR-LINK-001 |

## Avatars (1.16)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Avatar/NullWikiUserAvatarResolver.php` | Default resolver: no pictures, initials only | FR-AVATAR-001 |
| `Avatar/WikiAvatarKey.php` | The key of a user in the resolver's answer | FR-AVATAR-001 |
| `Avatar/WikiAvatars.php` | Batches and memoises avatar URLs of one response (`ResetInterface`) | FR-AVATAR-001 / FR-WORKER-001 |
| `Avatar/WikiUserAvatarResolverInterface.php` | Contract: host picture URLs, asked only for real names shown | FR-AVATAR-001 |

## Comments (1.16)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Comment/WikiCommentException.php` | A refused comment request: HTTP status and machine-readable reason | FR-COMMENT-001 |
| `Comment/WikiCommentMentionErasure.php` | Result of erasing a person's mentions | FR-COMMENT-003 |
| `Comment/WikiCommentService.php` | Thread component endpoints as PHP; the server decides the rights | FR-COMMENT-001 |
| `Comment/WikiCommentsPanel.php` | Who gets the panel, its state, count, URLs, Milkdown options, 1.21 extras | FR-COMMENT-002 / FR-UI-012 |
| `Entity/WikiComment.php` | One comment of a thread | FR-COMMENT-001 |
| `Entity/WikiCommentReaction.php` | One user's emoji reaction | FR-COMMENT-001 |
| `Entity/WikiCommentThread.php` | Inline or page-level thread of a page | FR-COMMENT-001 |
| `Event/WikiCommentAuthorErasedEvent.php` | A user's comments were erased or unlinked | FR-COMMENT-003 / FR-EVT-002 |
| `Event/WikiCommentEvent.php` | A comment change, after it is committed | FR-COMMENT-001 / FR-EVT-002 |
| `Event/WikiCommentMentionsErasedEvent.php` | Mentions of an erased person were rewritten | FR-COMMENT-003 / FR-EVT-002 |
| `Form/WikiCommentType.php` | No-JavaScript page-level comment form | FR-COMMENT-002 / FR-FORM-001 |
| `Panel/SessionWikiPanelStateStore.php` | Default panel state: the session | FR-PANEL-001 |
| `Panel/WikiPanelStateStoreInterface.php` | Contract: open / closed state of side panels per user | FR-PANEL-001 |
| `Repository/DoctrineOrmWikiCommentRepository.php` | Doctrine storage of threads, comments and reactions | FR-COMMENT-001 |
| `Repository/WikiCommentAuthorRepositoryInterface.php` | Per-author queries: thread cap and privacy export | FR-COMMENT-001 / FR-COMMENT-003 |
| `Repository/WikiCommentMentionBatch.php` | One page of the mention walk | FR-COMMENT-003 |
| `Repository/WikiCommentMentionRepositoryInterface.php` | Text lookups for the erasure of mentions | FR-COMMENT-003 |
| `Repository/WikiCommentRepositoryInterface.php` | Storage contract, every lookup scoped to a page | FR-COMMENT-001 |

## Page files (1.20)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Event/WikiPageFilesPurgedEvent.php` | Files of purged pages' revisions, as untrusted URLs | FR-FILES-001 / FR-EVT-002 |
| `PageFile/WikiPageFile.php` | A file a page refers to (cleaned name, safe URL) | FR-FILES-001 |
| `PageFile/WikiPageFileExtractor.php` | Lists a page's file cards and images from its Markdown | FR-FILES-001 |

## Framework-free ports and Doctrine isolation (1.27 / 1.36)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Ai/WikiAiAnswererInterface.php` | Port typed with ids and snapshots (no Symfony or Doctrine types), the twin of an older port that stays deprecated | FR-USER-001 |
| `Ai/WikiAiReply.php` | Port typed with ids and snapshots (no Symfony or Doctrine types), the twin of an older port that stays deprecated | FR-USER-001 |
| `Attachment/WikiAttachmentLimitsPolicyInterface.php` | Port typed with ids and snapshots (no Symfony or Doctrine types), the twin of an older port that stays deprecated | FR-USER-001 |
| `Avatar/WikiUserAvatarSourceInterface.php` | Port typed with ids and snapshots (no Symfony or Doctrine types), the twin of an older port that stays deprecated | FR-USER-001 |
| `DependencyInjection/Compiler/PrivateEntityManagerPass.php` | Private entity manager (`database.private_entity_manager`, opt-in; REQ-ARCH-001 rule 6) | FR-ARCH-002 |
| `DependencyInjection/Compiler/WikiMappingManagerPass.php` | XML ORM mapping (`database.mapping_type: xml`, opt-in) and the manager the mapping is registered on | FR-ARCH-003 |
| `DependencyInjection/Compiler/WikiPortsPass.php` | Framework-free host port: gate adapting the deprecated Symfony-typed port to its twin, or request/snapshot plumbing (REQ-ARCH-001 rules 1 and 3) | FR-ARCH-001 |
| `DependencyInjection/WikiDoctrineMapping.php` | XML ORM mapping (`database.mapping_type: xml`, opt-in) and the manager the mapping is registered on | FR-ARCH-003 |
| `Doctrine/PrivateEntityManagerConnection.php` | Private entity manager (`database.private_entity_manager`, opt-in; REQ-ARCH-001 rule 6) | FR-ARCH-002 |
| `Doctrine/PrivateEntityManagerDriver.php` | Private entity manager (`database.private_entity_manager`, opt-in; REQ-ARCH-001 rule 6) | FR-ARCH-002 |
| `Doctrine/PrivateEntityManagerMiddleware.php` | Private entity manager (`database.private_entity_manager`, opt-in; REQ-ARCH-001 rule 6) | FR-ARCH-002 |
| `Doctrine/PrivateManagerRepositoryFactory.php` | Private entity manager (`database.private_entity_manager`, opt-in; REQ-ARCH-001 rule 6) | FR-ARCH-002 |
| `Doctrine/Type/WikiUserRefType.php` | Exact, binary matching of stored user and owner ids (look-alike ids never match) | FR-SEC-011 |
| `Doctrine/WikiExcludedEntityNamespaceDriver.php` | XML ORM mapping (`database.mapping_type: xml`, opt-in) and the manager the mapping is registered on | FR-ARCH-003 |
| `Doctrine/WikiSpaceCollationListener.php` | Exact, binary matching of stored user and owner ids (look-alike ids never match) | FR-SEC-011 |
| `Link/WikiObjectLinkSourceInterface.php` | Port typed with ids and snapshots (no Symfony or Doctrine types), the twin of an older port that stays deprecated | FR-USER-001 |
| `Link/WikiPageLinkOperations.php` | Port typed with ids and snapshots (no Symfony or Doctrine types), the twin of an older port that stays deprecated | FR-USER-001 |
| `Link/WikiPageLinkOperationsInterface.php` | Port typed with ids and snapshots (no Symfony or Doctrine types), the twin of an older port that stays deprecated | FR-USER-001 |
| `Link/WikiPageLinkServiceInterface.php` | Port typed with ids and snapshots (no Symfony or Doctrine types), the twin of an older port that stays deprecated | FR-USER-001 |
| `Panel/WikiPanelStatesInterface.php` | Port typed with ids and snapshots (no Symfony or Doctrine types), the twin of an older port that stays deprecated | FR-USER-001 |
| `Port/WikiAccessPolicyGate.php` | Framework-free host port: gate adapting the deprecated Symfony-typed port to its twin, or request/snapshot plumbing (REQ-ARCH-001 rules 1 and 3) | FR-ARCH-001 |
| `Port/WikiAiAnswererGate.php` | Framework-free host port: gate adapting the deprecated Symfony-typed port to its twin, or request/snapshot plumbing (REQ-ARCH-001 rules 1 and 3) | FR-ARCH-001 |
| `Port/WikiApiFileStoreGate.php` | Framework-free host port: gate adapting the deprecated Symfony-typed port to its twin, or request/snapshot plumbing (REQ-ARCH-001 rules 1 and 3) | FR-ARCH-001 |
| `Port/WikiApiPlanPolicyGate.php` | Framework-free host port: gate adapting the deprecated Symfony-typed port to its twin, or request/snapshot plumbing (REQ-ARCH-001 rules 1 and 3) | FR-ARCH-001 |
| `Port/WikiApiRequestAuthenticatorGate.php` | Framework-free host port: gate adapting the deprecated Symfony-typed port to its twin, or request/snapshot plumbing (REQ-ARCH-001 rules 1 and 3) | FR-ARCH-001 |
| `Port/WikiApiWikiDirectoryGate.php` | Framework-free host port: gate adapting the deprecated Symfony-typed port to its twin, or request/snapshot plumbing (REQ-ARCH-001 rules 1 and 3) | FR-ARCH-001 |
| `Port/WikiAttachmentLimitsPolicyGate.php` | Framework-free host port: gate adapting the deprecated Symfony-typed port to its twin, or request/snapshot plumbing (REQ-ARCH-001 rules 1 and 3) | FR-ARCH-001 |
| `Port/WikiCollabSubscriptionGate.php` | Framework-free host port: gate adapting the deprecated Symfony-typed port to its twin, or request/snapshot plumbing (REQ-ARCH-001 rules 1 and 3) | FR-ARCH-001 |
| `Port/WikiObjectLinkSourceGate.php` | Framework-free host port: gate adapting the deprecated Symfony-typed port to its twin, or request/snapshot plumbing (REQ-ARCH-001 rules 1 and 3) | FR-ARCH-001 |
| `Port/WikiPanelStatesGate.php` | Framework-free host port: gate adapting the deprecated Symfony-typed port to its twin, or request/snapshot plumbing (REQ-ARCH-001 rules 1 and 3) | FR-ARCH-001 |
| `Port/WikiPorts.php` | Framework-free host port: gate adapting the deprecated Symfony-typed port to its twin, or request/snapshot plumbing (REQ-ARCH-001 rules 1 and 3) | FR-ARCH-001 |
| `Port/WikiRequestContexts.php` | Framework-free host port: gate adapting the deprecated Symfony-typed port to its twin, or request/snapshot plumbing (REQ-ARCH-001 rules 1 and 3) | FR-ARCH-001 |
| `Port/WikiSnapshots.php` | Framework-free host port: gate adapting the deprecated Symfony-typed port to its twin, or request/snapshot plumbing (REQ-ARCH-001 rules 1 and 3) | FR-ARCH-001 |
| `Port/WikiSpaceAccessPolicyGate.php` | Framework-free host port: gate adapting the deprecated Symfony-typed port to its twin, or request/snapshot plumbing (REQ-ARCH-001 rules 1 and 3) | FR-ARCH-001 |
| `Port/WikiSpaceIdLocatorGate.php` | Framework-free host port: gate adapting the deprecated Symfony-typed port to its twin, or request/snapshot plumbing (REQ-ARCH-001 rules 1 and 3) | FR-ARCH-001 |
| `Port/WikiSpaceReachGate.php` | Framework-free host port: gate adapting the deprecated Symfony-typed port to its twin, or request/snapshot plumbing (REQ-ARCH-001 rules 1 and 3) | FR-ARCH-001 |
| `Port/WikiTeamMembershipSourceGate.php` | Framework-free host port: gate adapting the deprecated Symfony-typed port to its twin, or request/snapshot plumbing (REQ-ARCH-001 rules 1 and 3) | FR-ARCH-001 |
| `Port/WikiTreeStatesGate.php` | Framework-free host port: gate adapting the deprecated Symfony-typed port to its twin, or request/snapshot plumbing (REQ-ARCH-001 rules 1 and 3) | FR-ARCH-001 |
| `Port/WikiUserAvatarSourceGate.php` | Framework-free host port: gate adapting the deprecated Symfony-typed port to its twin, or request/snapshot plumbing (REQ-ARCH-001 rules 1 and 3) | FR-ARCH-001 |
| `Port/WikiUserRefs.php` | Framework-free host port: gate adapting the deprecated Symfony-typed port to its twin, or request/snapshot plumbing (REQ-ARCH-001 rules 1 and 3) | FR-ARCH-001 |
| `Repository/WikiPageChildrenFinderInterface.php` | Port typed with ids and snapshots (no Symfony or Doctrine types), the twin of an older port that stays deprecated | FR-USER-001 |
| `Repository/WikiPageLockInterface.php` | Port typed with ids and snapshots (no Symfony or Doctrine types), the twin of an older port that stays deprecated | FR-USER-001 |
| `Repository/WikiPageMetadata.php` | Port typed with ids and snapshots (no Symfony or Doctrine types), the twin of an older port that stays deprecated | FR-USER-001 |
| `Repository/WikiPageMetadataFinderInterface.php` | Port typed with ids and snapshots (no Symfony or Doctrine types), the twin of an older port that stays deprecated | FR-USER-001 |
| `Repository/WikiPageMetadataQuery.php` | Port typed with ids and snapshots (no Symfony or Doctrine types), the twin of an older port that stays deprecated | FR-USER-001 |
| `Repository/WikiPageTitleFinderInterface.php` | Port typed with ids and snapshots (no Symfony or Doctrine types), the twin of an older port that stays deprecated | FR-USER-001 |
| `Resources/config/doctrine-xml/WikiAttachment.orm.xml` | XML ORM mapping (`database.mapping_type: xml`, opt-in) and the manager the mapping is registered on | FR-ARCH-003 |
| `Resources/config/doctrine-xml/WikiCollabDocument.orm.xml` | XML ORM mapping (`database.mapping_type: xml`, opt-in) and the manager the mapping is registered on | FR-ARCH-003 |
| `Resources/config/doctrine-xml/WikiCollabUpdate.orm.xml` | XML ORM mapping (`database.mapping_type: xml`, opt-in) and the manager the mapping is registered on | FR-ARCH-003 |
| `Resources/config/doctrine-xml/WikiComment.orm.xml` | XML ORM mapping (`database.mapping_type: xml`, opt-in) and the manager the mapping is registered on | FR-ARCH-003 |
| `Resources/config/doctrine-xml/WikiCommentReaction.orm.xml` | XML ORM mapping (`database.mapping_type: xml`, opt-in) and the manager the mapping is registered on | FR-ARCH-003 |
| `Resources/config/doctrine-xml/WikiCommentThread.orm.xml` | XML ORM mapping (`database.mapping_type: xml`, opt-in) and the manager the mapping is registered on | FR-ARCH-003 |
| `Resources/config/doctrine-xml/WikiPage.orm.xml` | XML ORM mapping (`database.mapping_type: xml`, opt-in) and the manager the mapping is registered on | FR-ARCH-003 |
| `Resources/config/doctrine-xml/WikiPageLink.orm.xml` | XML ORM mapping (`database.mapping_type: xml`, opt-in) and the manager the mapping is registered on | FR-ARCH-003 |
| `Resources/config/doctrine-xml/WikiPageRevision.orm.xml` | XML ORM mapping (`database.mapping_type: xml`, opt-in) and the manager the mapping is registered on | FR-ARCH-003 |
| `Resources/config/doctrine-xml/WikiRevisionLabel.orm.xml` | XML ORM mapping (`database.mapping_type: xml`, opt-in) and the manager the mapping is registered on | FR-ARCH-003 |
| `Resources/config/doctrine-xml/WikiSpace.orm.xml` | XML ORM mapping (`database.mapping_type: xml`, opt-in) and the manager the mapping is registered on | FR-ARCH-003 |
| `Resources/config/doctrine-xml/WikiTrashEntry.orm.xml` | XML ORM mapping (`database.mapping_type: xml`, opt-in) and the manager the mapping is registered on | FR-ARCH-003 |
| `Security/WikiAccessPolicyInterface.php` | Port typed with ids and snapshots (no Symfony or Doctrine types), the twin of an older port that stays deprecated | FR-USER-001 |
| `Security/WikiSpaceAccessPolicyInterface.php` | Port typed with ids and snapshots (no Symfony or Doctrine types), the twin of an older port that stays deprecated | FR-USER-001 |
| `Security/WikiTeamMembershipSourceInterface.php` | Port typed with ids and snapshots (no Symfony or Doctrine types), the twin of an older port that stays deprecated | FR-USER-001 |
| `Service/WikiPageCreateResult.php` | Port typed with ids and snapshots (no Symfony or Doctrine types), the twin of an older port that stays deprecated | FR-USER-001 |
| `Service/WikiPageDuplicator.php` | 1.41: Duplicate of the tree menu (the page only by default; placed by the mover) | FR-TREE-002 |
| `Service/WikiInvalidIconException.php` | 1.40.2 (audit SH-L1): a page service refusal (an `InvalidArgumentException`); callers catch it, not the broad class | FR-API-001 |
| `Service/WikiInvalidSlugException.php` | 1.40.2 (audit SH-L1): a page service refusal (an `InvalidArgumentException`); callers catch it, not the broad class | FR-API-001 |
| `Service/WikiPageArchivedException.php` | 1.40.2 (audit SH-L1): a page service refusal (an `InvalidArgumentException`); callers catch it, not the broad class | FR-API-001 |
| `Service/WikiPageNotFoundException.php` | 1.40.2 (audit SH-L1): a page service refusal (an `InvalidArgumentException`); callers catch it, not the broad class | FR-API-001 |
| `Service/WikiRevisionMismatchException.php` | 1.40.2 (audit SH-L1): a page service refusal (an `InvalidArgumentException`); callers catch it, not the broad class | FR-API-001 |
| `Service/WikiSlugTakenException.php` | 1.40.2 (audit SH-L1): a page service refusal (an `InvalidArgumentException`); callers catch it, not the broad class | FR-API-001 |
| `Service/WikiUnknownFormatException.php` | 1.40.2 (audit SH-L1): a page service refusal (an `InvalidArgumentException`); callers catch it, not the broad class | FR-API-001 |
| `Service/WikiPageOperations.php` | Port typed with ids and snapshots (no Symfony or Doctrine types), the twin of an older port that stays deprecated | FR-USER-001 |
| `Service/WikiPageOperationsInterface.php` | Port typed with ids and snapshots (no Symfony or Doctrine types), the twin of an older port that stays deprecated | FR-USER-001 |
| `Service/WikiPageServiceInterface.php` | Port typed with ids and snapshots (no Symfony or Doctrine types), the twin of an older port that stays deprecated | FR-USER-001 |
| `Service/WikiSpaceIdLocatorInterface.php` | Port typed with ids and snapshots (no Symfony or Doctrine types), the twin of an older port that stays deprecated | FR-USER-001 |
| `Service/WikiSpaceOperations.php` | Port typed with ids and snapshots (no Symfony or Doctrine types), the twin of an older port that stays deprecated | FR-USER-001 |
| `Service/WikiSpaceOperationsInterface.php` | Port typed with ids and snapshots (no Symfony or Doctrine types), the twin of an older port that stays deprecated | FR-USER-001 |
| `Service/WikiSpaceReachInterface.php` | Port typed with ids and snapshots (no Symfony or Doctrine types), the twin of an older port that stays deprecated | FR-USER-001 |
| `Service/WikiSpaceServiceInterface.php` | Port typed with ids and snapshots (no Symfony or Doctrine types), the twin of an older port that stays deprecated | FR-USER-001 |
| `Tree/WikiTreeStatesInterface.php` | Port typed with ids and snapshots (no Symfony or Doctrine types), the twin of an older port that stays deprecated | FR-USER-001 |
| `User/DoctrineWikiUserReferenceResolver.php` | Port typed with ids and snapshots (no Symfony or Doctrine types), the twin of an older port that stays deprecated | FR-USER-001 |
| `User/WikiRefUser.php` | Framework-free value object or user reference of the host path (ids, snapshots, request context) | FR-ARCH-001 |
| `User/WikiUserClassPolicy.php` | Framework-free value object or user reference of the host path (ids, snapshots, request context) | FR-ARCH-001 |
| `User/WikiUserDirectoryInterface.php` | Port typed with ids and snapshots (no Symfony or Doctrine types), the twin of an older port that stays deprecated | FR-USER-001 |
| `User/WikiUserFields.php` | Framework-free value object or user reference of the host path (ids, snapshots, request context) | FR-ARCH-001 |
| `User/WikiUserId.php` | Framework-free value object or user reference of the host path (ids, snapshots, request context) | FR-ARCH-001 |
| `User/WikiUserReferenceResolverInterface.php` | Port typed with ids and snapshots (no Symfony or Doctrine types), the twin of an older port that stays deprecated | FR-USER-001 |
| `User/WikiUsers.php` | Framework-free value object or user reference of the host path (ids, snapshots, request context) | FR-ARCH-001 |
| `ValueObject/WikiOwnerScopeId.php` | Framework-free value object or user reference of the host path (ids, snapshots, request context) | FR-ARCH-001 |
| `ValueObject/WikiPagePlacement.php` | Framework-free value object or user reference of the host path (ids, snapshots, request context) | FR-ARCH-001 |
| `ValueObject/WikiInvalidPlacementException.php` | 1.41: a page place that is not one (an `InvalidArgumentException`, audit SH-L1 rule) | FR-TREE-002 |
| `ValueObject/WikiPageSnapshot.php` | Framework-free value object or user reference of the host path (ids, snapshots, request context) | FR-ARCH-001 |
| `ValueObject/WikiRequestContext.php` | Framework-free value object or user reference of the host path (ids, snapshots, request context) | FR-ARCH-001 |
| `ValueObject/WikiRevisionSnapshot.php` | Framework-free value object or user reference of the host path (ids, snapshots, request context) | FR-ARCH-001 |
| `ValueObject/WikiSpaceIcon.php` | Framework-free value object or user reference of the host path (ids, snapshots, request context) | FR-ARCH-001 |
| `ValueObject/WikiSpaceSnapshot.php` | Framework-free value object or user reference of the host path (ids, snapshots, request context) | FR-ARCH-001 |
| `ValueObject/WikiStoredUserRef.php` | Framework-free value object or user reference of the host path (ids, snapshots, request context) | FR-ARCH-001 |
| `ValueObject/WikiUserRef.php` | Framework-free value object or user reference of the host path (ids, snapshots, request context) | FR-ARCH-001 |

## REST API (1.24+)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Api/Command/WikiApiPurgeIdempotencyCommand.php` | Idempotency of API writes (`Idempotency-Key`: claim, replay, outcome, purge) | FR-API-004 |
| `Api/Controller/WikiApiCommentController.php` | REST API writes, comments and delegated calls (rights re-checked per call) | FR-API-002 |
| `Api/Controller/WikiApiDelegatedController.php` | REST API writes, comments and delegated calls (rights re-checked per call) | FR-API-002 |
| `Api/Controller/WikiApiReadController.php` | REST API reads: pages, revisions, search, wikis; serialization with the body sections of 1.40 | FR-API-001 |
| `Api/Controller/WikiApiWikiController.php` | REST API reads: pages, revisions, search, wikis; serialization with the body sections of 1.40 | FR-API-001 |
| `Api/Controller/WikiApiWriteController.php` | REST API writes, comments and delegated calls (rights re-checked per call) | FR-API-002 |
| `Api/Hook/DenyingWikiApiFiles.php` | Host hook of the REST API: a port the host implements (denying or null default when the feature is not wired) | FR-API-003 |
| `Api/Hook/DenyingWikiApiPlanGate.php` | Host hook of the REST API: a port the host implements (denying or null default when the feature is not wired) | FR-API-003 |
| `Api/Hook/DenyingWikiApiRateLimiter.php` | Host hook of the REST API: a port the host implements (denying or null default when the feature is not wired) | FR-API-003 |
| `Api/Hook/DenyingWikiApiWikiManagement.php` | Host hook of the REST API: a port the host implements (denying or null default when the feature is not wired) | FR-API-003 |
| `Api/Hook/NullWikiApiUsers.php` | Host hook of the REST API: a port the host implements (denying or null default when the feature is not wired) | FR-API-003 |
| `Api/Hook/WikiApiAuthenticatorInterface.php` | Host hook of the REST API: a port the host implements (denying or null default when the feature is not wired) | FR-API-003 |
| `Api/Hook/WikiApiDirectoryInterface.php` | Host hook of the REST API: a port the host implements (denying or null default when the feature is not wired) | FR-API-003 |
| `Api/Hook/WikiApiFileStoreInterface.php` | Host hook of the REST API: a port the host implements (denying or null default when the feature is not wired) | FR-API-003 |
| `Api/Hook/WikiApiFilesInterface.php` | Host hook of the REST API: a port the host implements (denying or null default when the feature is not wired) | FR-API-003 |
| `Api/Hook/WikiApiIdempotencyStoreInterface.php` | Idempotency of API writes (`Idempotency-Key`: claim, replay, outcome, purge) | FR-API-004 |
| `Api/Hook/WikiApiPlanGateInterface.php` | Host hook of the REST API: a port the host implements (denying or null default when the feature is not wired) | FR-API-003 |
| `Api/Hook/WikiApiPlanPolicyInterface.php` | Host hook of the REST API: a port the host implements (denying or null default when the feature is not wired) | FR-API-003 |
| `Api/Hook/WikiApiRateLimiterInterface.php` | Host hook of the REST API: a port the host implements (denying or null default when the feature is not wired) | FR-API-003 |
| `Api/Hook/WikiApiRequestAuthenticatorInterface.php` | Host hook of the REST API: a port the host implements (denying or null default when the feature is not wired) | FR-API-003 |
| `Api/Hook/WikiApiUsersInterface.php` | Host hook of the REST API: a port the host implements (denying or null default when the feature is not wired) | FR-API-003 |
| `Api/Hook/WikiApiWikiDirectoryInterface.php` | Host hook of the REST API: a port the host implements (denying or null default when the feature is not wired) | FR-API-003 |
| `Api/Hook/WikiApiWikiManagementInterface.php` | Host hook of the REST API: a port the host implements (denying or null default when the feature is not wired) | FR-API-003 |
| `Api/Idempotency/DoctrineWikiApiIdempotencyStore.php` | Idempotency of API writes (`Idempotency-Key`: claim, replay, outcome, purge) | FR-API-004 |
| `Api/Operation/WikiApiAudit.php` | REST API reads: pages, revisions, search, wikis; serialization with the body sections of 1.40 | FR-API-001 |
| `Api/Operation/WikiApiCommentReadOperations.php` | REST API write and comment operation behind the controller | FR-API-002 |
| `Api/Operation/WikiApiCommentWriteOperations.php` | REST API write and comment operation behind the controller | FR-API-002 |
| `Api/Operation/WikiApiComments.php` | REST API write and comment operation behind the controller | FR-API-002 |
| `Api/Operation/WikiApiDelegatedOperations.php` | REST API write and comment operation behind the controller | FR-API-002 |
| `Api/Operation/WikiApiPageReadOperations.php` | REST API reads: pages, revisions, search, wikis; serialization with the body sections of 1.40 | FR-API-001 |
| `Api/Operation/WikiApiPageWriteOperations.php` | REST API write and comment operation behind the controller | FR-API-002 |
| `Api/Operation/WikiApiRevisionOperations.php` | REST API reads: pages, revisions, search, wikis; serialization with the body sections of 1.40 | FR-API-001 |
| `Api/Operation/WikiApiSearchOperations.php` | REST API reads: pages, revisions, search, wikis; serialization with the body sections of 1.40 | FR-API-001 |
| `Api/Operation/WikiApiWikiOperations.php` | REST API reads: pages, revisions, search, wikis; serialization with the body sections of 1.40 | FR-API-001 |
| `Api/Repository/WikiApiUsersReader.php` | REST API reads: pages, revisions, search, wikis; serialization with the body sections of 1.40 | FR-API-001 |
| `Api/Security/WikiApiGuard.php` | API request boundary: authentication, guard, render reach and the compile-time safety check (fails closed) | FR-API-004 |
| `Api/Security/WikiApiIdempotencyListener.php` | Idempotency of API writes (`Idempotency-Key`: claim, replay, outcome, purge) | FR-API-004 |
| `Api/Security/WikiApiRenderReach.php` | API request boundary: authentication, guard, render reach and the compile-time safety check (fails closed) | FR-API-004 |
| `Api/Security/WikiApiRequestListener.php` | API request boundary: authentication, guard, render reach and the compile-time safety check (fails closed) | FR-API-004 |
| `Api/Serializer/WikiApiContent.php` | REST API reads: pages, revisions, search, wikis; serialization with the body sections of 1.40 | FR-API-001 |
| `Api/Serializer/WikiApiPeople.php` | REST API reads: pages, revisions, search, wikis; serialization with the body sections of 1.40 | FR-API-001 |
| `Api/Serializer/WikiApiSerializer.php` | REST API reads: pages, revisions, search, wikis; serialization with the body sections of 1.40 | FR-API-001 |
| `Api/Value/WikiApiDownload.php` | Immutable value of the REST API (wiki, file, upload, filter, patch, rate-limit state) | FR-API-001 |
| `Api/Value/WikiApiFile.php` | Immutable value of the REST API (wiki, file, upload, filter, patch, rate-limit state) | FR-API-001 |
| `Api/Value/WikiApiIdempotencyClaim.php` | Idempotency of API writes (`Idempotency-Key`: claim, replay, outcome, purge) | FR-API-004 |
| `Api/Value/WikiApiIdempotencyOutcome.php` | Idempotency of API writes (`Idempotency-Key`: claim, replay, outcome, purge) | FR-API-004 |
| `Api/Value/WikiApiNewWiki.php` | Immutable value of the REST API (wiki, file, upload, filter, patch, rate-limit state) | FR-API-001 |
| `Api/Value/WikiApiRateLimitState.php` | Immutable value of the REST API (wiki, file, upload, filter, patch, rate-limit state) | FR-API-001 |
| `Api/Value/WikiApiUpload.php` | Immutable value of the REST API (wiki, file, upload, filter, patch, rate-limit state) | FR-API-001 |
| `Api/Value/WikiApiUploadedFile.php` | Immutable value of the REST API (wiki, file, upload, filter, patch, rate-limit state) | FR-API-001 |
| `Api/Value/WikiApiWiki.php` | Immutable value of the REST API (wiki, file, upload, filter, patch, rate-limit state) | FR-API-001 |
| `Api/Value/WikiApiWikiFilter.php` | Immutable value of the REST API (wiki, file, upload, filter, patch, rate-limit state) | FR-API-001 |
| `Api/Value/WikiApiWikiPatch.php` | Immutable value of the REST API (wiki, file, upload, filter, patch, rate-limit state) | FR-API-001 |
| `Api/WikiApiAuthFailure.php` | REST API core (capabilities, principal, credential, query, refusal, routes) and its service wiring (`nowo_wiki.api`, off by default) | FR-API-001 |
| `Api/WikiApiCapability.php` | REST API core (capabilities, principal, credential, query, refusal, routes) and its service wiring (`nowo_wiki.api`, off by default) | FR-API-001 |
| `Api/WikiApiCredential.php` | REST API core (capabilities, principal, credential, query, refusal, routes) and its service wiring (`nowo_wiki.api`, off by default) | FR-API-001 |
| `Api/WikiApiInput.php` | REST API core (capabilities, principal, credential, query, refusal, routes) and its service wiring (`nowo_wiki.api`, off by default) | FR-API-001 |
| `Api/WikiApiLevel.php` | REST API core (capabilities, principal, credential, query, refusal, routes) and its service wiring (`nowo_wiki.api`, off by default) | FR-API-001 |
| `Api/WikiApiPrincipal.php` | REST API core (capabilities, principal, credential, query, refusal, routes) and its service wiring (`nowo_wiki.api`, off by default) | FR-API-001 |
| `Api/WikiApiQuery.php` | REST API core (capabilities, principal, credential, query, refusal, routes) and its service wiring (`nowo_wiki.api`, off by default) | FR-API-001 |
| `Api/WikiApiRefusal.php` | REST API core (capabilities, principal, credential, query, refusal, routes) and its service wiring (`nowo_wiki.api`, off by default) | FR-API-001 |
| `Api/WikiApiRoutes.php` | REST API core (capabilities, principal, credential, query, refusal, routes) and its service wiring (`nowo_wiki.api`, off by default) | FR-API-001 |
| `Api/WikiApiWhere.php` | REST API core (capabilities, principal, credential, query, refusal, routes) and its service wiring (`nowo_wiki.api`, off by default) | FR-API-001 |
| `DependencyInjection/Compiler/WikiApiSafetyPass.php` | API request boundary: authentication, guard, render reach and the compile-time safety check (fails closed) | FR-API-004 |
| `Entity/WikiApiIdempotencyRecord.php` | Idempotency of API writes (`Idempotency-Key`: claim, replay, outcome, purge) | FR-API-004 |
| `Event/WikiApiWriteEvent.php` | Event dispatched after an API write | FR-API-002 |
| `Resources/config/doctrine-xml/WikiApiIdempotencyRecord.orm.xml` | Idempotency of API writes (`Idempotency-Key`: claim, replay, outcome, purge) | FR-API-004 |
| `Resources/config/services_api.yaml` | REST API core (capabilities, principal, credential, query, refusal, routes) and its service wiring (`nowo_wiki.api`, off by default) | FR-API-001 |

## Co-editing epochs and resets (1.22 / 1.23)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Collaboration/WikiCollabConditionalResetStoreInterface.php` | Co-editing log epochs, numbered and conditional reset stores, locking, subscription policy and the Mercure cookie (1.22 / 1.23) | FR-COLLAB-002 |
| `Collaboration/WikiCollabCookie.php` | Co-editing log epochs, numbered and conditional reset stores, locking, subscription policy and the Mercure cookie (1.22 / 1.23) | FR-COLLAB-002 |
| `Collaboration/WikiCollabCookieListener.php` | Co-editing log epochs, numbered and conditional reset stores, locking, subscription policy and the Mercure cookie (1.22 / 1.23) | FR-COLLAB-002 |
| `Collaboration/WikiCollabEpochStoreInterface.php` | Co-editing log epochs, numbered and conditional reset stores, locking, subscription policy and the Mercure cookie (1.22 / 1.23) | FR-COLLAB-002 |
| `Collaboration/WikiCollabLockingStoreInterface.php` | Co-editing log epochs, numbered and conditional reset stores, locking, subscription policy and the Mercure cookie (1.22 / 1.23) | FR-COLLAB-002 |
| `Collaboration/WikiCollabNumberedStoreInterface.php` | Co-editing log epochs, numbered and conditional reset stores, locking, subscription policy and the Mercure cookie (1.22 / 1.23) | FR-COLLAB-002 |
| `Collaboration/WikiCollabSchema.php` | Co-editing log epochs, numbered and conditional reset stores, locking, subscription policy and the Mercure cookie (1.22 / 1.23) | FR-COLLAB-002 |
| `Collaboration/WikiCollabSubscriptionPolicyInterface.php` | Co-editing log epochs, numbered and conditional reset stores, locking, subscription policy and the Mercure cookie (1.22 / 1.23) | FR-COLLAB-002 |
| `Collaboration/WikiCollabUnitLostException.php` | Co-editing log epochs, numbered and conditional reset stores, locking, subscription policy and the Mercure cookie (1.22 / 1.23) | FR-COLLAB-002 |

## Link picker and section links (1.40)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Controller/WikiLinkPickerController.php` | Milkdown link picker (`editor.milkdown.link_picker`, opt-in): same-tenant page and section search | FR-LINKPICK-001 |
| `LinkPicker/ConfiguredWikiLinkPickerSettings.php` | Milkdown link picker (`editor.milkdown.link_picker`, opt-in): same-tenant page and section search | FR-LINKPICK-001 |
| `LinkPicker/WikiLinkPickerEditorOptions.php` | Milkdown link picker (`editor.milkdown.link_picker`, opt-in): same-tenant page and section search | FR-LINKPICK-001 |
| `LinkPicker/WikiLinkPickerService.php` | Milkdown link picker (`editor.milkdown.link_picker`, opt-in): same-tenant page and section search | FR-LINKPICK-001 |
| `LinkPicker/WikiLinkPickerSettingsInterface.php` | Milkdown link picker (`editor.milkdown.link_picker`, opt-in): same-tenant page and section search | FR-LINKPICK-001 |
| `LinkPicker/WikiLinkTenantResolverInterface.php` | Milkdown link picker (`editor.milkdown.link_picker`, opt-in): same-tenant page and section search | FR-LINKPICK-001 |
| `LinkPicker/WikiTenantPathsLinkTenantResolver.php` | Milkdown link picker (`editor.milkdown.link_picker`, opt-in): same-tenant page and section search | FR-LINKPICK-001 |
| `Markdown/Milkdown/WikiMilkdownSectionResolver.php` | Stable and explicit heading ids, heading outline and "Link to this section" | FR-SECTION-001 |
| `Markdown/WikiHeadingAnchor.php` | Stable and explicit heading ids, heading outline and "Link to this section" | FR-SECTION-001 |
| `Markdown/WikiHeadingOutline.php` | Stable and explicit heading ids, heading outline and "Link to this section" | FR-SECTION-001 |
| `Resources/assets/src/wiki-section-links.ts` | Stable and explicit heading ids, heading outline and "Link to this section" | FR-SECTION-001 |

## Worker safety and transactions (1.29.1 / 1.36)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Doctrine/WikiEntityManager.php` | Per-request state and transactions that stay safe in FrankenPHP worker mode with kernel reset off: entity manager access, flush, savepoints, event deferral, boundary reset | FR-WORKER-002 |
| `Doctrine/WikiFlusher.php` | Per-request state and transactions that stay safe in FrankenPHP worker mode with kernel reset off: entity manager access, flush, savepoints, event deferral, boundary reset | FR-WORKER-002 |
| `Doctrine/WikiSavepoint.php` | Per-request state and transactions that stay safe in FrankenPHP worker mode with kernel reset off: entity manager access, flush, savepoints, event deferral, boundary reset | FR-WORKER-002 |
| `Service/WikiEventDeferral.php` | Per-request state and transactions that stay safe in FrankenPHP worker mode with kernel reset off: entity manager access, flush, savepoints, event deferral, boundary reset | FR-WORKER-002 |
| `Service/WikiRequestBoundaryResetter.php` | Per-request state and transactions that stay safe in FrankenPHP worker mode with kernel reset off: entity manager access, flush, savepoints, event deferral, boundary reset | FR-WORKER-002 |
| `Transaction/WikiFlushObserver.php` | Per-request state and transactions that stay safe in FrankenPHP worker mode with kernel reset off: entity manager access, flush, savepoints, event deferral, boundary reset | FR-WORKER-002 |
| `Transaction/WikiStorageFailureSubscriber.php` | Per-request state and transactions that stay safe in FrankenPHP worker mode with kernel reset off: entity manager access, flush, savepoints, event deferral, boundary reset | FR-WORKER-002 |
| `Transaction/WikiTransactionRunner.php` | Per-request state and transactions that stay safe in FrankenPHP worker mode with kernel reset off: entity manager access, flush, savepoints, event deferral, boundary reset | FR-WORKER-002 |

## Icons (1.32 / 1.38 / 1.39)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Form/WikiIconPickerType.php` | Page and space icons: UX name, emoji and the icon picker field | FR-ICON-001 |
| `Form/WikiSpaceIconType.php` | Page and space icons: UX name, emoji and the icon picker field | FR-ICON-001 |
| `Form/WikiSpaceType.php` | Space form, including the optional icon | FR-ICON-001 |
| `Resources/views/manage/_icon_picker_script.html.twig` | Page and space icons: UX name, emoji and the icon picker field | FR-ICON-001 |
| `Resources/views/manage/_page_icon.html.twig` | Page and space icons: UX name, emoji and the icon picker field | FR-ICON-001 |
| `Resources/views/manage/_tree_page_icon.html.twig` | Page and space icons: UX name, emoji and the icon picker field | FR-ICON-001 |
| `Service/WikiPageIconService.php` | Page and space icons: UX name, emoji and the icon picker field | FR-ICON-001 |

## Member badges and write provenance (1.34)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `History/WikiActorLabels.php` | Write provenance of a revision (which client wrote it), its erasure with the author and the labels shown | FR-PROV-001 |
| `Member/NullWikiMemberBadgeProvider.php` | Member badges next to author names, through a host provider (null by default) | FR-MEMBER-001 |
| `Member/WikiMemberBadge.php` | Member badges next to author names, through a host provider (null by default) | FR-MEMBER-001 |
| `Member/WikiMemberBadgeProviderInterface.php` | Member badges next to author names, through a host provider (null by default) | FR-MEMBER-001 |
| `Member/WikiMemberBadges.php` | Member badges next to author names, through a host provider (null by default) | FR-MEMBER-001 |
| `Member/WikiMemberRef.php` | Member badges next to author names, through a host provider (null by default) | FR-MEMBER-001 |
| `Provenance/NullWikiWriteProvenanceProvider.php` | Write provenance of a revision (which client wrote it), its erasure with the author and the labels shown | FR-PROV-001 |
| `Provenance/WikiProvenanceAuthorErasedListener.php` | Write provenance of a revision (which client wrote it), its erasure with the author and the labels shown | FR-PROV-001 |
| `Provenance/WikiWriteProvenance.php` | Write provenance of a revision (which client wrote it), its erasure with the author and the labels shown | FR-PROV-001 |
| `Provenance/WikiWriteProvenanceErasure.php` | Write provenance of a revision (which client wrote it), its erasure with the author and the labels shown | FR-PROV-001 |
| `Provenance/WikiWriteProvenanceProviderInterface.php` | Write provenance of a revision (which client wrote it), its erasure with the author and the labels shown | FR-PROV-001 |
| `Repository/WikiPageProvenance.php` | Write provenance of a revision (which client wrote it), its erasure with the author and the labels shown | FR-PROV-001 |
| `Repository/WikiPageProvenanceFinderInterface.php` | Write provenance of a revision (which client wrote it), its erasure with the author and the labels shown | FR-PROV-001 |
| `Resources/views/manage/_member_label_suffix.html.twig` | Member badges next to author names, through a host provider (null by default) | FR-MEMBER-001 |

## Saving and rendering (1.24 / 1.28)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Interchange/HtmlToMarkdown/WikiHeadingIdConverter.php` | Per-viewer render: context, frames and reach, the reader's Markdown, Markdown file reading and HTML to Markdown heading ids | FR-RENDER-001 |
| `Interchange/WikiMarkdownFileReader.php` | Per-viewer render: context, frames and reach, the reader's Markdown, Markdown file reading and HTML to Markdown heading ids | FR-RENDER-001 |
| `Markdown/Milkdown/WikiFilePreviewFrames.php` | File previews and host files inside the per-viewer render (1.24.1 / 1.28) | FR-RENDER-002 |
| `Markdown/Milkdown/WikiLeafHostFiles.php` | File previews and host files inside the per-viewer render (1.24.1 / 1.28) | FR-RENDER-002 |
| `Markdown/Milkdown/WikiMilkdownFilePreviewResolver.php` | File previews and host files inside the per-viewer render (1.24.1 / 1.28) | FR-RENDER-002 |
| `Markdown/Milkdown/WikiMilkdownHostFileResolver.php` | File previews and host files inside the per-viewer render (1.24.1 / 1.28) | FR-RENDER-002 |
| `Markdown/Milkdown/WikiMilkdownRenderContext.php` | Per-viewer render: context, frames and reach, the reader's Markdown, Markdown file reading and HTML to Markdown heading ids | FR-RENDER-001 |
| `Markdown/Milkdown/WikiNeutralTranslator.php` | Per-viewer render: context, frames and reach, the reader's Markdown, Markdown file reading and HTML to Markdown heading ids | FR-RENDER-001 |
| `Markdown/WikiRenderContext.php` | Per-viewer render: context, frames and reach, the reader's Markdown, Markdown file reading and HTML to Markdown heading ids | FR-RENDER-001 |
| `Markdown/WikiRenderFrame.php` | Per-viewer render: context, frames and reach, the reader's Markdown, Markdown file reading and HTML to Markdown heading ids | FR-RENDER-001 |
| `Markdown/WikiRenderReach.php` | Per-viewer render: context, frames and reach, the reader's Markdown, Markdown file reading and HTML to Markdown heading ids | FR-RENDER-001 |
| `Markdown/WikiRenderReachIntersection.php` | Per-viewer render: context, frames and reach, the reader's Markdown, Markdown file reading and HTML to Markdown heading ids | FR-RENDER-001 |
| `Markdown/WikiViewerMarkdownView.php` | Per-viewer render: context, frames and reach, the reader's Markdown, Markdown file reading and HTML to Markdown heading ids | FR-RENDER-001 |
| `Resources/views/manage/_collab_conflict.html.twig` | Save from a known revision: collision and conflict outcomes shown to the editor | FR-SAVE-001 |
| `Resources/views/manage/page_restore_conflict.html.twig` | Save from a known revision: collision and conflict outcomes shown to the editor | FR-SAVE-001 |
| `Service/WikiRevisionCollisionException.php` | Save from a known revision: collision and conflict outcomes shown to the editor | FR-SAVE-001 |
| `Service/WikiRevisionConflictException.php` | Save from a known revision: collision and conflict outcomes shown to the editor | FR-SAVE-001 |

## Tree menu and page navigation (1.35 / 1.39)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Panel/WikiTreeRail.php` | New page dialog, create-at-a-place, the tree context menu and the plain (collapsible) tree | FR-TREE-002 |
| `Resources/assets/src/wiki-new-page.ts` | New page dialog, create-at-a-place, the tree context menu and the plain (collapsible) tree | FR-TREE-002 |
| `Resources/assets/src/wiki-plain-tree.ts` | New page dialog, create-at-a-place, the tree context menu and the plain (collapsible) tree | FR-TREE-002 |
| `Resources/assets/src/wiki-tree-menu.ts` | New page dialog, create-at-a-place, the tree context menu and the plain (collapsible) tree | FR-TREE-002 |
| `Resources/views/manage/_new_page_dialog.html.twig` | New page dialog, create-at-a-place, the tree context menu and the plain (collapsible) tree | FR-TREE-002 |
| `Resources/views/manage/_page_nav.html.twig` | Previous / next page links (`web_ui.page_nav`) | FR-NAV-001 |
| `Resources/views/manage/_tree_menu.html.twig` | New page dialog, create-at-a-place, the tree context menu and the plain (collapsible) tree | FR-TREE-002 |
| `Resources/views/manage/_tree_menu_button.html.twig` | New page dialog, create-at-a-place, the tree context menu and the plain (collapsible) tree | FR-TREE-002 |
| `Resources/views/manage/_tree_new_page_button.html.twig` | New page dialog, create-at-a-place, the tree context menu and the plain (collapsible) tree | FR-TREE-002 |
| `Resources/views/manage/_tree_toggle.html.twig` | New page dialog, create-at-a-place, the tree context menu and the plain (collapsible) tree | FR-TREE-002 |
| `Resources/views/manage/_focus_toggle.html.twig` | 1.41: the UiKit shell's focus mode toggle (area wiki) and the tree drawer button; nothing without UiKit focus mode | FR-UI-013 |
| `Ui/WikiNewPageMenu.php` | New page dialog, create-at-a-place, the tree context menu and the plain (collapsible) tree | FR-TREE-002 |
| `Ui/WikiPageNav.php` | Previous / next page links (`web_ui.page_nav`) | FR-NAV-001 |
| `Ui/WikiTreeMenu.php` | New page dialog, create-at-a-place, the tree context menu and the plain (collapsible) tree | FR-TREE-002 |

## Page layout options (1.29 - 1.38)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/assets/src/wiki-comments-list.ts` | Page layout of the read and edit views: compact header, toolbar, path, children, breadcrumb labels, table scroll, date format and the comments list | FR-UI-013 |
| `Resources/assets/src/wiki-layout.ts` | Page layout of the read and edit views: compact header, toolbar, path, children, breadcrumb labels, table scroll, date format and the comments list | FR-UI-013 |
| `Resources/views/manage/_comments_toggle.html.twig` | Page layout of the read and edit views: compact header, toolbar, path, children, breadcrumb labels, table scroll, date format and the comments list | FR-UI-013 |
| `Resources/views/manage/_page_children.html.twig` | Page layout of the read and edit views: compact header, toolbar, path, children, breadcrumb labels, table scroll, date format and the comments list | FR-UI-013 |
| `Resources/views/manage/_page_children_chip.html.twig` | Page layout of the read and edit views: compact header, toolbar, path, children, breadcrumb labels, table scroll, date format and the comments list | FR-UI-013 |
| `Resources/views/manage/_page_header_compact.html.twig` | Page layout of the read and edit views: compact header, toolbar, path, children, breadcrumb labels, table scroll, date format and the comments list | FR-UI-013 |
| `Resources/views/manage/_page_path.html.twig` | Page layout of the read and edit views: compact header, toolbar, path, children, breadcrumb labels, table scroll, date format and the comments list | FR-UI-013 |
| `Resources/views/manage/_page_toolbar.html.twig` | Page layout of the read and edit views: compact header, toolbar, path, children, breadcrumb labels, table scroll, date format and the comments list | FR-UI-013 |
| `Resources/views/manage/_read_scaffold.html.twig` | Page layout of the read and edit views: compact header, toolbar, path, children, breadcrumb labels, table scroll, date format and the comments list | FR-UI-013 |
| `Ui/WikiBreadcrumbParentLabel.php` | Page layout of the read and edit views: compact header, toolbar, path, children, breadcrumb labels, table scroll, date format and the comments list | FR-UI-013 |
| `Ui/WikiTabActions.php` | Page layout of the read and edit views: compact header, toolbar, path, children, breadcrumb labels, table scroll, date format and the comments list | FR-UI-013 |
| `Util/WikiDateFormat.php` | Page layout of the read and edit views: compact header, toolbar, path, children, breadcrumb labels, table scroll, date format and the comments list | FR-UI-013 |
| `Util/WikiPlainLabel.php` | Page layout of the read and edit views: compact header, toolbar, path, children, breadcrumb labels, table scroll, date format and the comments list | FR-UI-013 |
| `Util/WikiTableScroll.php` | Page layout of the read and edit views: compact header, toolbar, path, children, breadcrumb labels, table scroll, date format and the comments list | FR-UI-013 |
| `Util/WikiTitleDedupe.php` | Page layout of the read and edit views: compact header, toolbar, path, children, breadcrumb labels, table scroll, date format and the comments list | FR-UI-013 |

## Favourites, recents and backlinks (1.45)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Backlink/WikiBacklinks.php` | Linked-from list, viewer-filtered | FR-BL-001 |
| `Backlink/WikiPageReferenceIndex.php` | Backlink index writer | FR-BL-001 |
| `Backlink/WikiPageReferenceListener.php` | Keeps the backlink index current after PAGE_SAVED | FR-BL-001 |
| `Command/WikiBacklinksRebuildCommand.php` | `wiki:backlinks:rebuild` | FR-BL-001 |
| `Entity/WikiPageReference.php` | Backlink index row | FR-BL-001 |
| `Resources/config/doctrine-xml/WikiPageReference.orm.xml` | XML mapping of the backlink row | FR-BL-001 |
| `Resources/views/manage/_backlinks.html.twig` | Linked-from section | FR-BL-001 |
| `Command/WikiQuickAccessEraseUserCommand.php` | `wiki:quick-access:erase-user` | FR-QA-001 |
| `Command/WikiRecentsPurgeCommand.php` | `wiki:recents:purge` | FR-QA-001 |
| `Controller/WikiFavouriteController.php` | Favourite toggle (POST + CSRF) | FR-QA-001 |
| `Form/WikiFavouriteType.php` | Flat favourite form | FR-QA-001 |
| `Entity/WikiPageFavourite.php` | Favourite row | FR-QA-001 |
| `Entity/WikiPageVisit.php` | Recent visit row | FR-QA-001 |
| `Resources/config/doctrine-xml/WikiPageFavourite.orm.xml` | XML mapping of the favourite row | FR-QA-001 |
| `Resources/config/doctrine-xml/WikiPageVisit.orm.xml` | XML mapping of the visit row | FR-QA-001 |
| `Resources/views/manage/_quick_access.html.twig` | Favourites and recents sections | FR-QA-001 |
| `QuickAccess/WikiFavouriteResult.php` | Outcome of a favourite change | FR-QA-001 |
| `QuickAccess/WikiQuickAccess.php` | Favourites and recents of the viewer | FR-QA-001 |
| `QuickAccess/WikiQuickAccessErasureListener.php` | Erases quick access with a person's comments | FR-QA-001 |
| `QuickAccess/WikiQuickAccessStore.php` | Favourite and visit rows store | FR-QA-001 |
| `QuickAccess/WikiReadableSpaces.php` | Viewer scope of one view | FR-QA-001 |
| `QuickAccess/WikiViewerScope.php` | What one viewer may see in the per-viewer lists | FR-QA-001 |
| `History/WikiRenderedRevisionDiff.php` | Rendered diff of two Markdown revisions | FR-HIST-002 |
| `History/WikiViewerBlockRenderer.php` | Per-viewer block renderer of the diff | FR-HIST-002 |
| `Transaction/WikiConflictAnswer.php` | Lock-conflict answer of the favourite toggle | FR-TX-001 |
| `Transaction/WikiStorageFailureConflictFormatter.php` | Lock-conflict formatter of the wiki's routes | FR-TX-001 |

## SearchKit group (1.44 / 1.46 / 1.47)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `DependencyInjection/Compiler/WikiSearchKitPass.php` | Hands the generated provider's tags to the wiki provider | FR-SK-001 |
| `Event/WikiSearchSpace.php` | One space a search would read | FR-SK-001 |
| `Event/WikiSearchSpacesEvent.php` | Host hook: leave spaces out before the SQL | FR-SK-001 |
| `Search/SearchKit/WikiPageSearchDefinition.php` | The wiki's SearchKit definition | FR-SK-001 |
| `Search/SearchKit/WikiPageSearchKitProvider.php` | The wiki's SearchKit group | FR-SK-001 |
| `Search/SearchKit/WikiSearchKitAvailability.php` | SearchKitBundle >= 1.10.1 check | FR-SK-001 |
| `Search/UserProviderWikiSearchViewerResolver.php` | Default viewer resolver (user provider) | FR-SK-001 |
| `Search/WikiReadablePageFinder.php` | Read rules as a bounded query port | FR-SK-001 |
| `Search/WikiReadablePageHits.php` | Readable hits and completeness | FR-SK-001 |
| `Search/WikiSearchTenantResolverInterface.php` | Current tenant port | FR-SK-001 |
| `Search/WikiSearchTenantsResolverAdapter.php` | Single-key to list adapter | FR-SK-001 |
| `Search/WikiSearchTenantsResolverInterface.php` | Current tenants port | FR-SK-001 |
| `Search/WikiSearchViewerResolverInterface.php` | Search viewer port | FR-SK-001 |
| `Search/WikiTenantPathsSearchTenantResolver.php` | Default tenant resolver | FR-SK-001 |

## Space lifecycle and host flush probe (1.49 / 1.50)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Doctrine/WikiHostFlushProbe.php` | Host flush migration list (shared manager mode) | FR-PEM-001 |
| `Event/WikiSpaceAccessCheckEvent.php` | Grant or deny a space lifecycle action | FR-SPACE-002 |
| `Event/WikiSpaceLifecycleEvent.php` | Space renamed, archived or unarchived | FR-SPACE-002 |
| `Event/WikiSpacePurgedEvent.php` | Space purged | FR-SPACE-002 |
| `Security/WikiArchivedSpaceAccessChecker.php` | Archived space rule on the space checker | FR-SPACE-002 |
| `Space/WikiSpaceAccessDeniedException.php` | Manage refused (403) | FR-SPACE-002 |
| `Space/WikiSpaceLifecycle.php` | Default lifecycle service | FR-SPACE-002 |
| `Space/WikiSpaceLifecycleAction.php` | Lifecycle action enum | FR-SPACE-002 |
| `Space/WikiSpaceLifecycleInterface.php` | Lifecycle port | FR-SPACE-002 |
| `Space/WikiSpaceNotFoundException.php` | Not found or not openable (404) | FR-SPACE-002 |
| `Space/WikiSpacePurgeBatch.php` | One committed purge batch | FR-SPACE-002 |
| `Space/WikiSpacePurgeInTransactionException.php` | Purge refused inside a transaction | FR-SPACE-002 |
| `Space/WikiSpacePurgeResult.php` | Purge outcome | FR-SPACE-002 |
| `Space/WikiSpacePurger.php` | Batched space purge | FR-SPACE-002 |
| `Space/WikiSpaceStateException.php` | State refusal (409) | FR-SPACE-002 |
| `Space/WikiSpaceVersionConflictException.php` | Optimistic version conflict (409) | FR-SPACE-002 |

## Read ports and page access (1.51)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Query/WikiArchiveFilter.php` | Archive state filter | FR-QRY-001 |
| `Query/WikiOwnerUsage.php` | What one owner holds | FR-QRY-001 |
| `Query/WikiPageContent.php` | Current revision with body | FR-QRY-001 |
| `Query/WikiPageCriteria.php` | Page query criteria | FR-QRY-001 |
| `Query/WikiPageQuery.php` | Page read port implementation | FR-QRY-001 |
| `Query/WikiPageQueryInterface.php` | Page read port | FR-QRY-001 |
| `Query/WikiPageSearchHit.php` | One search hit | FR-QRY-001 |
| `Query/WikiPageSearchResult.php` | Search outcome | FR-QRY-001 |
| `Query/WikiPageSort.php` | Page order | FR-QRY-001 |
| `Query/WikiQueryIds.php` | Input rules of the read ports | FR-QRY-001 |
| `Query/WikiQueryRows.php` | Scalar selections and snapshots | FR-QRY-001 |
| `Query/WikiSpaceCriteria.php` | Space query criteria | FR-QRY-001 |
| `Query/WikiSpacePageStats.php` | Live pages of one space | FR-QRY-001 |
| `Query/WikiSpaceQuery.php` | Space read port implementation | FR-QRY-001 |
| `Query/WikiSpaceQueryInterface.php` | Space read port | FR-QRY-001 |
| `Query/WikiSpaceSort.php` | Space order | FR-QRY-001 |
| `Security/WikiPageAccess.php` | Page access by ids | FR-ACC-001 |
| `Security/WikiPageAccessInterface.php` | Page access by ids (port) | FR-ACC-001 |
| `Resources/assets/src/raw-modules.d.ts` | Type declaration for Vite `?raw` imports (test support, not shipped runtime) | FR-BUILD-002 |

## Ids, erasure, key columns, assets and host-loaded entities (1.52 – 1.55.2)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Doctrine/WikiExactKeyColumns.php` | 1.52: the bundle's declaration of its identity / access-key columns (REQ-SEC-006) | FR-SEC-011 |
| `Doctrine/WikiManagedEntities.php` | 1.55.2: the private manager's instance of a host-loaded space or page, read again by id before a write | FR-ARCH-002 / FR-WORKER-002 |
| `Repository/WikiSpaceBatchFinderInterface.php` | 1.55.2: spaces by ids in one query, and the no-refresh read for read paths | FR-REPO-001 / FR-WORKER-002 |
| `Repository/WikiCommentByIdFinderInterface.php` | 1.55: comment threads and comments by id, bounded thread reads | FR-ARCH-001 / FR-COMMENT-001 |
| `Util/WikiSpaceSlugRule.php` | 1.55: the space slug rule of the create by id | FR-SPACE-002 |
| `Space/WikiSpaceOwnerMismatchException.php` | 1.55.1: erasure refused for another owner | FR-SPACE-002 |
| `ValueObject/WikiCommentThreadSnapshot.php` | 1.55: comment thread read by id | FR-ARCH-001 |
| `ValueObject/WikiCommentSnapshot.php` | 1.55: comment read by id | FR-ARCH-001 |
| `ValueObject/WikiNewSpace.php` | 1.55: space create by id | FR-ARCH-001 / FR-SPACE-002 |
| `Resources/assets/src/logger.ts` | Console logger of the scripts (REQ-ASSETS-005) | FR-BUILD-001 |
| `Resources/assets/src/script-banner.ts` | "script loaded" banner, safe with a clobbered global | FR-BUILD-001 |
| `Resources/views/manage/_import_export_form.html.twig` | 1.53: import / export dialog loaded on demand | FR-UI-012 / FR-IX-001 |
| `Resources/views/manage/_ai_ask_form.html.twig` | 1.53: AI dialog loaded on demand | FR-UI-012 / FR-AI-001 |
| `Resources/views/manage/_new_page_form.html.twig` | 1.53: new page dialog loaded on demand | FR-UI-012 / FR-PAGE-001 |
| `Resources/views/manage/import_export.html.twig` | Import / export page | FR-IX-001 |
| `Resources/views/manage/_tree_menu_form.html.twig` | 1.53: tree menu dialog loaded on demand | FR-UI-012 / FR-TREE-001 |
| `Resources/views/manage/_milkdown_editor_assets.html.twig` | 1.53: editor assets of a dialog loaded on demand | FR-UI-012 / FR-MD-002 |

## Coverage summary

| Category | Files | Mapped |
| --- | ---: | ---: |
| Bundle & DI | 5 | 5 |
| CLI | 5 | 5 |
| Controllers | 4 | 4 |
| Persistence | 12 | 12 |
| Forms | 6 | 6 |
| Domain models | 6 | 6 |
| Application services | 26 | 26 |
| Security | 15 | 15 |
| AI | 7 | 7 |
| Routing | 3 | 3 |
| Persistence integration | 11 | 11 |
| Support utilities | 2 | 2 |
| Frontend TypeScript | 5 | 5 |
| Symfony config | 1 | 1 |
| Translations | 7 | 7 |
| Twig views | 21 | 21 |
| Markdown and the Milkdown bridge (1.11 / 1.13) | 32 | 32 |
| Co-editing (1.13) | 16 | 16 |
| Page tree (1.14) | 12 | 12 |
| Trash (1.14) | 9 | 9 |
| History timeline (1.15) | 10 | 10 |
| Attachments (1.9) | 14 | 14 |
| Links to host objects (1.9) | 4 | 4 |
| Avatars (1.16) | 4 | 4 |
| Comments (1.16) | 18 | 18 |
| Page files (1.20) | 3 | 3 |
| Framework-free ports and Doctrine isolation (1.27 / 1.36) | 97 | 97 |
| REST API (1.24+) | 68 | 68 |
| Co-editing epochs and resets (1.22 / 1.23) | 9 | 9 |
| Link picker and section links (1.40) | 11 | 11 |
| Worker safety and transactions (1.29.1 / 1.36) | 8 | 8 |
| Icons (1.32 / 1.38 / 1.39) | 7 | 7 |
| Member badges and write provenance (1.34) | 14 | 14 |
| Saving and rendering (1.24 / 1.28) | 17 | 17 |
| Tree menu and page navigation (1.35 / 1.39) | 14 | 14 |
| Page layout options (1.29 - 1.38) | 15 | 15 |
| Favourites, recents and backlinks (1.45) | 26 | 26 |
| SearchKit group (1.44 / 1.46 / 1.47) | 14 | 14 |
| Space lifecycle and host flush probe (1.49 / 1.50) | 16 | 16 |
| Read ports and page access (1.51) | 19 | 19 |
| Ids, erasure, key columns, assets and host-loaded entities (1.52 – 1.55.2) | 17 | 17 |
| **Total production sources** | **610** | **610** |

Audit: `find src -type f ! -path '*/assets/dist/*' ! -name '*.test.ts' | wc -l` must print 610. Every path in the tables exists, once, and every `FR-*` above is defined in [`spec.md`](spec.md).
