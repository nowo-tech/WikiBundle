<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRevisionRepository;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use PHPUnit\Framework\TestCase;

final class DoctrineOrmWikiPageRevisionRepositoryTest extends TestCase
{
    public function testSavePersistsRevision(): void
    {
        $page     = new WikiPage(new WikiSpace('s', 'S', WikiSpaceOwnerScope::Team, 't'), 'p', 'P');
        $revision = new WikiPageRevision($page, 1, '<p>x</p>', new TestUser());

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with($revision);
        $em->expects(self::once())->method('flush');

        (new DoctrineOrmWikiPageRevisionRepository($em))->save($revision);
    }

    public function testFindByPageOrdersDescending(): void
    {
        $page     = new WikiPage(new WikiSpace('s', 'S', WikiSpaceOwnerScope::Team, 't'), 'p', 'P');
        $revision = new WikiPageRevision($page, 1, '<p>x</p>', new TestUser());

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([$revision]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        $found = (new DoctrineOrmWikiPageRevisionRepository($em))->findByPage($page);

        self::assertSame([$revision], $found);
    }

    public function testGetNextRevisionNumber(): void
    {
        $page = new WikiPage(new WikiSpace('s', 'S', WikiSpaceOwnerScope::Team, 't'), 'p', 'P');

        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn(3);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        self::assertSame(4, (new DoctrineOrmWikiPageRevisionRepository($em))->getNextRevisionNumber($page));
    }

    public function testFindByPageAndNumber(): void
    {
        $page     = new WikiPage(new WikiSpace('s', 'S', WikiSpaceOwnerScope::Team, 't'), 'p', 'P');
        $revision = new WikiPageRevision($page, 2, '<p>x</p>', new TestUser());
        $repo     = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($revision);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $found = (new DoctrineOrmWikiPageRevisionRepository($em))->findByPageAndNumber($page, 2);

        self::assertSame($revision, $found);
    }

    public function testFindById(): void
    {
        $page     = new WikiPage(new WikiSpace('s', 'S', WikiSpaceOwnerScope::Team, 't'), 'p', 'P');
        $revision = new WikiPageRevision($page, 1, '<p>x</p>', new TestUser());
        $em       = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($revision);

        $found = (new DoctrineOrmWikiPageRevisionRepository($em))->findById($revision->getId());

        self::assertSame($revision, $found);
    }
}
