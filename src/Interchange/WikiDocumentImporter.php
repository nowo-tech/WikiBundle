<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Interchange;

use InvalidArgumentException;
use Nowo\WikiBundle\DependencyInjection\Configuration;
use Nowo\WikiBundle\Dto\WikiImportReport;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiInterchangeFormat;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Util\WikiSlugger;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

use function sprintf;

/**
 * Imports Outline/Notion Markdown trees into a wiki space.
 *
 * Logs start / success / failure of every import (space slug, format, counters; never page content).
 */
final readonly class WikiDocumentImporter
{
    /**
     * Wires the archive, detection, parsing, conversion and persistence services used by the import.
     *
     * @param WikiArchiveHelper $archiveHelper extracts ZIP sources to a temporary directory
     * @param WikiFormatDetector $formatDetector detects the export layout when the format is Auto
     * @param WikiDocumentTreeReader $treeReader reads the export tree into document nodes
     * @param WikiMarkdownConverter $markdownConverter converts Markdown bodies to HTML
     * @param WikiPageRepositoryInterface $pageRepository looks up existing pages by slug
     * @param WikiPageService $pageService creates pages and appends revisions
     * @param WikiSlugger $slugger derives and validates page slugs
     * @param LoggerInterface|null $logger logger (defaults to a NullLogger)
     * @param string $editorType `nowo_wiki.editor.type` (1.11): with `markdown` the Markdown bodies are stored as
     *                           Markdown revisions, otherwise they are converted to HTML
     */
    public function __construct(
        private WikiArchiveHelper $archiveHelper,
        private WikiFormatDetector $formatDetector,
        private WikiDocumentTreeReader $treeReader,
        private WikiMarkdownConverter $markdownConverter,
        private WikiPageRepositoryInterface $pageRepository,
        private WikiPageService $pageService,
        private WikiSlugger $slugger,
        ?LoggerInterface $logger = null,
        private string $editorType = Configuration::EDITOR_TIPTAP,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    private LoggerInterface $logger;

    /**
     * Imports a Markdown directory tree or ZIP archive into the given space.
     *
     * @param WikiSpace $space target space
     * @param string $sourcePath directory or ZIP archive path
     * @param WikiInterchangeFormat $format source format (Auto detects it)
     * @param object $author application user recorded as revision author
     * @param bool $updateExisting append a revision to pages whose slug already exists
     * @param bool $dryRun count changes without writing anything
     *
     * @throws InvalidArgumentException when the source is neither a directory nor a ZIP archive
     *
     * @return WikiImportReport counters and per-document messages
     */
    public function import(
        WikiSpace $space,
        string $sourcePath,
        WikiInterchangeFormat $format,
        object $author,
        bool $updateExisting = false,
        bool $dryRun = false,
    ): WikiImportReport {
        $logContext = [
            'bundle'          => 'nowo_wiki',
            'action'          => 'import',
            'space'           => $space->getSlug(),
            'format'          => $format->value,
            'update_existing' => $updateExisting,
            'dry_run'         => $dryRun,
        ];
        $this->logger->info('Wiki import started.', $logContext);

        try {
            $report = $this->doImport($space, $sourcePath, $format, $author, $updateExisting, $dryRun);
        } catch (Throwable $exception) {
            $this->logger->error('Wiki import failed.', $logContext + ['exception_class' => $exception::class]);

            throw $exception;
        }

        $this->logger->info('Wiki import finished.', $logContext + [
            'created' => $report->created,
            'updated' => $report->updated,
            'skipped' => $report->skipped,
            'failed'  => $report->failed,
        ]);

        return $report;
    }

    private function doImport(
        WikiSpace $space,
        string $sourcePath,
        WikiInterchangeFormat $format,
        object $author,
        bool $updateExisting,
        bool $dryRun,
    ): WikiImportReport {
        $report      = new WikiImportReport();
        $cleanupDir  = null;
        $workingPath = $sourcePath;

        if ($this->archiveHelper->isZipPath($sourcePath)) {
            $workingPath = $this->archiveHelper->extractZip($sourcePath);
            $cleanupDir  = $workingPath;
        } elseif (!is_dir($sourcePath)) {
            throw new InvalidArgumentException('Import source must be a directory or ZIP archive.');
        }

        try {
            $resolvedFormat = $format === WikiInterchangeFormat::Auto
                ? $this->formatDetector->detect($workingPath)
                : $format;

            $nodes = $this->treeReader->read($workingPath, $resolvedFormat);
            if ($nodes === []) {
                $report->addMessage('No Markdown documents found in import source.');

                return $report;
            }

            /** @var array<string, WikiPage> $pathMap */
            $pathMap = [];
            /** @var array<string, WikiPage> $slugMap */
            $slugMap = [];
            foreach ($this->pageRepository->findActiveBySpace($space) as $existingPage) {
                $slugMap[$existingPage->getSlug()] = $existingPage;
            }

            foreach ($nodes as $node) {
                try {
                    $this->importNode(
                        $space,
                        $node,
                        $author,
                        $pathMap,
                        $slugMap,
                        $updateExisting,
                        $dryRun,
                        $report,
                    );
                } catch (InvalidArgumentException $exception) {
                    ++$report->failed;
                    $report->addMessage(sprintf('%s: %s', $node->relativePath ?: 'root', $exception->getMessage()));
                }
            }
        } finally {
            if ($cleanupDir !== null) {
                // @igor-ignore - Not shared worker service state.
                $this->archiveHelper->removeDirectory($cleanupDir);
            }
        }

        return $report;
    }

    /**
     * @param array<string, WikiPage> $pathMap
     * @param array<string, WikiPage> $slugMap
     */
    private function importNode(
        WikiSpace $space,
        WikiDocumentNode $node,
        object $author,
        array &$pathMap,
        array &$slugMap,
        bool $updateExisting,
        bool $dryRun,
        WikiImportReport $report,
    ): void {
        $title = $node->title ?? 'Untitled';
        $slug  = $node->slug ?? $this->slugger->slugify($title);
        $slug  = $this->ensureUniqueSlug($space, $slug, $slugMap, $node->slug !== null);

        $parent = null;
        if ($node->parentRelativePath !== null && $node->parentRelativePath !== '' && isset($pathMap[$node->parentRelativePath])) {
            $parent = $pathMap[$node->parentRelativePath];
        } elseif ($node->parentSlug !== null && isset($slugMap[$node->parentSlug])) {
            $parent = $slugMap[$node->parentSlug];
        }

        [$content, $format] = $this->editorType === Configuration::EDITOR_MARKDOWN
            ? [$node->markdownBody, WikiPageRevision::FORMAT_MARKDOWN]
            : [$this->markdownConverter->markdownToHtml($node->markdownBody), WikiPageRevision::FORMAT_HTML];

        if (isset($slugMap[$slug])) {
            if (!$updateExisting) {
                ++$report->skipped;
                $report->addMessage(sprintf('Skipped existing page "%s".', $slug));
                $pathMap[$node->relativePath] = $slugMap[$slug];

                return;
            }

            if ($dryRun) {
                ++$report->updated;
                $pathMap[$node->relativePath] = $slugMap[$slug];

                return;
            }

            $page = $slugMap[$slug];
            $this->pageService->saveRevision($page, $title, $content, $author, $format);
            ++$report->updated;
            $pathMap[$node->relativePath] = $page;

            return;
        }

        if ($dryRun) {
            ++$report->created;
            $pathMap[$node->relativePath] = new WikiPage($space, $slug, $title, $parent);

            return;
        }

        $page                         = $this->pageService->create($space, $title, $content, $author, $parent, $slug, $format);
        $slugMap[$slug]               = $page;
        $pathMap[$node->relativePath] = $page;
        ++$report->created;
    }

    /**
     * @param array<string, WikiPage> $slugMap
     */
    private function ensureUniqueSlug(WikiSpace $space, string $slug, array $slugMap, bool $preserveRequestedSlug): string
    {
        if (!$this->slugger->isValid($slug)) {
            throw new InvalidArgumentException('Invalid slug in import document.');
        }

        if (isset($slugMap[$slug])) {
            return $slug;
        }

        if ($this->pageRepository->countBySpaceAndSlug($space, $slug) === 0) {
            return $slug;
        }

        if ($preserveRequestedSlug) {
            throw new InvalidArgumentException(sprintf('Slug "%s" already exists.', $slug));
        }

        $base = $slug;
        $i    = 2;
        while (isset($slugMap[$slug]) || $this->pageRepository->countBySpaceAndSlug($space, $slug) > 0) {
            $slug = $base . '-' . $i;
            ++$i;
        }

        return $slug;
    }
}
