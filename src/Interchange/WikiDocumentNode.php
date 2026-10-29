<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Interchange;

/**
 * Parsed Markdown document ready for wiki import.
 */
final readonly class WikiDocumentNode
{
    /**
     * Holds one parsed document of an export tree.
     *
     * @param string $relativePath path of the document relative to the export root
     * @param string|null $parentRelativePath relative path of the parent document, or null for a root
     * @param string $markdownBody Markdown body without front matter
     * @param array<string, mixed> $meta parsed front matter
     * @param string|null $title resolved title (front matter, first heading or file name)
     * @param string|null $slug slug from front matter, if any
     * @param string|null $parentSlug parent slug from front matter, if any
     */
    public function __construct(
        public string $relativePath,
        public ?string $parentRelativePath,
        public string $markdownBody,
        public array $meta,
        public ?string $title,
        public ?string $slug,
        public ?string $parentSlug,
    ) {
    }
}
