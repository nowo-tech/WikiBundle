<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Event;

use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\User\WikiUserId;

/**
 * A trash change (1.14, T065): {@see WikiEvents::PAGE_TRASHED}, {@see WikiEvents::TRASH_RESTORED} or
 * {@see WikiEvents::TRASH_PURGED} (after a purge the pages no longer exist: ids and the root title only).
 */
final readonly class WikiTrashEvent
{
    /**
     * @param WikiSpace $space the space
     * @param string $batchId the deletion (the page the user deleted and its sub-pages)
     * @param string $rootTitle title of the page the user deleted
     * @param list<string> $pageIds every page of the deletion
     * @param object|null $actor user who acted (null: the retention command)
     */
    public function __construct(
        public WikiSpace $space,
        public string $batchId,
        public string $rootTitle,
        public array $pageIds,
        public ?object $actor = null,
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
}
