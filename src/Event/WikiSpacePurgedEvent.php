<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Event;

use DateTimeImmutable;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;
use Nowo\WikiBundle\ValueObject\WikiUserRef;

/**
 * 1.50 ({@see WikiEvents::SPACE_PURGED}): a space was deleted for good with its pages, revisions, comments, attachments,
 * links, favourites, visits and references; dispatched once, after the last batch committed (the call that finished
 * the purge). The space row no longer exists.
 */
final readonly class WikiSpacePurgedEvent
{
    /**
     * @param WikiSpaceSnapshot $space the space as it was before its row went
     * @param int $pages pages deleted by the call that finished the purge (earlier, interrupted calls not counted)
     * @param WikiUserRef|null $actor who asked; null for a trusted call without a person
     * @param DateTimeImmutable $at when the purge finished
     */
    public function __construct(
        public WikiSpaceSnapshot $space,
        public int $pages,
        public ?WikiUserRef $actor,
        public DateTimeImmutable $at,
    ) {
    }
}
