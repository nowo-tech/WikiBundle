<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Event;

use DateTimeImmutable;
use Nowo\WikiBundle\Space\WikiSpaceLifecycleAction;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;
use Nowo\WikiBundle\ValueObject\WikiUserRef;

/**
 * 1.50: a space was renamed, archived or brought back ({@see WikiEvents::SPACE_RENAMED},
 * {@see WikiEvents::SPACE_ARCHIVED}, {@see WikiEvents::SPACE_UNARCHIVED}); dispatched after the change committed (after
 * the outermost transaction when the call ran inside one).
 */
final readonly class WikiSpaceLifecycleEvent
{
    /**
     * @param WikiSpaceLifecycleAction $action what happened
     * @param WikiSpaceSnapshot $space the space after the change
     * @param WikiSpaceSnapshot $before the space before the change
     * @param WikiUserRef|null $actor who asked; null for a trusted call without a person (a command, a message handler)
     * @param DateTimeImmutable $at when it happened
     */
    public function __construct(
        public WikiSpaceLifecycleAction $action,
        public WikiSpaceSnapshot $space,
        public WikiSpaceSnapshot $before,
        public ?WikiUserRef $actor,
        public DateTimeImmutable $at,
    ) {
    }
}
