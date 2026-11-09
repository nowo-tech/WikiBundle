<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Database;

use Doctrine\ORM\EntityManager;
use Nowo\WikiBundle\Entity\WikiComment;
use Nowo\WikiBundle\Entity\WikiCommentReaction;
use Nowo\WikiBundle\Entity\WikiCommentThread;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRevisionRepository;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Nowo\WikiBundle\Util\WikiSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * 1.26: deleting a host user keeps the revisions they wrote (`author_id ... ON DELETE SET NULL`, nothing else about
 * them is stored), so the pages whose current revision was theirs keep it. Their comments stay without
 * an author; their own reactions go with them. Runs on SQLite (with `foreign_keys` on), MySQL and PostgreSQL.
 */
final class WikiAuthorDeletionTest extends TestCase
{
    public function testDeletingAUserKeepsTheirRevisionsAndPages(): void
    {
        $em = WikiTestDatabase::create();
        if (!WikiTestDatabase::isServer()) {
            // SQLite enforces foreign keys (and their ON DELETE actions) only when asked to.
            $em->getConnection()->executeStatement('PRAGMA foreign_keys = ON');
        }
        $ana   = new TestUserEntity('ana');
        $bob   = new TestUserEntity('bob');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        foreach ([$ana, $bob, $space] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        $pages = $this->service($em);
        // Ana created the runbook, Bob edited it; Bob created the guide, Ana wrote its current revision.
        $runbook = $pages->create($space, 'Runbook', '<p>ana v1</p>', $ana);
        $pages->saveRevision($runbook, 'Runbook', '<p>bob v2</p>', $bob);
        $guide = $pages->create($space, 'Guide', '<p>bob v1</p>', $bob);
        $pages->saveRevision($guide, 'Guide', '<p>ana v2</p>', $ana);

        $thread  = new WikiCommentThread($guide, $ana);
        $comment = new WikiComment($thread, $ana, 'Looks good');
        $reply   = new WikiComment($thread, $bob, 'Thanks', 1);
        foreach ([$thread, $comment, $reply, new WikiCommentReaction($reply, $ana, '👍'), new WikiCommentReaction($comment, $bob, '🎉')] as $entity) {
            $em->persist($entity);
        }
        $em->flush();
        $runbookId = $runbook->getId();
        $guideId   = $guide->getId();
        $commentId = $comment->getId();

        $em->remove($ana);
        $em->flush();
        $em->clear();

        self::assertNull($em->find(TestUserEntity::class, 'ana'));
        self::assertSame(4, $this->rows($em, 'wiki_page_revisions'));

        $runbook = $em->find(WikiPage::class, $runbookId);
        $guide   = $em->find(WikiPage::class, $guideId);
        self::assertInstanceOf(WikiPage::class, $runbook);
        self::assertInstanceOf(WikiPage::class, $guide);
        // The guide keeps its current revision, written by the deleted user.
        $current = $guide->getCurrentRevision();
        self::assertInstanceOf(WikiPageRevision::class, $current);
        self::assertSame(2, $current->getRevisionNumber());
        self::assertSame('<p>ana v2</p>', $current->getContentHtml());
        self::assertNull($current->getAuthor());

        $first = (new DoctrineOrmWikiPageRevisionRepository($em))->findByPageAndNumber($runbook, 1);
        self::assertInstanceOf(WikiPageRevision::class, $first);
        self::assertNull($first->getAuthor());
        $second = $runbook->getCurrentRevision();
        self::assertInstanceOf(WikiPageRevision::class, $second);
        self::assertInstanceOf(TestUserEntity::class, $second->getAuthor());
        self::assertSame('bob', $second->getAuthor()->getUserIdentifier());

        // Comments stay without an author; the deleted user's reaction goes, Bob's stays.
        $kept = $em->find(WikiComment::class, $commentId);
        self::assertInstanceOf(WikiComment::class, $kept);
        self::assertNull($kept->getAuthor());
        self::assertNull($kept->getThread()->getCreatedBy());
        self::assertSame(2, $this->rows($em, 'wiki_comments'));
        self::assertSame(1, $this->rows($em, 'wiki_comment_reactions'));

        // The page can still be edited: the next revision follows the deleted user's.
        $bob = $em->find(TestUserEntity::class, 'bob');
        self::assertInstanceOf(TestUserEntity::class, $bob);
        self::assertSame(3, $this->service($em)->saveRevision($guide, 'Guide', '<p>bob v3</p>', $bob)->getRevisionNumber());
        $em->getConnection()->close();
    }

    private function rows(EntityManager $em, string $table): int
    {
        return (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM ' . $table);
    }

    private function service(EntityManager $em): WikiPageService
    {
        return new WikiPageService(new DoctrineOrmWikiPageRepository($em), new DoctrineOrmWikiPageRevisionRepository($em), new WikiSlugger(), new WikiHtmlSanitizer(), new EventDispatcher());
    }
}
