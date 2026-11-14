<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Provenance;

use Nowo\WikiBundle\Comment\WikiCommentService;
use Nowo\WikiBundle\Event\WikiCommentAuthorErasedEvent;
use Nowo\WikiBundle\Event\WikiEvents;

/**
 * Spec 234 (audit PQ-L1): the bundle's user-erasure path ({@see WikiCommentService::eraseAuthor()},
 * `wiki:comments:erase-user`, {@see WikiEvents::COMMENT_AUTHOR_ERASED}) also erases the actor labels of the user's
 * revisions, and those of revisions whose author was already deleted (`author_id` NULL), so a host that follows the
 * documented privacy path keeps nothing of them.
 */
final readonly class WikiProvenanceAuthorErasedListener
{
    public function __construct(private WikiWriteProvenanceErasure $erasure)
    {
    }

    public function __invoke(WikiCommentAuthorErasedEvent $event): void
    {
        $this->erasure->eraseActor($event->user);
        $this->erasure->eraseOrphaned();
    }
}
