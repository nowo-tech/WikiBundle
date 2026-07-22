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

use const DIRECTORY_SEPARATOR;

/**
 * Exports a wiki space to Outline or Notion compatible Markdown trees.
 */
final readonly class WikiDocumentExporter
{
    public function __construct(
        private WikiPageRepositoryInterface $pageRepository,
        private WikiPageTreeBuilder $pageTreeBuilder,
        private WikiMarkdownConverter $markdownConverter,
        private WikiFrontMatterParser $frontMatterParser,
    ) {
    }

    public function export(
        WikiSpace $space,
        string $targetDir,
        WikiInterchangeFormat $format,
    ): WikiExportReport {
        if ($format === WikiInterchangeFormat::Auto) {
            $format = WikiInterchangeFormat::Outline;
        }

        if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            throw new InvalidArgumentException('Unable to create export directory.');
        }

        $pages  = $this->pageRepository->findActiveBySpace($space);
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
        if (!is_dir($folder) && !mkdir($folder, 0777, true) && !is_dir($folder)) {
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
