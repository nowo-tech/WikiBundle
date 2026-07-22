<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Service\WikiSearchService;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use PHPUnit\Framework\TestCase;

final class WikiSearchServiceTest extends TestCase
{
    public function testEmptyQueryReturnsNoResults(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('createQueryBuilder');

        $space   = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 't1');
        $service = new WikiSearchService($em);

        self::assertSame([], $service->search($space, '   '));
        self::assertSame([], $service->searchAcrossSpaces([$space], ''));
    }

    public function testEmptySpaceListReturnsNoResults(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('createQueryBuilder');

        $service = new WikiSearchService($em);

        self::assertSame([], $service->searchAcrossSpaces([], 'deploy'));
    }

    public function testSearchReturnsHitsWithExcerpt(): void
    {
        $space = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 't1');
        $page  = new WikiPage($space, 'deploy', 'Deploy guide');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>Production deploy steps here</p>', new TestUser()));

        $service = new WikiSearchService($this->entityManager([[$page, 'contentHtml' => '<p>Production deploy steps here</p>']]));
        $results = $service->search($space, 'deploy');

        self::assertCount(1, $results);
        self::assertSame('Deploy guide', $results[0]['page']->getTitle());
        self::assertStringContainsString('deploy', strtolower($results[0]['excerpt']));
    }

    public function testSearchIgnoresInvalidRows(): void
    {
        $space   = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 't1');
        $service = new WikiSearchService($this->entityManager(['invalid', ['not-a-page', 'contentHtml' => 'x']]));

        self::assertSame([], $service->search($space, 'deploy'));
    }

    public function testExcerptFallsBackToTitleWhenContentEmpty(): void
    {
        $space = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 't1');
        $page  = new WikiPage($space, 'title-only', 'Title only page');

        $service = new WikiSearchService($this->entityManager([[$page, 'contentHtml' => '']]));
        $results = $service->search($space, 'title');

        self::assertSame('Title only page', $results[0]['excerpt']);
    }

    public function testSearchReturnsNonArrayResult(): void
    {
        $space   = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 't1');
        $service = new WikiSearchService($this->entityManager(null));

        self::assertSame([], $service->search($space, 'deploy'));
    }

    private function entityManager(mixed $rows): EntityManagerInterface
    {
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn($rows);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('innerJoin')->willReturnSelf();
        $qb->method('leftJoin')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        return $em;
    }
}
