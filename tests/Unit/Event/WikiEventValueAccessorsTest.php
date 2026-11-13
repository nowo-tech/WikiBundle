<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Event;

use DateTimeImmutable;
use Nowo\WikiBundle\Api\WikiApiLevel;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiWhere;
use Nowo\WikiBundle\Entity\WikiAttachment;
use Nowo\WikiBundle\Entity\WikiComment;
use Nowo\WikiBundle\Entity\WikiCommentThread;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiApiWriteEvent;
use Nowo\WikiBundle\Event\WikiAttachmentEvent;
use Nowo\WikiBundle\Event\WikiCommentAuthorErasedEvent;
use Nowo\WikiBundle\Event\WikiCommentEvent;
use Nowo\WikiBundle\Event\WikiEditorOptionsEvent;
use Nowo\WikiBundle\Event\WikiPageCreatedEvent;
use Nowo\WikiBundle\Event\WikiPageFilesPurgedEvent;
use Nowo\WikiBundle\Event\WikiPageLinkEvent;
use Nowo\WikiBundle\Event\WikiPageMovedEvent;
use Nowo\WikiBundle\Event\WikiPageRestoredEvent;
use Nowo\WikiBundle\Event\WikiPageUnarchivedEvent;
use Nowo\WikiBundle\Event\WikiQuotaCheckEvent;
use Nowo\WikiBundle\Event\WikiTrashEvent;
use Nowo\WikiBundle\Service\WikiPageArchivedEvent;
use Nowo\WikiBundle\Service\WikiPageSavedEvent;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use PHPUnit\Framework\TestCase;

/**
 * 1.34 (REQ-ARCH-001 rules 1 and 4): the events answer with ids, snapshots and user references next to their payload.
 */
final class WikiEventValueAccessorsTest extends TestCase
{
    public function testPageEvents(): void
    {
        $actor    = new TestUser('u-1', 'alice');
        $space    = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $parent   = new WikiPage($space, 'root', 'Root');
        $page     = new WikiPage($space, 'run', 'Runbook', $parent);
        $first    = new WikiPageRevision($page, 1, '<p>a</p>', $actor);
        $second   = new WikiPageRevision($page, 2, '<p>b</p>', $actor);
        $pageless = [
            new WikiPageCreatedEvent($page, $actor),
            new WikiPageUnarchivedEvent($page, $actor),
            new WikiPageArchivedEvent($page, $actor),
            new WikiPageLinkEvent($page, 'ticket', 'T-1', $actor),
            new WikiPageSavedEvent($page, $second, $first, $actor),
            new WikiPageRestoredEvent($page, $first, $second, $actor),
            new WikiPageMovedEvent($page, $parent, null, 1, $actor, new DateTimeImmutable()),
        ];
        foreach ($pageless as $event) {
            self::assertSame($space->getId(), $event->spaceId(), $event::class);
            self::assertSame($page->getId(), $event->pageId(), $event::class);
            self::assertSame($page->getId(), $event->pageSnapshot()->id, $event::class);
            self::assertSame('u-1', $event->actorRef()?->id, $event::class);
            self::assertSame('alice', $event->actorRef()->identifier, $event::class);
        }
        self::assertNull((new WikiPageCreatedEvent($page))->actorRef());

        $saved = new WikiPageSavedEvent($page, $second, $first, $actor);
        self::assertSame($second->getId(), $saved->revisionId());
        self::assertSame(2, $saved->revisionSnapshot()->revisionNumber);
        self::assertSame($first->getId(), $saved->previousRevisionId());
        self::assertSame(1, $saved->previousRevisionSnapshot()?->revisionNumber);
        $created = new WikiPageSavedEvent($page, $first);
        self::assertNull($created->previousRevisionId());
        self::assertNull($created->previousRevisionSnapshot());

        $restored = new WikiPageRestoredEvent($page, $first, $second, $actor);
        self::assertSame($second->getId(), $restored->revisionId());
        self::assertSame(2, $restored->revisionSnapshot()->revisionNumber);
        self::assertSame($first->getId(), $restored->restoredFromId());
        self::assertSame(1, $restored->restoredFromSnapshot()->revisionNumber);

        $moved = new WikiPageMovedEvent($page, $parent, null, 1, $actor, new DateTimeImmutable());
        self::assertSame($parent->getId(), $moved->fromParentId());
        self::assertNull($moved->toParentId());
    }

    public function testSpaceAttachmentAndCommentEvents(): void
    {
        $actor = new TestUser('u-2', 'bob');
        $space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $page  = new WikiPage($space, 'run', 'Runbook');

        foreach ([new WikiTrashEvent($space, 'b', 'Runbook', [], $actor), new WikiPageFilesPurgedEvent($space, 'b', [], [], $actor)] as $event) {
            self::assertSame($space->getId(), $event->spaceId());
            self::assertSame('eng', $event->spaceSnapshot()->slug);
            self::assertSame('u-2', $event->actorRef()?->id);
        }

        $attachment = new WikiAttachment($page, $space, 'a.txt', 'k', 'text/plain', 1, 'sha');
        $event      = new WikiAttachmentEvent($attachment, $actor);
        self::assertSame($attachment->getId(), $event->attachmentId());
        self::assertSame($space->getId(), $event->spaceId());
        self::assertSame($page->getId(), $event->pageId());
        self::assertSame('run', $event->pageSnapshot()->slug);
        self::assertSame('u-2', $event->actorRef()?->id);

        $thread  = new WikiCommentThread($page, $actor);
        $comment = new WikiComment($thread, $actor, 'hi');
        $event   = new WikiCommentEvent($page, $thread, $comment, $actor);
        self::assertSame($space->getId(), $event->spaceId());
        self::assertSame($page->getId(), $event->pageId());
        self::assertSame($page->getId(), $event->pageSnapshot()->id);
        self::assertSame($thread->getId(), $event->threadId());
        self::assertSame($comment->getId(), $event->commentId());
        self::assertNull((new WikiCommentEvent($page, $thread, null, $actor))->commentId());
        self::assertSame('u-2', $event->actorRef()->id);

        $erased = new WikiCommentAuthorErasedEvent($actor, 3, true);
        self::assertSame('u-2', $erased->userId());
        self::assertSame('bob', $erased->userRef()->identifier);
    }

    public function testUserTypedEventsAnswerWithReferences(): void
    {
        $user  = new TestUser('u-3', 'carol', ['ROLE_WIKI']);
        $space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $page  = new WikiPage($space, 'run', 'Runbook');

        $options = new WikiEditorOptionsEvent($space, $page, $user);
        self::assertSame($space->getId(), $options->spaceId());
        self::assertSame('eng', $options->spaceSnapshot()->slug);
        self::assertSame($page->getId(), $options->pageId());
        self::assertSame($page->getId(), $options->pageSnapshot()?->id);
        self::assertSame(['ROLE_WIKI'], $options->userRef()->roles);
        $new = new WikiEditorOptionsEvent($space, null, $user);
        self::assertNull($new->pageId());
        self::assertNull($new->pageSnapshot());

        $quota = new WikiQuotaCheckEvent(WikiQuotaCheckEvent::PAGE_UNARCHIVE, $space, $user, 1, 0, $page);
        self::assertSame($space->getId(), $quota->spaceId());
        self::assertSame('eng', $quota->spaceSnapshot()->slug);
        self::assertSame($page->getId(), $quota->pageId());
        self::assertSame($page->getId(), $quota->pageSnapshot()?->id);
        self::assertSame('u-3', $quota->userRef()->id);
        $create = new WikiQuotaCheckEvent(WikiQuotaCheckEvent::PAGE_CREATE, $space, $user);
        self::assertNull($create->pageId());
        self::assertNull($create->pageSnapshot());

        $write = new WikiApiWriteEvent(new WikiApiPrincipal($user, WikiApiLevel::Edit, WikiApiWhere::everything(), 't', 'n'), 'page.create', 'eng');
        self::assertSame('carol', $write->actorRef()->identifier);
    }
}
