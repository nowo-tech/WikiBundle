<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration;

use DateTimeImmutable;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Entity\WikiComment;
use Nowo\WikiBundle\Entity\WikiCommentReaction;
use Nowo\WikiBundle\Entity\WikiCommentThread;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiCommentRepository;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use PHPUnit\Framework\TestCase;

use function strlen;

/**
 * 1.16: the comment entities and their Doctrine store (page-scoped lookups, children deleted with their parent).
 */
final class WikiCommentRepositoryTest extends TestCase
{
    public function testEntitiesAndPageScopedLookups(): void
    {
        $em    = SqliteWikiEntityManager::create();
        $space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $page  = new WikiPage($space, 'run', 'Runbooks');
        $other = new WikiPage($space, 'other', 'Other');
        $ana   = new TestUserEntity('ana');
        foreach ([$space, $page, $other, $ana] as $entity) {
            $em->persist($entity);
        }
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($em);
        $repo = new DoctrineOrmWikiCommentRepository(new WikiEntityManagerProvider($registry, 'default'));

        $thread   = new WikiCommentThread($page, $ana, '{"v":1}', 'quote');
        $comment  = new WikiComment($thread, $ana, 'Body', 0);
        $reaction = new WikiCommentReaction($comment, $ana, '👍');
        foreach ([$thread, $comment, $reaction] as $entity) {
            $repo->add($entity);
        }
        $repo->flush();

        self::assertSame($page, $thread->getPage());
        self::assertSame($ana, $thread->getCreatedBy());
        self::assertInstanceOf(DateTimeImmutable::class, $thread->getCreatedAt());
        self::assertSame('{"v":1}', $thread->getAnchor());
        self::assertFalse($thread->isPageLevel());
        self::assertSame(36, strlen($reaction->getId()));
        self::assertSame($ana, $reaction->getUser());
        self::assertNull($comment->getDeletedAt());
        $comment->markDeleted();
        self::assertInstanceOf(DateTimeImmutable::class, $comment->getDeletedAt());
        self::assertSame('', $comment->getBody());

        self::assertSame([], $repo->comments([]));
        self::assertSame([], $repo->commentCounts([]));
        self::assertSame([], $repo->reactions([]));
        self::assertSame($thread, $repo->thread($page, $thread->getId()));
        self::assertNull($repo->thread($other, $thread->getId()));
        self::assertNull($repo->comment($other, $comment->getId()));
        self::assertSame($reaction, $repo->reaction($comment, $ana, '👍'));
        self::assertNull($repo->reaction($comment, $ana, '🎉'));
        self::assertSame(1, $repo->countOpen($page));
        self::assertSame(1, $repo->countThreads($page));
        self::assertSame(1, $repo->countOpenBy($page, $ana));
        self::assertSame(0, $repo->countOpen($other));

        $repo->remove($thread);
        $repo->flush();
        self::assertNull($em->find(WikiCommentReaction::class, $reaction->getId()));
        self::assertNull($em->find(WikiComment::class, $comment->getId()));

        // 1.16: a revision's plain text.
        $revision = new WikiPageRevision($page, 1, '<p>Plain <em>text</em></p>', $ana);
        self::assertNull($revision->getContentText());
        $em->persist($revision);
        self::assertSame('Plain text', $revision->getContentText());
        self::assertFalse($revision->refreshContentText());
        $revision->fillContentText();
        self::assertSame('Plain text', $revision->getContentText());
    }
}
