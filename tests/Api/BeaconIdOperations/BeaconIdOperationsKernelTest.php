<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\BeaconIdOperations;

use Nowo\WikiBundle\Entity\WikiComment;
use Nowo\WikiBundle\Entity\WikiCommentThread;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Service\WikiPageOperationsInterface;
use Nowo\WikiBundle\Service\WikiSlugTakenException;
use Nowo\WikiBundle\Service\WikiSpaceOperationsInterface;
use Nowo\WikiBundle\Space\WikiSpaceLifecycleInterface;
use Nowo\WikiBundle\Tests\Api\ApiTestCase;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\ValueObject\WikiCommentSnapshot;
use Nowo\WikiBundle\ValueObject\WikiNewSpace;

use function array_map;

/**
 * 1.55 (Beacon): the id operations as a host gets them from the container, through the wiki's private entity manager
 * (2.0: the only mode):
 * a space created with a tenant-path slug and an icon of the settings, a taken slug refused with the host's manager
 * still open and its pending changes neither flushed nor dropped, the comment read models, and the erasure.
 */
final class BeaconIdOperationsKernelTest extends ApiTestCase
{
    protected const OWNER = '0b0c6b66-5c43-4c49-9a1c-3f0b8e2a7d11';

    /** @var array<string, mixed> */
    protected static array $wikiConfig = [
        'tenant_paths' => [
            ['path' => '/projects/{spaceSlug}/wiki', 'name_prefix' => 'project_', 'slug_requirement' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}(?:--[a-z0-9]+(?:-[a-z0-9]+)*)?'],
        ],
    ];

    public function testTheHostCreatesReadsAndErasesThroughTheWiki(): void
    {
        $spaces = self::getContainer()->get(WikiSpaceOperationsInterface::class);
        $pages  = self::getContainer()->get(WikiPageOperationsInterface::class);
        $life   = self::getContainer()->get(WikiSpaceLifecycleInterface::class);
        self::assertInstanceOf(WikiSpaceOperationsInterface::class, $spaces);
        self::assertInstanceOf(WikiPageOperationsInterface::class, $pages);
        self::assertInstanceOf(WikiSpaceLifecycleInterface::class, $life);
        $host = $this->em();
        $host->persist($pending = new TestUserEntity('u-pending'));

        $id = $spaces->createSpaceFrom(new WikiNewSpace(self::OWNER . '--runbooks', 'Runbooks', self::OWNER, icon: 'tabler:book'));

        self::assertSame(self::OWNER . '--runbooks', $spaces->findSpace($id)?->slug);
        self::assertSame('tabler:book', $spaces->findSpace($id)->icon);
        try {
            $spaces->createSpaceFrom(new WikiNewSpace(self::OWNER . '--runbooks', 'Again', self::OWNER));
            self::fail('Expected the slug to be taken.');
        } catch (WikiSlugTakenException) {
        }
        self::assertTrue($host->isOpen());
        self::assertTrue($host->getUnitOfWork()->isScheduledForInsert($pending), 'the host flushes its own changes');
        self::assertSame(0, (int) $host->getConnection()->fetchOne("SELECT COUNT(*) FROM test_users WHERE id = 'u-pending'"));
        $host->flush();

        // Comments written by the wiki (here straight through its manager), read back as read models by id.
        $wiki  = $this->wikiEm();
        $space = $wiki->find(WikiSpace::class, $id);
        self::assertNotNull($space);
        $page   = $this->page($space, 'Intro', '# Intro');
        $author = $wiki->find(TestUserEntity::class, 'u-ana');
        $thread = new WikiCommentThread($page, $author);
        $wiki->persist($thread);
        $wiki->persist($first = new WikiComment($thread, $author, 'Hello'));
        $wiki->persist($reply = new WikiComment($thread, $author, 'Reply', 1));
        $wiki->flush();

        self::assertSame($page->getId(), $pages->findCommentThread($thread->getId())?->pageId);
        self::assertSame('u-ana', $pages->findComment($reply->getId())?->authorId);
        self::assertSame([$first->getId(), $reply->getId()], array_map(static fn (WikiCommentSnapshot $c): string => $c->id, $pages->findThreadComments($thread->getId())));
        self::assertSame('u-ana', $pages->findPageRevision($page->getId(), 1)?->authorId);

        // Account erasure, after the host's commit: complete, then idempotent.
        $result = $life->eraseSpace($id);
        self::assertTrue($result->finished);
        self::assertSame(1, $result->pages);
        self::assertNull($spaces->findSpace($id));
        self::assertNull($pages->findCommentThread($thread->getId()));
        self::assertTrue($life->eraseSpace($id)->finished);
        self::assertSame(0, (int) $host->getConnection()->fetchOne('SELECT COUNT(*) FROM wiki_comments'));
    }
}
