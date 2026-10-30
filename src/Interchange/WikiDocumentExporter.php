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
     */
    public function __construct(
        private WikiPageRepositoryInterface $pageRepository,
        private WikiPageTreeBuilder $pageTreeBuilder,
        private WikiMarkdownConverter $markdownConverter,
        private WikiFrontMatterParser $frontMatterParser,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    private LoggerInterface $logger;

    /**
     * Writes the active pages of the space (those the page filter keeps) as Markdown files under the target directory.
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

        if ($format === WikiInterchangeFormat::Notion) {
            foreach ($tree as $node) {
                $this->exportNotionNode($node, $targetDir, $report);
            }
        } else {
            foreach ($tree as $node) {
                $this->exportOutlineNode($node, $targetDir, null, $report);
            }
        }

        return $report;
    }

    /**
     * @param array{page: WikiPage, children: list<mixed>} $node
     */
    private function exportNotionNode(array $node, string $targetDir, WikiExportReport $report): void
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
        );
        ++$report->pagesExported;

        /** @var list<array{page: WikiPage, children: list<mixed>}> $children */
        $children = $node['children'];
        foreach ($children as $child) {
            $this->exportNotionNode($child, $folder, $report);
        }
    }

    /**
     * @param array{page: WikiPage, children: list<mixed>} $node
     */
    private function exportOutlineNode(
        array $node,
        string $targetDir,
        ?WikiPage $parent,
        WikiExportReport $report,
    ): void {
        $page = $node['page'];
        $this->writeMarkdownFile(
            $targetDir . DIRECTORY_SEPARATOR . $page->getSlug() . '.md',
            $page,
            $parent,
        );
        ++$report->pagesExported;

        /** @var list<array{page: WikiPage, children: list<mixed>}> $children */
        $children = $node['children'];
        foreach ($children as $child) {
            $this->exportOutlineNode($child, $targetDir, $page, $report);
        }
    }

    private function writeMarkdownFile(string $path, WikiPage $page, ?WikiPage $parent): void
    {
        $revision = $page->getCurrentRevision();
        $markdown = $revision instanceof WikiPageRevision ? $this->markdownConverter->htmlToMarkdown($revision->getContentHtml()) : '';
        $meta     = [
            'title'       => $page->getTitle(),
            'wiki_slug'   => $page->getSlug(),
            'wiki_parent' => $parent?->getSlug() ?? '',
        ];
        $content = $this->frontMatterParser->serialize($meta, $markdown);
        file_put_contents($path, $content);
    }

    private function sanitizeFilesystemName(string $value): string
    {
        $sanitized = preg_replace('/[<>:"\/\\\\|?*\\x00-\\x1F]/', '-', $value) ?? $value;
        $sanitized = trim($sanitized, '. ');

        return $sanitized !== '' ? $sanitized : 'untitled';
    }
}
