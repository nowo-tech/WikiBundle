<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Dto;

/**
 * Summary of a wiki export run.
 */
final class WikiExportReport
{
    public function __construct(
        public int $pagesExported = 0,
        public string $outputPath = '',
    ) {
    }
}
