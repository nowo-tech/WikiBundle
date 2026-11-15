<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Port;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Port\WikiSnapshots;
use Nowo\WikiBundle\Port\WikiUserRefs;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\ValueObject\WikiStoredUserRef;
use PHPUnit\Framework\TestCase;
use stdClass;

use function class_exists;

final class WikiSnapshotsTest extends TestCase
{
    public function testSnapshotsCopyIdsAndValues(): void
    {
        $space    = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $parent   = new WikiPage($space, 'root', 'Root');
        $page     = new WikiPage($space, 'child', 'Child', $parent);
        $revision = new WikiPageRevision($page, 3, '<p>x</p>', new TestUser('u-7'));
        $page->setCurrentRevision($revision);
        $page->archive();

        $spaceSnapshot = WikiSnapshots::space($space);
        self::assertSame($space->getId(), $spaceSnapshot->id);
        self::assertSame('docs', $spaceSnapshot->slug);
        self::assertSame('Docs', $spaceSnapshot->name);
        self::assertSame(WikiSpaceOwnerScope::Team, $spaceSnapshot->ownerScopeType);
        self::assertSame('team-1', $spaceSnapshot->ownerScopeId);
        self::assertSame($space->getCreatedAt(), $spaceSnapshot->createdAt);
        self::assertSame($space->getUpdatedAt(), $spaceSnapshot->updatedAt);
        self::assertNull($spaceSnapshot->icon);
        $space->setIcon('tabler:book');
        self::assertSame('tabler:book', WikiSnapshots::space($space)->icon);

        $pageSnapshot = WikiSnapshots::page($page);
        self::assertSame($page->getId(), $pageSnapshot->id);
        self::assertSame($space->getId(), $pageSnapshot->spaceId);
        self::assertSame('child', $pageSnapshot->slug);
        self::assertSame('Child', $pageSnapshot->title);
        self::assertSame($parent->getId(), $pageSnapshot->parentId);
        self::assertSame(0, $pageSnapshot->position);
        self::assertSame($revision->getId(), $pageSnapshot->currentRevisionId);
        self::assertTrue($pageSnapshot->isArchived());
        self::assertSame($page->getCreatedAt(), $pageSnapshot->createdAt);
        self::assertSame($page->getUpdatedAt(), $pageSnapshot->updatedAt);
        self::assertNull(WikiSnapshots::page($parent)->parentId);
        self::assertFalse(WikiSnapshots::page($parent)->isArchived());

        $revisionSnapshot = WikiSnapshots::revision($revision);
        self::assertSame($revision->getId(), $revisionSnapshot->id);
        self::assertSame($page->getId(), $revisionSnapshot->pageId);
        self::assertSame(3, $revisionSnapshot->revisionNumber);
        self::assertSame('html', $revisionSnapshot->format);
        self::assertSame('u-7', $revisionSnapshot->authorId);
        self::assertSame($revision->getCreatedAt(), $revisionSnapshot->createdAt);

        self::assertNull(WikiSnapshots::spaceOrNull(null));
        self::assertNull(WikiSnapshots::pageOrNull(null));
        self::assertNull(WikiSnapshots::revisionOrNull(null));
        self::assertSame($space->getId(), WikiSnapshots::spaceOrNull($space)?->id);
        self::assertSame($page->getId(), WikiSnapshots::pageOrNull($page)?->id);
        self::assertSame($revision->getId(), WikiSnapshots::revisionOrNull($revision)?->id);
    }

    public function testUserRefsFromObjects(): void
    {
        $user = new TestUser('u-1', 'alice@x', ['ROLE_A']);
        $ref  = WikiUserRefs::fromUser($user);
        self::assertSame(TestUser::class, $ref->userClass);
        self::assertSame('u-1', $ref->id);
        self::assertSame('alice@x', $ref->identifier);
        self::assertSame(['ROLE_A'], $ref->roles);
        self::assertSame($user, $ref->user());

        $plain = WikiUserRefs::fromUser(new stdClass());
        self::assertSame(stdClass::class, $plain->userClass);
        self::assertNull($plain->id);
        self::assertSame('', $plain->identifier);

        self::assertNull(WikiUserRefs::fromUser(new TestUser('bob '))->id, 'an id that cannot be matched exactly identifies nobody');

        $stored = new WikiStoredUserRef('9');
        self::assertSame('App\\User', WikiUserRefs::fromUser($stored, 'App\\User')->userClass);
        self::assertSame('9', WikiUserRefs::fromUser($stored)->id);
        self::assertSame(WikiStoredUserRef::class, WikiUserRefs::fromUser($stored)->userClass);
        self::assertSame($stored, WikiUserRefs::fromUser($stored)->user());

        self::assertNull(WikiUserRefs::fromUserOrNull(null));
        self::assertSame('u-1', WikiUserRefs::fromUserOrNull($user)?->id);
        if (!class_exists('Proxies\\__CG__\\Nowo\\WikiBundle\\Tests\\Unit\\Port\\ProxiedUser')) {
            eval('namespace Proxies\\__CG__\\Nowo\\WikiBundle\\Tests\\Unit\\Port; final class ProxiedUser {}');
        }
        $class = 'Proxies\\__CG__\\Nowo\\WikiBundle\\Tests\\Unit\\Port\\ProxiedUser';
        self::assertSame('Nowo\\WikiBundle\\Tests\\Unit\\Port\\ProxiedUser', WikiUserRefs::className(new $class()));
    }
}
