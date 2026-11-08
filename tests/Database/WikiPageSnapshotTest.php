<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Database;

use Doctrine\ORM\EntityManager;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRevisionRepository;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Service\WikiRevisionConflictException;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Nowo\WikiBundle\Util\WikiSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * 1.24 (audit IF-I2): a save from an expected revision inside an outer transaction that already read (the co-editing
 * log's lock; on MySQL its REPEATABLE READ snapshot is then fixed) still sees the revision another writer committed
 * since: the page and its current revision are read with locks, and the next number follows the current one.
 */
final class WikiPageSnapshotTest extends TestCase
{
    public function testAnOuterSnapshotDoesNotHideANewerRevision(): void
    {
        if (!WikiTestDatabase::isServer()) {
            self::markTestSkipped('Needs ' . WikiTestDatabase::ENV . ' (MySQL or PostgreSQL).');
        }
        $em    = WikiTestDatabase::create();
        $user  = new TestUserEntity('u-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $em->persist($user);
        $em->persist($space);
        $em->flush();
        $mine = $this->service($em);
        $page = $mine->create($space, 'Runbook', 'one', $user, null, null, WikiPageRevision::FORMAT_MARKDOWN);

        // The outer transaction reads first: on MySQL that fixes its snapshot.
        $em->getConnection()->beginTransaction();
        $em->getConnection()->fetchOne('SELECT COUNT(*) FROM wiki_page_revisions');

        // Someone else commits revision 2 meanwhile.
        $other     = WikiTestDatabase::connect();
        $otherPage = $other->find(WikiPage::class, $page->getId());
        $otherUser = $other->find(TestUserEntity::class, 'u-1');
        self::assertInstanceOf(WikiPage::class, $otherPage);
        self::assertInstanceOf(TestUserEntity::class, $otherUser);
        $this->service($other)->saveRevision($otherPage, 'Runbook', 'theirs', $otherUser, WikiPageRevision::FORMAT_MARKDOWN);
        $other->getConnection()->close();

        try {
            $mine->saveRevision($page, 'Runbook', 'stale', $user, WikiPageRevision::FORMAT_MARKDOWN, 1);
            self::fail('The stale save must see revision 2.');
        } catch (WikiRevisionConflictException $conflict) {
            self::assertSame(2, $conflict->currentRevision);
        }

        $revision = $mine->saveRevision($page, 'Runbook', 'mine', $user, WikiPageRevision::FORMAT_MARKDOWN, 2);
        self::assertSame(3, $revision->getRevisionNumber());
        $em->getConnection()->commit();
        self::assertSame(3, (int) $em->getConnection()->fetchOne('SELECT MAX(revision_number) FROM wiki_page_revisions'));
        $em->getConnection()->close();
    }

    private function service(EntityManager $em): WikiPageService
    {
        return new WikiPageService(new DoctrineOrmWikiPageRepository($em), new DoctrineOrmWikiPageRevisionRepository($em), new WikiSlugger(), new WikiHtmlSanitizer(), new EventDispatcher());
    }
}
