<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Interchange;

use InvalidArgumentException;
use Nowo\WikiBundle\Dto\WikiExportReport;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiInterchangeFormat;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Service\WikiPageTreeBuilder;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

use function dirname;
use function ltrim;
use function str_replace;
use function strlen;
use function substr;

use const DIRECTORY_SEPARATOR;

/**
 * Exports a wiki space to Outline or Notion compatible Markdown trees.
 *
 * Logs start / success / failure of every export (space slug, format, page count; never page content).
 */
final readonly class WikiDocumentExporter
{
    /**
     * Wires the repository, tree builder, converter and front matter services used by the export.
     *
     * @param WikiPageRepositoryInterface $pageRepository loads the pages of the space
     * @param WikiPageTreeBuilder $pageTreeBuilder nests pages for the directory layout
     * @param WikiMarkdownConverter $markdownConverter converts stored HTML to Markdown
     * @param WikiFrontMatterParser $frontMatterParser serializes the YAML front matter
     * @param LoggerInterface|null $logger logger (defaults to a NullLogger)
     * @param WikiExportIndexWriter|null $indexWriter writes the README.md index (1.15.1; untranslated English when null)
     */
    public function __construct(
        private WikiPageRepositoryInterface $pageRepository,
        private WikiPageTreeBuilder $pageTreeBuilder,
        private WikiMarkdownConverter $markdownConverter,
        private WikiFrontMatterParser $frontMatterParser,
        ?LoggerInterface $logger = null,
        ?WikiExportIndexWriter $indexWriter = null,
    ) {
        $this->logger      = $logger ?? new NullLogger();
        $this->indexWriter = $indexWriter ?? new WikiExportIndexWriter();
    }

    private LoggerInterface $logger;

    private WikiExportIndexWriter $indexWriter;

    /**
     * Writes the active pages of the space (those the page filter keeps) as Markdown files under the target directory,
     * plus a {@code README.md} index of exactly those pages (1.15.1: an export is never empty).
     *
     * @param WikiSpace $space space to export
     * @param string $targetDir output directory (created when missing)
     * @param WikiInterchangeFormat $format Outline or Notion layout (Auto means Outline)
     * @param (callable(list<WikiPage>): list<WikiPage>)|null $pageFilter keeps the pages the requesting user may read
     *                                                                    (1.10; the web export passes the per-page View rule, the CLI exports everything)
     *
     * @throws InvalidArgumentException when an output directory cannot be created
     *
     * @return WikiExportReport output path and number of exported pages
     */
    public function export(
        WikiSpace $space,
        string $targetDir,
        WikiInterchangeFormat $format,
        ?callable $pageFilter = null,
    ): WikiExportReport {
        if ($format === WikiInterchangeFormat::Auto) {
            $format = WikiInterchangeFormat::Outline;
        }

        $logContext = ['bundle' => 'nowo_wiki', 'action' => 'export', 'space' => $space->getSlug(), 'format' => $format->value];
        $this->logger->info('Wiki export started.', $logContext);

        try {
            $report = $this->doExport($space, $targetDir, $format, $pageFilter);
        } catch (Throwable $exception) {
            $this->logger->error('Wiki export failed.', $logContext + ['exception_class' => $exception::class]);

            throw $exception;
        }

        $this->logger->info('Wiki export finished.', $logContext + ['pages_exported' => $report->pagesExported]);

        return $report;
    }

    /**
     * @param (callable(list<WikiPage>): list<WikiPage>)|null $pageFilter
     */
    private function doExport(WikiSpace $space, string $targetDir, WikiInterchangeFormat $format, ?callable $pageFilter): WikiExportReport
    {

        if (!is_dir($targetDir) && !mkdir($targetDir, 0700, true) && !is_dir($targetDir)) {
            throw new InvalidArgumentException('Unable to create export directory.');
        }

        $pages = $this->pageRepository->findActiveBySpace($space);
        if ($pageFilter !== null) {
            $pages = $pageFilter($pages);
        }
        $tree   = $this->pageTreeBuilder->build($pages);
        $report = new WikiExportReport(outputPath: $targetDir);

        $exported = $this->markdownConverter->dialect ? $this->exportedPaths($tree, $targetDir, $format) : [];
        if ($format === WikiInterchangeFormat::Notion) {
            foreach ($tree as $node) {
                $this->exportNotionNode($node, $targetDir, $report, $exported, 0);
            }
        } else {
            foreach ($tree as $node) {
                $this->exportOutlineNode($node, $targetDir, null, $report, $exported, 0);
            }
        }

        $this->indexWriter->write($space, $targetDir, $report);

        return $report;
    }

    /**
     * @param array{page: WikiPage, children: list<mixed>} $node
     * @param array<string, array{path: string, title: string}> $exported page id => file of the exported pages (1.13)
     */
    private function exportNotionNode(array $node, string $targetDir, WikiExportReport $report, array $exported, int $depth): void
    {
        $page   = $node['page'];
        $folder = $targetDir . DIRECTORY_SEPARATOR . $this->sanitizeFilesystemName($page->getTitle());
        if (!is_dir($folder) && !mkdir($folder, 0700, true) && !is_dir($folder)) {
            throw new InvalidArgumentException('Unable to create export folder.');
        }

        $this->writeMarkdownFile(
            $folder . DIRECTORY_SEPARATOR . $this->sanitizeFilesystemName($page->getTitle()) . '.md',
            $page,
            null,
            $exported,
            $report,
            $depth,
        );

        /** @var list<array{page: WikiPage, children: list<mixed>}> $children */
        $children = $node['children'];
        foreach ($children as $child) {
            $this->exportNotionNode($child, $folder, $report, $exported, $depth + 1);
        }
    }

    /**
     * @param array{page: WikiPage, children: list<mixed>} $node
     * @param array<string, array{path: string, title: string}> $exported page id => file of the exported pages (1.13)
     */
    private function exportOutlineNode(
        array $node,
        string $targetDir,
        ?WikiPage $parent,
        WikiExportReport $report,
        array $exported,
        int $depth,
    ): void {
        $page = $node['page'];
        $this->writeMarkdownFile(
            $targetDir . DIRECTORY_SEPARATOR . $page->getSlug() . '.md',
            $page,
            $parent,
            $exported,
            $report,
            $depth,
        );

        /** @var list<array{page: WikiPage, children: list<mixed>}> $children */
        $children = $node['children'];
        foreach ($children as $child) {
            $this->exportOutlineNode($child, $targetDir, $page, $report, $exported, $depth + 1);
        }
    }

    /**
     * Writes one page and records it in the report (count and README index entry).
     *
     * @param array<string, array{path: string, title: string}> $exported
     */
    private function writeMarkdownFile(string $path, WikiPage $page, ?WikiPage $parent, array $exported, WikiExportReport $report, int $depth): void
    {
        $revision = $page->getCurrentRevision();
        // 1.11: a Markdown revision exports its source as is (1.13: dialect page links to exported pages as file links).
        $markdown = $revision instanceof WikiPageRevision
            ? $revision->getContentMarkdown() ?? $this->markdownConverter->htmlToMarkdown($revision->getContentHtml())
            : '';
        $markdown = WikiMarkdownPortableLinks::rewrite($markdown, dirname($path), $exported);
        $meta     = [
            'title'       => $page->getTitle(),
            'wiki_slug'   => $page->getSlug(),
            'wiki_parent' => $parent?->getSlug() ?? '',
        ];
        $content = $this->frontMatterParser->serialize($meta, $markdown);
        file_put_contents($path, $content);

        ++$report->pagesExported;
        $report->pages[] = [
            'title' => $page->getTitle(),
            'path'  => str_replace(DIRECTORY_SEPARATOR, '/', ltrim(substr($path, strlen($report->outputPath)), '/\\')),
            'depth' => $depth,
        ];
    }

    /**
     * File path and title of every exported page, by page id (1.13, the same layout as the node writers).
     *
     * @param list<array{page: WikiPage, children: list<mixed>}> $nodes
     *
     * @return array<string, array{path: string, title: string}>
     */
    private function exportedPaths(array $nodes, string $directory, WikiInterchangeFormat $format): array
    {
        $paths = [];
        foreach ($nodes as $node) {
            $page = $node['page'];
            $name = $this->sanitizeFilesystemName($page->getTitle());
            $dir  = $format === WikiInterchangeFormat::Notion ? $directory . DIRECTORY_SEPARATOR . $name : $directory;

            $paths[$page->getId()] = [
                'path'  => $dir . DIRECTORY_SEPARATOR . ($format === WikiInterchangeFormat::Notion ? $name : $page->getSlug()) . '.md',
                'title' => $page->getTitle(),
            ];
            /** @var list<array{page: WikiPage, children: list<mixed>}> $children */
            $children = $node['children'];
            $paths += $this->exportedPaths($children, $dir, $format);
        }

        return $paths;
    }

    private function sanitizeFilesystemName(string $value): string
    {
        $sanitized = preg_replace('/[<>:"\/\\\\|?*\\x00-\\x1F]/', '-', $value) ?? $value;
        $sanitized = trim($sanitized, '. ');

        return $sanitized !== '' ? $sanitized : 'untitled';
    }
}
