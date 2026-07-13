<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Ai\Tool;

use Nowo\WikiBundle\Ai\Tool\WikiKnowledgeSearchTool;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Service\WikiSearchService;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolverInterface;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class WikiKnowledgeSearchToolTest extends TestCase
{
    public function testReturnsErrorWhenUnauthenticated(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        $json = (new WikiKnowledgeSearchTool(
            $security,
            new WikiSearchService($this->createMock(\Doctrine\ORM\EntityManagerInterface::class)),
            $this->createMock(WikiSpaceAccessResolverInterface::class),
        ))('deploy');

        self::assertStringContainsString('Authentication required', $json);
    }

    public function testReturnsEmptyResultsForBlankQuery(): void
    {
        $json = $this->tool([])('   ');

        self::assertStringContainsString('"results":[]', str_replace(' ', '', $json));
    }

    public function testSearchesAcrossAccessibleSpaces(): void
    {
        $user  = new TestUser();
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'deploy', 'Deploy guide');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>deploy runbook</p>', $user));

        $json = $this->tool([$space], [[$page, 'contentHtml' => '<p>deploy runbook</p>']])('deploy');

        self::assertStringContainsString('"space":"eng"', $json);
        self::assertStringContainsString('"page":"deploy"', $json);
        self::assertStringContainsString('Deploy guide', $json);
    }

    public function testSearchesWithinResolvedSpace(): void
    {
        $user  = new TestUser();
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $other = new WikiSpace('ops', 'Ops', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'deploy', 'Deploy');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>deploy</p>', $user));

        $json = $this->tool([$space, $other], [[$page, 'contentHtml' => '<p>deploy</p>']])('deploy', 'eng');

        self::assertStringContainsString('"space":"eng"', $json);
    }

    public function testResolveSpaceReturnsNullForUnknownSlug(): void
    {
        new TestUser();
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $json = $this->tool([$space], [])('deploy', 'missing');

        self::assertStringContainsString('"results":[]', str_replace(' ', '', $json));
    }

    public function testClampsLimit(): void
    {
        $user  = new TestUser();
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getResult')->willReturn([]);

        $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('innerJoin')->willReturnSelf();
        $qb->method('leftJoin')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('setMaxResults')->with(25)->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        (new WikiKnowledgeSearchTool($security, new WikiSearchService($em), $resolver))('deploy', null, 100);

        self::assertTrue(true);
    }

    /**
     * @param list<WikiSpace> $spaces
     * @param list<array{0: WikiPage, contentHtml: string}> $rows
     */
    private function tool(array $spaces, array $rows = []): WikiKnowledgeSearchTool
    {
        $user = new TestUser();

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

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

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn($spaces);

        return new WikiKnowledgeSearchTool($security, new WikiSearchService($em), $resolver);
    }
}
