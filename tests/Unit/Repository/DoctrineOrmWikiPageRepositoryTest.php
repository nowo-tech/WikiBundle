<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use PHPUnit\Framework\TestCase;

final class DoctrineOrmWikiPageRepositoryTest extends TestCase
{
    public function testSavePersistsPage(): void
    {
        $page = new WikiPage(new WikiSpace('s', 'S', WikiSpaceOwnerScope::Team, 't'), 'p', 'P');
        $em   = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with($page);
        $em->expects(self::once())->method('flush');

        (new DoctrineOrmWikiPageRepository($em))->save($page);
    }

    public function testFindActiveBySpace(): void
    {
        $space = new WikiSpace('s', 'S', WikiSpaceOwnerScope::Team, 't');
        $page  = new WikiPage($space, 'p', 'P');

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([$page]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('addOrderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        $found = (new DoctrineOrmWikiPageRepository($em))->findActiveBySpace($space);

        self::assertSame([$page], $found);
    }

    public function testCountBySpaceAndSlugWithExclude(): void
    {
        $space = new WikiSpace('s', 'S', WikiSpaceOwnerScope::Team, 't');

        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn(0);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        $count = (new DoctrineOrmWikiPageRepository($em))->countBySpaceAndSlug($space, 'slug', 'exclude-id');

        self::assertSame(0, $count);
    }

    public function testFindBySlugDelegatesToRepository(): void
    {
        $space = new WikiSpace('s', 'S', WikiSpaceOwnerScope::Team, 't');
        $page  = new WikiPage($space, 'p', 'P');
        $repo  = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($page);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $found = (new DoctrineOrmWikiPageRepository($em))->findBySlug($space, 'p');

        self::assertSame($page, $found);
    }

    public function testFindById(): void
    {
        $space = new WikiSpace('s', 'S', WikiSpaceOwnerScope::Team, 't');
        $page  = new WikiPage($space, 'p', 'P');
        $em    = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($page);

        $found = (new DoctrineOrmWikiPageRepository($em))->findById($page->getId());

        self::assertSame($page, $found);
    }
}
