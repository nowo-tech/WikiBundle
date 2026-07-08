<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Interchange;

/**
 * Parsed Markdown document ready for wiki import.
 */
final readonly class WikiDocumentNode
{
    /**
     * @param array<string, mixed> $meta
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
