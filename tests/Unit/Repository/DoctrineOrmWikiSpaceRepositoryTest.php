<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
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
        $repo  = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($space);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $found = (new DoctrineOrmWikiSpaceRepository($em))->findBySlug(WikiSpaceOwnerScope::Team, 't', 's');

        self::assertSame($space, $found);
    }

    public function testFindById(): void
    {
        $space = new WikiSpace('s', 'S', WikiSpaceOwnerScope::Team, 't');
        $em    = $this->createMock(EntityManagerInterface::class);
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

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getResult')->willReturn([$space]);

        $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        $found = (new DoctrineOrmWikiSpaceRepository($em))->findAccessible('team', ['t']);

        self::assertSame([$space], $found);
    }

    public function testFindFirstBySlug(): void
    {
        $space = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 't');

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getOneOrNullResult')->willReturn($space);

        $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
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
}
