<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
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

    public function testExcerptCentresOnMatchAfterMultibyteText(): void
    {
        $space   = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 't1');
        $page    = new WikiPage($space, 'multibyte', 'Multibyte');
        $content = '<p>' . str_repeat('é', 60) . ' Needle ' . str_repeat('x', 200) . '</p>';

        $service = new WikiSearchService($this->entityManager([[$page, 'contentHtml' => $content]]));
        $results = $service->search($space, 'NEEDLE');

        self::assertStringContainsString('Needle', $results[0]['excerpt']);
        self::assertStringStartsWith('…' . str_repeat('é', 39) . ' Needle', $results[0]['excerpt']);
    }

    public function testExcerptSeparatesBlocksWithSpaces(): void
    {
        $space   = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 't1');
        $page    = new WikiPage($space, 'runbook', 'Runbook');
        $content = '<h2>Overview</h2><p>This runbook&nbsp;covers <strong>deploys</strong>.</p><ul><li>One</li><li>Two<br>lines</li></ul><table><tr><td>A</td><td>B</td></tr></table>';

        $service = new WikiSearchService($this->entityManager([[$page, 'contentHtml' => $content]]));
        $results = $service->search($space, 'runbook');

        self::assertSame('Overview This runbook covers deploys. One Two lines A B', $results[0]['excerpt']);
    }

    /**
     * 1.31 fix: a leading heading equal to the title is not repeated in the excerpt (the row shows the title).
     */
    public function testExcerptLeavesOutALeadingTitleHeading(): void
    {
        $space = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 't1');
        $page  = new WikiPage($space, 'showcase', 'Content  showcase');
        $other = new WikiPage($space, 'only', 'Only title');
        $prose = new WikiPage($space, 'prose', 'Deploy');
        $sub   = new WikiPage($space, 'sub', 'Steps');

        $service = new WikiSearchService($this->entityManager([
            [$page, 'contentHtml' => '<h1 id="nowo-h-x">Content <em>showcase</em></h1><p>This page shows every block.</p>'],
            [$other, 'contentHtml' => '<h2>only TITLE</h2>'],
            // Not a heading: kept as written.
            [$prose, 'contentHtml' => '<p>Deploy the service first.</p>'],
            // A heading that is not the title: kept.
            [$sub, 'contentHtml' => '<h2>Overview</h2><p>Steps follow.</p>'],
            // The plain-text column with the HTML: the HTML decides, the text is trimmed.
            [$sub, 'contentHtml' => '<h3>Steps</h3><p>Then go.</p>', 'contentText' => 'Steps Then go.'],
        ]));
        $results = $service->search($space, 'e');

        self::assertSame('This page shows every block.', $results[0]['excerpt']);
        self::assertSame('', $results[1]['excerpt']);
        self::assertSame('Deploy the service first.', $results[2]['excerpt']);
        self::assertSame('Overview Steps follow.', $results[3]['excerpt']);
        self::assertSame('Then go.', $results[4]['excerpt']);
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

    public function testSearchResolvesEntityManagerThroughProviderAndRefreshesResults(): void
    {
        $space    = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 't1');
        $page     = new WikiPage($space, 'deploy', 'Deploy guide');
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::once())->method('getManager')->with('default')->willReturn($this->entityManager([[$page, 'contentHtml' => '']], true));

        $service = new WikiSearchService(new WikiEntityManagerProvider($registry));

        self::assertCount(1, $service->search($space, 'deploy'));
    }

    private function entityManager(mixed $rows, bool $expectRefreshHint = false): EntityManagerInterface
    {
        $query = $this->createMock(Query::class);
        if ($expectRefreshHint) {
            $query->expects(self::once())->method('setHint')->with(Query::HINT_REFRESH, true)->willReturnSelf();
        } else {
            $query->method('setHint')->willReturnSelf();
        }
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
        $em->method('isOpen')->willReturn(true);

        return $em;
    }
}
