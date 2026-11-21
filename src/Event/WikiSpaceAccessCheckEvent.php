<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Event;

use Nowo\WikiBundle\Space\WikiSpaceLifecycleAction;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;
use Nowo\WikiBundle\ValueObject\WikiUserRef;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * 1.50 ({@see WikiEvents::SPACE_ACCESS_CHECK}): dispatched before a person renames, archives, unarchives or purges a
 * space, once the space checker answered. The checker's decision (`Manage` on the space) is the default
 * ({@see self::isDefaultGranted()}); a listener may override it with {@see self::grant()} or {@see self::deny()}. A
 * deny sticks: a later grant() is ignored. Only asked for a space the person can open: a space they cannot open is
 * answered as an unknown one before this event.
 */
final class WikiSpaceAccessCheckEvent extends Event
{
    private bool $granted = false;

    private bool $denied = false;

    /**
     * @param WikiSpaceLifecycleAction $action what is being decided
     * @param WikiSpaceSnapshot $space the space, as it is before the change
     * @param WikiUserRef $actor the person asking
     * @param bool $defaultGranted the space checker's decision
     */
    public function __construct(
        public readonly WikiSpaceLifecycleAction $action,
        public readonly WikiSpaceSnapshot $space,
        public readonly WikiUserRef $actor,
        private readonly bool $defaultGranted,
    ) {
    }

    /** The space checker's decision. */
    public function isDefaultGranted(): bool
    {
        return $this->defaultGranted;
    }

    /** Allows the action (ignored after a deny). */
    public function grant(): void
    {
        // @igor-ignore - Per-call event, created per dispatch and excluded from the services; no worker state.
        $this->granted = true;
    }

    /** Refuses the action; final. */
    public function deny(): void
    {
        // @igor-ignore - Per-call event, created per dispatch and excluded from the services; no worker state.
        $this->denied = true;
    }

    /** The decision: a deny wins, then a grant, then the default. */
    public function isGranted(): bool
    {
        if ($this->denied) {
            return false;
        }

        return $this->granted || $this->defaultGranted;
    }
}
