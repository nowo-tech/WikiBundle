<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Space;

use Nowo\WikiBundle\Entity\WikiAttachment;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\PageFile\WikiPageFile;

/**
 * 1.50: what one committed batch of {@see WikiSpacePurger} removed.
 *
 * @internal
 */
final readonly class WikiSpacePurgeBatch
{
    /**
     * @param list<string> $pageIds pages deleted
     * @param list<WikiAttachment> $attachments attachment rows deleted (their files go after the commit)
     * @param list<WikiPageFile> $files files the deleted revisions referred to (`page_files.purge_event`)
     * @param bool $truncated whether `page_files.max_purge_files` cut `$files`
     * @param bool $finished whether the space row is gone (deleted by this batch, or already)
     * @param WikiSpace|null $space the space of the deleted pages (detached), null when no page was deleted
     */
    public function __construct(
        public array $pageIds,
        public array $attachments,
        public array $files,
        public bool $truncated,
        public bool $finished,
        public ?WikiSpace $space = null,
    ) {
    }
}
