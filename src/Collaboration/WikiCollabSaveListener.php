<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Collaboration;

use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Service\WikiPageSavedEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Listener of {@see WikiEvents::PAGE_SAVED} with collaboration on (1.13, "reset only when needed"): a save the page
 * form made from the live session (marked with {@see WikiCollabService::SESSION_SAVE_ATTRIBUTE}) keeps the Yjs log
 * when its base is current ({@see WikiCollabService::saved()}); every other save — outside the session, restore,
 * import, conversion, host API, console — replaces the document ({@see WikiCollabService::replaced()}).
 */
final readonly class WikiCollabSaveListener
{
    /**
     * @param WikiCollabService $collab the collaboration service
     * @param RequestStack|null $requestStack the current request (none in the console: every save is a replacement)
     */
    public function __construct(
        private WikiCollabService $collab,
        private ?RequestStack $requestStack = null,
    ) {
    }

    public function __invoke(WikiPageSavedEvent $event): void
    {
        $request = $this->requestStack?->getCurrentRequest();
        if ($request instanceof Request && $request->attributes->get(WikiCollabService::LOG_HANDLED_ATTRIBUTE) === $event->page->getId()) {
            return;
        }

        if ($request instanceof Request && $request->attributes->get(WikiCollabService::SESSION_SAVE_ATTRIBUTE) === $event->page->getId()) {
            $this->collab->saved($event->page, $event->revision, $event->previousRevision);

            return;
        }

        $this->collab->replaced($event->page, $event->revision);
    }
}
