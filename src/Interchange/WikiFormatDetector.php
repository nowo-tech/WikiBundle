<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Interchange;

use FilesystemIterator;
use Nowo\WikiBundle\Enum\WikiInterchangeFormat;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Heuristically detects Outline vs Notion export layouts.
 */
final readonly class WikiFormatDetector
{
    public function __construct(
        private WikiFrontMatterParser $frontMatterParser,
    ) {
    }

    public function detect(string $rootDir): WikiInterchangeFormat
    {
        $notionScore  = 0;
        $outlineScore = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootDir, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            // @codeCoverageIgnoreStart
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }
            // @codeCoverageIgnoreEnd

            if (strtolower($file->getExtension()) !== 'md') {
                continue;
            }

            $dirName  = basename($file->getPath());
            $fileName = $file->getBasename('.md');
            if (strcasecmp($dirName, $fileName) === 0) {
                ++$notionScore;
            }

            $parsed = $this->frontMatterParser->parse((string) file_get_contents($file->getPathname()));
            $meta   = $parsed['meta'];
            if (isset($meta['wiki_slug']) || isset($meta['wiki_parent']) || isset($meta['slug']) || isset($meta['parent'])) {
                ++$outlineScore;
            }
        }

        if ($notionScore > $outlineScore) {
            return WikiInterchangeFormat::Notion;
        }

        return WikiInterchangeFormat::Outline;
    }
}
