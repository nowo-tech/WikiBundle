<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Dto;

/**
 * Summary of a wiki export run.
 */
final class WikiExportReport
{
    /**
     * Exported pages in tree order (1.15.1): title, path relative to the export root ({@code /} separators) and
     * nesting depth. The README index lists exactly these pages.
     *
     * @var list<array{title: string, path: string, depth: int}>
     */
    public array $pages = [];

    /**
     * Creates an export report.
     *
     * @param int $pagesExported number of pages written to the export
     * @param string $outputPath path of the generated export (directory or archive)
     */
    public function __construct(
        public int $pagesExported = 0,
        public string $outputPath = '',
    ) {
    }
}
