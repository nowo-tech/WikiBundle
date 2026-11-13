<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiSpaceRepository;
use PHPUnit\Framework\TestCase;

final class DoctrineOrmWikiSpaceRepositoryTest extends TestCase
{
    public function testSavePersistsSpace(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        (new DoctrineOrmWikiSpaceRepository($em))->save(new WikiSpace('s', 'S', WikiSpaceOwnerScope::Team, 't'));
    }

    public function testFindBySlugDelegatesToRepository(): void
    {
        $space = new WikiSpace('s', 'S', WikiSpaceOwnerScope::Team, 't');
        $query = $this->createMock(Query::class);
        $query->expects(self::once())->method('setHint')->with(Query::HINT_REFRESH, true)->willReturnSelf();
        $query->method('getResult')->willReturn([$space]);

        $found = (new DoctrineOrmWikiSpaceRepository($this->entityManagerFor($query)))->findBySlug(WikiSpaceOwnerScope::Team, 't', 's');

        self::assertSame($space, $found);
    }

    /**
     * 1.31.1 (house rule LQ): a folding or PAD SPACE collation also returns the spaces of look-alike owners; only the
     * exact owner, scope type and slug count, whatever the order the database returns them in.
     */
    public function testFindBySlugReChecksOwnerScopeAndSlugInPhp(): void
    {
        $exact = new WikiSpace('s', 'S', WikiSpaceOwnerScope::User, 'alice');
        $query = $this->createMock(Query::class);
        $query->method('setHint')->willReturnSelf();
        $query->method('getResult')->willReturnOnConsecutiveCalls(
            [new WikiSpace('s', 'S', WikiSpaceOwnerScope::User, 'Alice'), new WikiSpace('s', 'S', WikiSpaceOwnerScope::User, 'alice '), $exact],
            [new WikiSpace('S', 'S', WikiSpaceOwnerScope::User, 'alice'), new WikiSpace('s', 'S', WikiSpaceOwnerScope::Team, 'alice')],
        );
        $repository = new DoctrineOrmWikiSpaceRepository($this->entityManagerFor($query));

        self::assertSame($exact, $repository->findBySlug(WikiSpaceOwnerScope::User, 'alice', 's'));
        self::assertNull($repository->findBySlug(WikiSpaceOwnerScope::User, 'alice', 's'));
    }

    /** 1.31.1 (house rule LQ): look-alike owners and other scope types returned by the SQL `IN` are dropped. */
    public function testFindAccessibleReChecksOwnerInPhp(): void
    {
        $alice = new WikiSpace('a', 'A', WikiSpaceOwnerScope::User, 'alice');
        $bob   = new WikiSpace('b', 'B', WikiSpaceOwnerScope::User, 'bob');
        $query = $this->createMock(Query::class);
        $query->method('setHint')->willReturnSelf();
        $query->method('getResult')->willReturn([
            new WikiSpace('c', 'C', WikiSpaceOwnerScope::User, 'Alice'),
            $alice,
            new WikiSpace('d', 'D', WikiSpaceOwnerScope::User, 'alice '),
            new WikiSpace('e', 'E', WikiSpaceOwnerScope::Team, 'bob'),
            $bob,
            new WikiSpace('f', 'F', WikiSpaceOwnerScope::User, "\u{e1}lice"),
        ]);

        self::assertSame([$alice, $bob], (new DoctrineOrmWikiSpaceRepository($this->entityManagerFor($query)))->findAccessible('user', ['alice', 'bob']));
    }

    public function testFindById(): void
    {
        $space = new WikiSpace('s', 'S', WikiSpaceOwnerScope::Team, 't');
        $em    = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('refresh');
        $em->method('find')->willReturn($space);

        $found = (new DoctrineOrmWikiSpaceRepository($em))->findById($space->getId());

        self::assertSame($space, $found);
    }

    public function testFindAccessibleReturnsEmptyForNoScopeIds(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('createQueryBuilder');

        self::assertSame([], (new DoctrineOrmWikiSpaceRepository($em))->findAccessible('team', []));
    }

    public function testFindAccessibleReturnsSpaces(): void
    {
        $space = new WikiSpace('s', 'S', WikiSpaceOwnerScope::Team, 't');

        $query = $this->createMock(Query::class);
        $query->method('setHint')->willReturnSelf();
        $query->method('getResult')->willReturn([$space]);

        $found = (new DoctrineOrmWikiSpaceRepository($this->entityManagerFor($query)))->findAccessible('team', ['t']);

        self::assertSame([$space], $found);
    }

    public function testFindFirstBySlug(): void
    {
        $space = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 't');

        $query = $this->createMock(Query::class);
        $query->method('setHint')->willReturnSelf();
        $query->method('getOneOrNullResult')->willReturn($space);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        $found = (new DoctrineOrmWikiSpaceRepository($em))->findFirstBySlug('docs');

        self::assertSame($space, $found);
    }

    private function entityManagerFor(Query $query): EntityManagerInterface
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        return $em;
    }
}
