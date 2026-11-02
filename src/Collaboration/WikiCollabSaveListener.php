<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Collaboration;

use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Service\WikiPageSavedEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Listener of {@see WikiEvents::PAGE_SAVED} with collaboration on (1.13): the collaborative save of the page edit form
 * (marked with {@see WikiCollabService::COLLAB_SAVE_ATTRIBUTE} on the request) keeps the Yjs log and records the
 * revision; any other save — restore, import, conversion, a host API — replaces the document, so the log is dropped
 * and the live editors reload.
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
        if ($request instanceof Request && $request->attributes->get(WikiCollabService::COLLAB_SAVE_ATTRIBUTE) === $event->page->getId()) {
            $this->collab->saved($event->page, $event->revision);

            return;
        }

        $this->collab->replaced($event->page, $event->revision);
    }
}
