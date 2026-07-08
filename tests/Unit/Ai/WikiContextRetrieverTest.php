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
