<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Event;

use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\PageFile\WikiPageFile;
use Nowo\WikiBundle\Port\WikiSnapshots;
use Nowo\WikiBundle\Port\WikiUserRefs;
use Nowo\WikiBundle\User\WikiUserId;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;
use Nowo\WikiBundle\ValueObject\WikiUserRef;

/**
 * {@see WikiEvents::PAGE_FILES_PURGED} (1.20, `page_files.purge_event`): pages were purged from the trash for good, and
 * these are the files their revisions referred to (file cards and images of every revision, without duplicates).
 *
 * **`files` are untrusted, editor-supplied URLs** (audit GR-L2): anyone who could edit one of the pages could write any
 * URL into it, including another space's or user's file. A listener must, for each URL:
 * - map it to a stored file by **lookup** (URL → record of your upload endpoint), never by building a path from it
 *   (relative URLs may contain `..`);
 * - delete only files it **owns for this space / these pages** (space, page or uploader recorded at upload time);
 * - skip files another page, template or object still refers to (copied pages and pasted blocks keep the same URL).
 *
 * Dispatched after the purge committed, also when a {@see WikiEvents::TRASH_PURGED} listener throws; never on Delete
 * (the trash keeps files for a restore). At most `page_files.max_purge_files` files: `truncated` says the list was cut.
 */
final readonly class WikiPageFilesPurgedEvent
{
    /**
     * @param WikiSpace $space the space
     * @param string $batchId the deletion that was purged
     * @param list<string> $pageIds ids of the purged pages (they no longer exist)
     * @param list<WikiPageFile> $files files their revisions referred to (untrusted URLs, see above)
     * @param object|null $actor user who purged (null: the retention command)
     * @param bool $truncated whether `page_files.max_purge_files` cut the list
     */
    public function __construct(
        public WikiSpace $space,
        public string $batchId,
        public array $pageIds,
        public array $files,
        public ?object $actor = null,
        public bool $truncated = false,
    ) {
    }

    /**
     * The actor's id (`getId()` as a string; null without an actor or an id), so listeners need not know the user
     * class (1.27).
     */
    public function actorId(): ?string
    {
        return WikiUserId::of($this->actor);
    }

    /**
     * 1.34: the space's id.
     */
    public function spaceId(): string
    {
        return $this->space->getId();
    }

    /**
     * 1.34: the space as a framework-free snapshot.
     */
    public function spaceSnapshot(): WikiSpaceSnapshot
    {
        return WikiSnapshots::space($this->space);
    }

    /**
     * 1.34: the actor as a framework-free reference; null without an actor.
     */
    public function actorRef(): ?WikiUserRef
    {
        return WikiUserRefs::fromUserOrNull($this->actor);
    }
}
