<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Backlink;

use Doctrine\DBAL\Exception\RetryableException;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Service\WikiPageSavedEvent;
use Psr\Log\LoggerInterface;

/**
 * 1.45 (`backlinks.enabled`): keeps the backlink index current. Listens to {@see WikiEvents::PAGE_SAVED} (every save,
 * create, restore, import and conversion), which the bundle dispatches once the save's outermost transaction committed,
 * and indexes the page's current revision in a unit of its own ({@see WikiPageReferenceIndex::reindex()}).
 *
 * The save is already committed when this runs, so a lock conflict of the index write must not turn the save into an
 * error: it is logged and the page's links are indexed again by its next save or by `wiki:backlinks:rebuild` (until
 * then the "Linked from" list of the linked pages is stale for this one source). Inside a transaction another caller
 * opened, and for any other failure, it propagates.
 */
final readonly class WikiPageReferenceListener
{
    public function __construct(
        private WikiPageReferenceIndex $index,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function onPageSaved(WikiPageSavedEvent $event): void
    {
        // Inside a transaction someone else opened, a lock conflict may have rolled back their work too: not ours to hide.
        $nested = $this->index->inTransaction();
        try {
            $this->index->reindex($event->page->getId());
        } catch (TransientStorageFailure|RetryableException $failure) {
            if ($nested) {
                throw $failure;
            }
            $this->logger?->warning('Wiki: the backlink index of a saved page was not updated (lock conflict); its next save or wiki:backlinks:rebuild indexes it.', [
                'bundle'          => 'nowo_wiki',
                'page'            => $event->page->getId(),
                'exception_class' => $failure::class,
            ]);
        }
    }
}
