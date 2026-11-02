<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Collaboration;

use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Service\WikiPageSavedEvent;

/**
 * Listener of {@see WikiEvents::PAGE_SAVED} with collaboration on (1.13): every save of a page — the edit form, a
 * restore, an import, a conversion, a host API — makes the saved Markdown the source of truth, so the Yjs log is
 * dropped and the live editors reload (user decision 2026-11-02, audit CW-L1).
 */
final readonly class WikiCollabSaveListener
{
    /**
     * @param WikiCollabService $collab the collaboration service
     */
    public function __construct(
        private WikiCollabService $collab,
    ) {
    }

    public function __invoke(WikiPageSavedEvent $event): void
    {
        $this->collab->replaced($event->page, $event->revision);
    }
}
