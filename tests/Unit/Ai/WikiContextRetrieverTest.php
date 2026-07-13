<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Ai;

use Nowo\WikiBundle\Ai\Exception\WikiAiUnavailableException;
use Nowo\WikiBundle\Ai\NullWikiAiAssistant;
use Nowo\WikiBundle\Ai\WikiContextRetriever;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Service\WikiSearchService;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolverInterface;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use PHPUnit\Framework\TestCase;

use function count;

final class WikiContextRetrieverTest extends TestCase
{
    public function testRetrieveBuildsContextFromSearchHits(): void
    {
        $user     = new TestUser();
        $space    = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page     = new WikiPage($space, 'deploy', 'Deploy');
        $revision = new WikiPageRevision($page, 1, '<p>Production deploy steps</p>', $user);
        $page->setCurrentRevision($revision);

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $search = $this->createSearchService([[$page, 'contentHtml' => '<p>Production deploy steps</p>']]);

        $retriever = new WikiContextRetriever($search, $resolver);
        $result    = $retriever->retrieve($user, 'how to deploy', null, 5, 5000);

        self::assertStringContainsString('Deploy', $result['context']);
        self::assertStringContainsString('eng/deploy', $result['context']);
        self::assertCount(1, $result['sources']);
    }

    public function testNullAssistantThrowsUnavailable(): void
    {
        $assistant = new NullWikiAiAssistant();

        $this->expectException(WikiAiUnavailableException::class);
        $assistant->ask(new TestUser(), 'question');
    }

    public function testRetrieveReturnsEmptyForBlankQuestion(): void
    {
        $retriever = new WikiContextRetriever(
            new WikiSearchService($this->createMock(\Doctrine\ORM\EntityManagerInterface::class)),
            $this->createMock(WikiSpaceAccessResolverInterface::class),
        );

        $result = $retriever->retrieve(new TestUser(), '   ', null, 5, 5000);

        self::assertSame('', $result['context']);
        self::assertSame([], $result['sources']);
    }

    public function testRetrieveUsesScopedSpace(): void
    {
        $user  = new TestUser();
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'deploy', 'Deploy');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>deploy steps</p>', $user));

        $retriever = new WikiContextRetriever(
            $this->createSearchService([[$page, 'contentHtml' => '<p>deploy steps</p>']]),
            $this->createMock(WikiSpaceAccessResolverInterface::class),
        );

        $result = $retriever->retrieve($user, 'deploy question', $space, 3, 5000);

        self::assertNotEmpty($result['sources']);
    }

    public function testRetrieveStopsWhenContextBudgetExceeded(): void
    {
        $user  = new TestUser();
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $pages = [];
        for ($i = 0; $i < 5; ++$i) {
            $page = new WikiPage($space, 'page-' . $i, 'Page ' . $i);
            $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>' . str_repeat('content ', 200) . '</p>', $user));
            $pages[] = [$page, 'contentHtml' => '<p>' . str_repeat('content ', 200) . '</p>'];
        }

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $result = (new WikiContextRetriever($this->createSearchService($pages), $resolver))
            ->retrieve($user, 'content search terms here', null, 5, 600);

        self::assertLessThanOrEqual(5, count($result['sources']));
        self::assertLessThan(600, mb_strlen($result['context']));
    }

    public function testRetrieveUsesExcerptWhenRevisionMissing(): void
    {
        $user  = new TestUser();
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'draft', 'Draft');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $search = $this->createSearchService([[$page, 'contentHtml' => '']]);

        $result = (new WikiContextRetriever($search, $resolver))->retrieve($user, 'draft page', null, 5, 5000);

        self::assertStringContainsString('Draft', $result['context']);
    }

    /**
     * @param list<array{0: WikiPage, contentHtml: string}> $rows
     */
    private function createSearchService(array $rows): WikiSearchService
    {
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getResult')->willReturn($rows);

        $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('innerJoin')->willReturnSelf();
        $qb->method('leftJoin')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        return new WikiSearchService($em);
    }
}
