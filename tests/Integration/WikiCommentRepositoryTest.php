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
use Nowo\WikiBundle\Repository\WikiCommentMentionBatch;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use PHPUnit\Framework\TestCase;

use function array_map;
use function sort;
use function strcmp;
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

    public function testMentionLookupsMatchTheWholeHandleInBatches(): void
    {
        // Audit FG-L3: the LIKE uses the whole handle (each "_" as "_" or "\_"), not the part before the first "_".
        $em    = SqliteWikiEntityManager::create();
        $space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $page  = new WikiPage($space, 'run', 'Runbooks');
        $ana   = new TestUserEntity('ana');
        $repo  = new DoctrineOrmWikiCommentRepository($em);
        foreach ([$space, $page, $ana] as $entity) {
            $em->persist($entity);
        }
        $thread = new WikiCommentThread($page, $ana, '{"exact":"@_Bob"}', 'see @_bob');
        $other  = new WikiCommentThread($page, $ana, '{"exact":"@ana"}', '@ana and 100%');
        $em->persist($thread);
        $em->persist($other);
        $bodies = [
            'plain'   => 'Hi @_bob.',
            'escaped' => 'Hi @\\_BOB.',
            'longer'  => 'Hi @_bobby.',
            'at'      => 'Hi @ana, @x and @y.',
            'percent' => '@%bob and @!bob',
            'many'    => 'cc @a\\_b_c\\_d_e',
            'manyNot' => 'cc @a, nothing else',
        ];
        $comments = [];
        foreach ($bodies as $key => $body) {
            $comments[$key] = new WikiComment($thread, $ana, $body);
            $em->persist($comments[$key]);
        }
        $deleted = new WikiComment($thread, $ana, '@_bob');
        $deleted->markDeleted();
        $deleted->redactBody('@_bob');
        $em->persist($deleted);
        $em->flush();

        $ids   = static fn (WikiCommentMentionBatch $batch): array => array_map(static fn (WikiComment|WikiCommentThread $e): string => $e->getId(), $batch->entities);
        $found = $ids($repo->commentsMentioning('_bob', 10));
        sort($found);
        $expected = [$comments['plain']->getId(), $comments['escaped']->getId(), $comments['longer']->getId()];
        sort($expected);
        self::assertSame($expected, $found, 'Only the comments with the whole handle (a superset: @_bobby), never "@" alone.');
        self::assertSame([$comments['many']->getId()], $ids($repo->commentsMentioning('a_b_c_d_e', 10)), 'More than 3 underscores: every part must be there.');
        self::assertSame([$comments['percent']->getId()], $ids($repo->commentsMentioning('%bob', 10)), '"%" is literal, not a wildcard.');
        self::assertSame([$thread->getId()], $ids($repo->threadsMentioning('_BOB', 10)));
        self::assertSame([], $repo->threadsMentioning('_bob', 10, $thread->getId())->entities);
        self::assertSame([$other->getId()], $ids($repo->threadsMentioning('ana', 10)));
        self::assertSame([], $repo->commentsMentioning('nobody', 10)->entities);
        // Audit FG2-L1: instances managed before the lookup are never "loaded" (never released).
        self::assertSame([], $repo->commentsMentioning('_bob', 10)->loaded);

        // Keyset pages ordered by id; only what the lookup loaded itself is released.
        $em->clear();
        $held   = $em->find(WikiComment::class, $comments['plain']->getId());
        $first  = $repo->commentsMentioning('_bob', 2);
        $second = $repo->commentsMentioning('_bob', 2, $first->entities[1]->getId());
        self::assertCount(2, $first->entities);
        self::assertCount(1, $second->entities);
        self::assertLessThan(0, strcmp($first->entities[1]->getId(), $second->entities[0]->getId()));
        $all    = [...$first->entities, ...$second->entities];
        $loaded = [...$first->loaded, ...$second->loaded];
        self::assertContains($held, $all);
        self::assertNotContains($held, $loaded);
        self::assertCount(2, $loaded);
        $repo->release($loaded);
        self::assertFalse($em->contains($loaded[0]));
        self::assertTrue($em->contains($held));
        $repo->release($loaded);

        // One transaction: rolled back as a whole.
        $kept = $em->find(WikiComment::class, $comments['at']->getId());
        self::assertInstanceOf(WikiComment::class, $kept);
        $repo->begin();
        $kept->redactBody('changed');
        $repo->flush();
        $repo->rollBack();
        self::assertSame('Hi @ana, @x and @y.', $em->getConnection()->fetchOne('SELECT body FROM wiki_comments WHERE id = ?', [$kept->getId()]));
        $repo->begin();
        $kept->redactBody('committed');
        $repo->flush();
        $repo->commit();
        self::assertSame('committed', $em->getConnection()->fetchOne('SELECT body FROM wiki_comments WHERE id = ?', [$kept->getId()]));
    }
}
