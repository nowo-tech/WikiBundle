<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Interchange;

use Nowo\WikiBundle\Enum\WikiInterchangeFormat;

use function basename;
use function count;
use function is_dir;
use function is_string;
use function scandir;
use function str_starts_with;
use function strlen;

use const DIRECTORY_SEPARATOR;

/**
 * Reads Outline/Notion directory trees into importable document nodes.
 */
final readonly class WikiDocumentTreeReader
{
    public function __construct(
        private WikiFrontMatterParser $frontMatterParser,
    ) {
    }

    /**
     * @return list<WikiDocumentNode>
     */
    public function read(string $rootDir, WikiInterchangeFormat $format): array
    {
        $rootDir = $this->normalizeRootDirectory($rootDir);
        $nodes   = [];
        $this->walkDirectory($rootDir, $rootDir, null, $format, $nodes);

        usort($nodes, static fn (WikiDocumentNode $left, WikiDocumentNode $right): int => substr_count($left->relativePath, '/') <=> substr_count($right->relativePath, '/'));

        return $nodes;
    }

    /**
     * @param list<WikiDocumentNode> $nodes
     */
    private function walkDirectory(
        string $rootDir,
        string $currentDir,
        ?string $parentRelativePath,
        WikiInterchangeFormat $format,
        array &$nodes,
    ): void {
        $relativePath  = $this->relativePath($rootDir, $currentDir);
        $markdownFiles = $this->collectMarkdownFiles($currentDir, $format);

        foreach ($markdownFiles as $markdownFile) {
            $parsed     = $this->frontMatterParser->parse((string) file_get_contents($markdownFile));
            $meta       = $parsed['meta'];
            $title      = $this->stringMeta($meta, 'title');
            $slug       = $this->stringMeta($meta, 'wiki_slug') ?? $this->stringMeta($meta, 'slug');
            $parentSlug = $this->stringMeta($meta, 'wiki_parent') ?? $this->stringMeta($meta, 'parent');

            if ($title === null) {
                $title = $this->extractFirstHeading($parsed['body']) ?? basename($markdownFile, '.md');
            }

            $fileRelativePath = $format === WikiInterchangeFormat::Notion
                ? $relativePath
                : ($relativePath === ''
                    ? ($slug ?? basename($markdownFile, '.md'))
                    : $relativePath . '/' . ($slug ?? basename($markdownFile, '.md')));

            $nodes[] = new WikiDocumentNode(
                $fileRelativePath,
                $parentRelativePath,
                $parsed['body'],
                $meta,
                $title,
                $slug,
                $parentSlug,
            );
        }

        $childParentPath = $markdownFiles !== [] ? $relativePath : $parentRelativePath;

        $entries = scandir($currentDir);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }

            $path = $currentDir . DIRECTORY_SEPARATOR . $entry;
            if (!is_dir($path)) {
                continue;
            }

            $this->walkDirectory($rootDir, $path, $childParentPath, $format, $nodes);
        }
    }

    private function normalizeRootDirectory(string $rootDir): string
    {
        $markdownAtRoot = glob($rootDir . '/*.md') ?: [];
        $directories    = array_values(array_filter(scandir($rootDir) ?: [], static function (string $entry) use ($rootDir): bool {
            if ($entry === '.' || $entry === '..') {
                return false;
            }

            return is_dir($rootDir . DIRECTORY_SEPARATOR . $entry);
        }));

        if ($markdownAtRoot === [] && count($directories) === 1 && $this->isLikelyExportWrapper($directories[0])) {
            return $rootDir . DIRECTORY_SEPARATOR . $directories[0];
        }

        return $rootDir;
    }

    private function isLikelyExportWrapper(string $directoryName): bool
    {
        return preg_match('/^(Export[\s-]|export[\s-]|Notion[\s-]|Part-\d)/', $directoryName) === 1;
    }

    /**
     * @return list<string>
     */
    private function collectMarkdownFiles(string $directory, WikiInterchangeFormat $format): array
    {
        if ($format === WikiInterchangeFormat::Notion) {
            $primary = $this->findPrimaryMarkdownFile($directory, $format);

            return $primary !== null ? [$primary] : [];
        }

        $markdownFiles = glob($directory . '/*.md') ?: [];
        sort($markdownFiles);

        return $markdownFiles;
    }

    private function findPrimaryMarkdownFile(string $directory, WikiInterchangeFormat $format): ?string
    {
        $directoryName = basename($directory);
        $candidates    = [];

        if ($format === WikiInterchangeFormat::Notion) {
            $candidates[] = $directory . DIRECTORY_SEPARATOR . $directoryName . '.md';
        }

        $candidates[] = $directory . DIRECTORY_SEPARATOR . 'index.md';
        $candidates[] = $directory . DIRECTORY_SEPARATOR . 'README.md';

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        $markdownFiles = glob($directory . '/*.md') ?: [];
        if (count($markdownFiles) === 1) {
            return $markdownFiles[0];
        }

        if ($format === WikiInterchangeFormat::Outline && $markdownFiles !== []) {
            sort($markdownFiles);

            return $markdownFiles[0];
        }

        return null;
    }

    private function relativePath(string $rootDir, string $path): string
    {
        $rootDir = rtrim($rootDir, '/\\');
        $path    = rtrim($path, '/\\');
        if ($path === $rootDir) {
            return '';
        }

        return ltrim(str_replace('\\', '/', substr($path, strlen($rootDir))), '/');
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function stringMeta(array $meta, string $key): ?string
    {
        if (!isset($meta[$key]) || !is_string($meta[$key]) || trim($meta[$key]) === '') {
            return null;
        }

        return trim($meta[$key]);
    }

    private function extractFirstHeading(string $body): ?string
    {
        if (preg_match('/^\s{0,3}#\s+(.+?)\s*$/m', $body, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }
}
