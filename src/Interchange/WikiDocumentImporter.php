<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Interchange;

use InvalidArgumentException;
use Nowo\WikiBundle\Dto\WikiImportReport;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiInterchangeFormat;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Util\WikiSlugger;

use function sprintf;

/**
 * Imports Outline/Notion Markdown trees into a wiki space.
 */
final readonly class WikiDocumentImporter
{
    public function __construct(
        private WikiArchiveHelper $archiveHelper,
        private WikiFormatDetector $formatDetector,
        private WikiDocumentTreeReader $treeReader,
        private WikiMarkdownConverter $markdownConverter,
        private WikiPageRepositoryInterface $pageRepository,
        private WikiPageService $pageService,
        private WikiSlugger $slugger,
    ) {
    }

    public function import(
        WikiSpace $space,
        string $sourcePath,
        WikiInterchangeFormat $format,
        object $author,
        bool $updateExisting = false,
        bool $dryRun = false,
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

        $html = $this->markdownConverter->markdownToHtml($node->markdownBody);

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
            $this->pageService->saveRevision($page, $title, $html, $author);
            ++$report->updated;
            $pathMap[$node->relativePath] = $page;

            return;
        }

        if ($dryRun) {
            ++$report->created;
            $pathMap[$node->relativePath] = new WikiPage($space, $slug, $title, $parent);

            return;
        }

        $page                         = $this->pageService->create($space, $title, $html, $author, $parent, $slug);
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

        if (!isset($slugMap[$slug]) && $this->pageRepository->countBySpaceAndSlug($space, $slug) === 0) {
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
