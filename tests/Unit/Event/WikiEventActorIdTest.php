<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Event;

use DateTimeImmutable;
use Nowo\WikiBundle\Entity\WikiAttachment;
use Nowo\WikiBundle\Entity\WikiCommentThread;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiAttachmentEvent;
use Nowo\WikiBundle\Event\WikiCommentEvent;
use Nowo\WikiBundle\Event\WikiPageCreatedEvent;
use Nowo\WikiBundle\Event\WikiPageFilesPurgedEvent;
use Nowo\WikiBundle\Event\WikiPageLinkEvent;
use Nowo\WikiBundle\Event\WikiPageMovedEvent;
use Nowo\WikiBundle\Event\WikiPageRestoredEvent;
use Nowo\WikiBundle\Event\WikiPageUnarchivedEvent;
use Nowo\WikiBundle\Event\WikiTrashEvent;
use Nowo\WikiBundle\Service\WikiPageArchivedEvent;
use Nowo\WikiBundle\Service\WikiPageSavedEvent;
use Nowo\WikiBundle\Tests\Stub\WikiSecurityUserDto;
use PHPUnit\Framework\TestCase;

/**
 * 1.27: every event with an actor gives its scalar id next to the unchanged object payload.
 */
final class WikiEventActorIdTest extends TestCase
{
    public function testActorIds(): void
    {
        $actor    = new WikiSecurityUserDto(42);
        $space    = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $page     = new WikiPage($space, 'run', 'Runbook');
        $revision = new WikiPageRevision($page, 1, '<p>x</p>', $actor);
        $events   = [
            new WikiPageSavedEvent($page, $revision, null, $actor),
            new WikiPageCreatedEvent($page, $actor),
            new WikiPageArchivedEvent($page, $actor),
            new WikiPageUnarchivedEvent($page, $actor),
            new WikiPageRestoredEvent($page, $revision, $revision, $actor),
            new WikiPageLinkEvent($page, 'ticket', 'T-1', $actor),
            new WikiPageMovedEvent($page, null, null, 0, $actor, new DateTimeImmutable()),
            new WikiTrashEvent($space, 'b', 'Runbook', [], $actor),
            new WikiPageFilesPurgedEvent($space, 'b', [], [], $actor),
            new WikiAttachmentEvent(new WikiAttachment($page, $space, 'a.txt', 'k', 'text/plain', 1, 'sha'), $actor),
            new WikiCommentEvent($page, new WikiCommentThread($page, $actor), null, $actor),
        ];
        foreach ($events as $event) {
            self::assertSame('42', $event->actorId(), $event::class);
        }
        self::assertNull((new WikiPageCreatedEvent($page))->actorId());
    }
}
