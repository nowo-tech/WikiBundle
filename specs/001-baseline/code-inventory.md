# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/wiki-bundle`  
**Last audited**: 2026-11-06 (every file of `src/` mapped after reading it, including 1.9 to 1.21; audit: `find src -type f ! -path '*/assets/dist/*' ! -name '*.test.ts' | wc -l` → 256)

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
| `Controller/WikiTreeController.php` | Tree endpoints (move, state) | FR-TREE-001 |
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

## Coverage summary

| Category | Files | Mapped |
| --- | ---: | ---: |
| Bundle & DI | 5 | 5 |
| CLI | 5 | 5 |
| Controllers | 4 | 4 |
| Persistence | 12 | 12 |
| Forms | 6 | 6 |
| Domain models | 6 | 6 |
| Application services | 25 | 25 |
| Security | 15 | 15 |
| AI | 7 | 7 |
| Routing | 3 | 3 |
| Persistence integration | 10 | 10 |
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
| **Total production sources** | **256** | **256** |

Audit: `find src -type f ! -path '*/assets/dist/*' ! -name '*.test.ts' | wc -l` must print 256. Every path in the tables exists, once, and every `FR-*` above is defined in [`spec.md`](spec.md).
