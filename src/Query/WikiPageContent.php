<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Query;

/**
 * 1.51: the current revision of a page with its body, from {@see WikiPageQueryInterface::pageContents()} (an export, a
 * conversion). The body is the stored source: give it only to people who may read the page.
 */
final readonly class WikiPageContent
{
    /**
     * @param string $pageId the page
     * @param string $revisionId its current revision
     * @param int $revisionNumber the revision's number in the page
     * @param string $format `markdown` or `html` (`WikiPageRevision::FORMAT_*`)
     * @param string|null $markdown the Markdown source; null for an HTML revision
     * @param string $html the viewer-neutral HTML cache (the source of an HTML revision)
     * @param string|null $text the plain text the search matches; null before `wiki:search:backfill-text`
     */
    public function __construct(
        public string $pageId,
        public string $revisionId,
        public int $revisionNumber,
        public string $format,
        public ?string $markdown,
        public string $html,
        public ?string $text,
    ) {
    }
}
