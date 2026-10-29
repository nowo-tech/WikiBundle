<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Dto;

/**
 * Summary of a wiki export run.
 */
final class WikiExportReport
{
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
