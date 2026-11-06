<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Interchange;

use InvalidArgumentException;

use function file_get_contents;
use function sprintf;
use function strlen;

/**
 * Reads one Markdown document of an import with a bounded read (audit GV-L3): a ZIP entry over the limit is refused by
 * {@see WikiArchiveHelper} before extraction, and this covers the directory sources of `wiki:import`, which pass
 * no archive check.
 */
final readonly class WikiMarkdownFileReader
{
    /**
     * @param positive-int $maxDocumentBytes largest Markdown document accepted, in bytes (`import_export.max_document_bytes`)
     */
    public function __construct(
        private int $maxDocumentBytes = WikiArchiveHelper::DEFAULT_MAX_DOCUMENT_BYTES,
    ) {
    }

    /**
     * Returns the content of a Markdown file, never holding more than the limit plus one byte in memory.
     *
     * @param string $path Markdown file path
     *
     * @throws InvalidArgumentException when the file is over the limit
     */
    public function read(string $path): string
    {
        $content = (string) file_get_contents($path, false, null, 0, $this->maxDocumentBytes + 1);
        if (strlen($content) > $this->maxDocumentBytes) {
            throw new InvalidArgumentException(sprintf('Markdown document "%s" exceeds the limit of %d bytes per document.', basename($path), $this->maxDocumentBytes));
        }

        return $content;
    }
}
