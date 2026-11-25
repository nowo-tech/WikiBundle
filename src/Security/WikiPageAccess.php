<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Security;

use Doctrine\ORM\Query;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Port\Internal\WikiSpaceAccessCheckerInterface;
use Nowo\WikiBundle\Port\WikiUserRefs;
use Nowo\WikiBundle\Query\WikiQueryIds;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;
use Nowo\WikiBundle\ValueObject\WikiUserRef;

use function array_flip;
use function array_intersect_key;
use function array_keys;

/**
 * 1.51: {@see WikiPageAccessInterface} over {@see WikiPageVisibility} and the space checker {@see WikiSpaceGuard} asks.
 * Stateless: the decisions of one call are a local memo.
 */
final readonly class WikiPageAccess implements WikiPageAccessInterface
{
    /**
     * @param WikiTransactionRunner $runner gives the wiki's entity manager
     * @param WikiSpaceAccessCheckerInterface $checker the space checker of {@see WikiSpaceGuard}
     */
    public function __construct(
        private WikiTransactionRunner $runner,
        private WikiSpaceAccessCheckerInterface $checker,
    ) {
    }

    public function isPageReadable(WikiUserRef $user, string $pageId): bool
    {
        return $this->readablePageIds($user, [$pageId]) === [$pageId];
    }

    public function readablePageIds(WikiUserRef $user, array $pageIds): array
    {
        $pageIds = WikiQueryIds::ids($pageIds);
        $pages   = $this->load($pageIds, true);
        // The ancestors, one query per tree level, so the rule below reads no page lazily.
        $loaded   = $pages;
        $frontier = $pages;
        while ($frontier !== []) {
            $parents = [];
            foreach ($frontier as $page) {
                $parentId = $page->getParent()?->getId();
                if ($parentId !== null && !isset($loaded[$parentId])) {
                    $parents[$parentId] = true;
                }
            }
            $frontier = $this->load(array_keys($parents), false);
            $loaded += $frontier;
        }

        $viewer    = WikiUserRefs::securityUser($user);
        $decisions = [];
        $readable  = [];
        foreach ($pageIds as $id) {
            $page = $pages[$id] ?? null;
            if ($page instanceof WikiPage && WikiPageVisibility::isReadable($this->checker, $viewer, $page->getSpace(), $page, $decisions)) {
                $readable[] = $id;
            }
        }

        return $readable;
    }

    /**
     * The pages with exactly these ids, read fresh (another worker's change is never hidden by the identity map).
     *
     * @param list<string> $ids
     *
     * @return array<string, WikiPage> by id
     */
    private function load(array $ids, bool $withSpace): array
    {
        $pages = [];
        foreach (WikiQueryIds::chunks($ids) as $chunk) {
            $builder = $this->runner->manager()->createQueryBuilder()
                ->select('p')
                ->from(WikiPage::class, 'p')
                ->where('p.id IN (:ids)')
                ->setParameter('ids', $chunk);
            if ($withSpace) {
                $builder->addSelect('s')->innerJoin('p.space', 's');
            }
            /** @var list<WikiPage> $rows */
            $rows = $builder->getQuery()->setHint(Query::HINT_REFRESH, true)->getResult();
            foreach ($rows as $page) {
                $pages[$page->getId()] = $page;
            }
        }

        // Exactly the ids asked (house rule LQ: a folding collation never answers for another id).
        return array_intersect_key($pages, array_flip($ids));
    }
}
