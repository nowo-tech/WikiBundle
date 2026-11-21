<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Port\WikiUserRefs;
use Nowo\WikiBundle\Query\WikiArchiveFilter;
use Nowo\WikiBundle\Query\WikiOwnerUsage;
use Nowo\WikiBundle\Query\WikiPageCriteria;
use Nowo\WikiBundle\Query\WikiPageQuery;
use Nowo\WikiBundle\Query\WikiPageQueryInterface;
use Nowo\WikiBundle\Query\WikiPageSearchHit;
use Nowo\WikiBundle\Query\WikiPageSort;
use Nowo\WikiBundle\Query\WikiSpaceCriteria;
use Nowo\WikiBundle\Query\WikiSpaceQuery;
use Nowo\WikiBundle\Query\WikiSpaceQueryInterface;
use Nowo\WikiBundle\Security\WikiPageAccess;
use Nowo\WikiBundle\Security\WikiPageAccessInterface;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\ValueObject\WikiPageSnapshot;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;

use function array_keys;
use function array_map;
use function sprintf;

/**
 * 1.51 (DDD-host checklist B7, B8, B10 — standard host leg): the read ports and the page rule by ids as public services
 * of a standard host kernel (attribute mapping, a Doctrine user class, the application's own space checker), deciding
 * as the entity-typed `WikiSpaceGuard` does. The DDD host leg is `DddHost\DddHostKernelTest`.
 */
final class ApiReadModelTest extends ApiTestCase
{
    public function testTheReadPortsAreServicesOfTheHost(): void
    {
        $container = self::getContainer();
        $spaces    = $container->get(WikiSpaceQueryInterface::class);
        $pages     = $container->get(WikiPageQueryInterface::class);
        self::assertInstanceOf(WikiSpaceQuery::class, $spaces);
        self::assertInstanceOf(WikiPageQuery::class, $pages);
        $plan  = $this->page($this->eng, 'Plan', 'The plan');
        $steps = $this->page($this->eng, 'Steps', sprintf('Follow [[wiki:%s]].', $plan->getId()), $plan);
        $this->page($this->ops, 'Runbook', 'Ops');

        self::assertSame(['Engineering'], array_map(static fn (WikiSpaceSnapshot $space): string => $space->name, $spaces->findSpaces(WikiSpaceCriteria::forOwners(WikiSpaceOwnerScope::Team, ['owner-1']))));
        self::assertSame(2, $spaces->countSpaces(new WikiSpaceCriteria(WikiSpaceOwnerScope::Team, ['owner-1', 'owner-2'], null, WikiArchiveFilter::Active)));
        self::assertEquals(new WikiOwnerUsage('owner-2', 1, 0, 1, 0, 0), $spaces->usage(WikiSpaceOwnerScope::Team, ['owner-2'])['owner-2']);
        self::assertSame(['Plan', 'Steps'], array_map(static fn (WikiPageSnapshot $page): string => $page->title, $pages->findPages(WikiPageCriteria::inSpaces([$this->eng->getId()], WikiArchiveFilter::Any, WikiPageSort::TitleAsc))));
        self::assertSame(['Plan'], array_map(static fn (WikiPageSnapshot $page): string => $page->title, $pages->ancestors([$steps->getId()])[$steps->getId()]));
        self::assertSame([$steps->getId()], array_keys($pages->pageContents([$steps->getId()])));
        self::assertSame(['Steps'], array_map(static fn (WikiPageSnapshot $page): string => $page->title, $pages->backlinks($plan->getId(), WikiUserRefs::fromUser($this->bob))));
        self::assertSame(['Plan'], array_map(static fn (WikiPageSearchHit $hit): string => $hit->page->title, $pages->searchPages([$this->eng->getId(), $this->ops->getId()], 'plan', WikiUserRefs::fromUser($this->bob))->hits));
    }

    public function testThePageRuleByIdsDecidesAsTheGuard(): void
    {
        $container = self::getContainer();
        $access    = $container->get(WikiPageAccessInterface::class);
        $guard     = $container->get(WikiSpaceGuard::class);
        self::assertInstanceOf(WikiPageAccess::class, $access);
        self::assertInstanceOf(WikiSpaceGuard::class, $guard);
        $plan    = $this->page($this->eng, 'Plan', 'The plan');
        $steps   = $this->page($this->eng, 'Steps', 'Steps', $plan);
        $runbook = $this->page($this->ops, 'Runbook', 'Ops');

        $decisions = [];
        foreach (['ana' => $this->ana, 'bob' => $this->bob] as $who => $user) {
            foreach ([$plan, $steps, $runbook] as $page) {
                $entity = $this->wikiEm()->find(WikiPage::class, $page->getId());
                self::assertInstanceOf(WikiPage::class, $entity);
                $space = $this->wikiEm()->find(WikiSpace::class, $entity->getSpace()->getId());
                self::assertInstanceOf(WikiSpace::class, $space);
                $byEntity = $guard->isPageReadable($user, $space, $entity);
                self::assertSame($byEntity, $access->isPageReadable(WikiUserRefs::fromUser($user), $page->getId()), $who . ' ' . $page->getTitle());
                $decisions[$who][] = $byEntity;
            }
        }
        self::assertSame(['ana' => [true, true, true], 'bob' => [true, true, false]], $decisions, 'Bob reads eng only');
        self::assertSame([$plan->getId(), $steps->getId()], $access->readablePageIds(WikiUserRefs::fromUser($this->bob), [$plan->getId(), $runbook->getId(), $steps->getId()]));
    }
}
