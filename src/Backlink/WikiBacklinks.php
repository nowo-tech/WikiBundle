<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Backlink;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageReference;
use Nowo\WikiBundle\QuickAccess\WikiViewerScope;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;

use function count;
use function min;
use function strcmp;
use function strnatcasecmp;
use function usort;

/**
 * 1.45 (`backlinks.enabled`): the "Linked from" list of a page — the pages whose current revision links it, from the
 * backlink index ({@see WikiPageReferenceIndex}), **only those the viewer may read**:
 *
 * 1. in SQL, before anything is counted or cut: the index rows of the target, joined to their source pages, narrowed to
 *    the spaces of this view's {@see WikiViewerScope} (the current tenant's spaces the viewer may read now: role gate,
 *    tenant / team resolver, View on the space) and to live pages (archived and trashed sources are never read);
 * 2. then the page rule (View on the source and every ancestor, {@see WikiViewerScope::canRead()}), which is the host's PHP
 *    checker, on a keyset scan of those rows in id order, at most `backlinks.candidates` rows, until `backlinks.limit`
 *    readable sources were found.
 *
 * A source the viewer may not read is never listed or counted. Residual (as the search, audit TI-L2): past the
 * candidate budget, hidden sources in the viewer's own spaces use up scan rows. Sorted by title for display.
 * Stateless (worker mode).
 */
final readonly class WikiBacklinks
{
    /** Rows read per database round. */
    private const BATCH = 100;

    public function __construct(
        private WikiTransactionRunner $runner,
        private int $limit = 20,
        private int $candidates = 200,
    ) {
    }

    /**
     * @return list<WikiPage> the readable sources, by title
     */
    public function sources(WikiViewerScope $scope, WikiPage $target): array
    {
        $spaceIds = $scope->spaceIds();
        if ($spaceIds === []) {
            return [];
        }
        $readable = [];
        $after    = '';
        $read     = 0;
        while ($read < $this->candidates && count($readable) < $this->limit) {
            $batch = min(self::BATCH, $this->candidates - $read);
            /** @var list<WikiPage> $rows */
            $rows = $this->runner->manager()->createQueryBuilder()
                ->select('p')
                ->from(WikiPage::class, 'p')
                ->join(WikiPageReference::class, 'r', 'WITH', 'r.source = p')
                ->where('r.targetPageId = :target')
                ->andWhere('IDENTITY(p.space) IN (:spaces)')
                ->andWhere('p.archivedAt IS NULL')
                ->andWhere('p.id <> :target')
                ->andWhere('p.id > :after')
                ->orderBy('p.id', 'ASC')
                ->setParameter('target', $target->getId())
                ->setParameter('spaces', $spaceIds)
                ->setParameter('after', $after)
                ->setMaxResults($batch)
                ->getQuery()
                ->getResult();
            $read += count($rows);
            foreach ($rows as $page) {
                $after = $page->getId();
                if (count($readable) < $this->limit && $scope->canRead($page)) {
                    $readable[] = $page;
                }
            }
            if (count($rows) < $batch) {
                break;
            }
        }
        usort($readable, static fn (WikiPage $a, WikiPage $b): int => strnatcasecmp($a->getTitle(), $b->getTitle()) ?: strcmp($a->getId(), $b->getId()));

        return $readable;
    }
}
