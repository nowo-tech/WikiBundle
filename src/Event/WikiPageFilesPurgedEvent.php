<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Event;

use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\PageFile\WikiPageFile;

/**
 * {@see WikiEvents::PAGE_FILES_PURGED} (1.20, `page_files.purge_event`): pages were purged from the trash for good, and
 * these are the files their revisions referred to (file cards and images of every revision, without duplicates). The
 * bundle does not own the host's storage: a listener deletes the files it stores — after checking that no other page,
 * template or object still refers to them (a page copied or a block pasted elsewhere keeps the same URL). Dispatched
 * after the purge committed; never on Delete (the trash keeps files for a restore).
 */
final readonly class WikiPageFilesPurgedEvent
{
    /**
     * @param WikiSpace $space the space
     * @param string $batchId the deletion that was purged
     * @param list<string> $pageIds ids of the purged pages (they no longer exist)
     * @param list<WikiPageFile> $files files their revisions referred to
     * @param object|null $actor user who purged (null: the retention command)
     */
    public function __construct(
        public WikiSpace $space,
        public string $batchId,
        public array $pageIds,
        public array $files,
        public ?object $actor = null,
    ) {
    }
}
