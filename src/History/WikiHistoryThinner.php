<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\History;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Doctrine\WikiSavepoint;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiRevisionLabel;
use Nowo\WikiBundle\User\WikiUserFields;
use Stringable;

use function array_chunk;
use function array_fill_keys;
use function count;
use function sprintf;

/**
 * Revision retention (1.15, D-W6, `wiki:history:thin`): revisions older than `history.retention_days` are thinned to
 * the newest one of each session and day. Kept forever: every named version, the page's current revision and its
 * newest revision. A session is what the timeline shows: consecutive saves by the same author less than
 * `history.session_minutes` apart; here it also ends at midnight (the day of the stored time), so at least one revision per day and author survives.
 *
 * Revision numbers are never reused (the next number is the highest plus one), so a thinned history has gaps.
 */
final readonly class WikiHistoryThinner
{
    private const DELETE_CHUNK = 500;

    public function __construct(
        private EntityManagerInterface|WikiEntityManagerProvider $entityManager,
        private int $retentionDays = 30,
        private int $sessionMinutes = 30,
    ) {
    }

    public function retentionDays(): int
    {
        return $this->retentionDays;
    }

    /**
     * @return array{pages: int, revisions: int} pages thinned and revisions removed (or that would be, with $dryRun)
     */
    public function thin(DateTimeImmutable $now, bool $dryRun = false): array
    {
        $em     = $this->em();
        $cutoff = $now->modify(sprintf('-%d days', $this->retentionDays));
        /** @var list<string> $pageIds */
        $pageIds = $em->createQueryBuilder()
            ->select('DISTINCT IDENTITY(r.page)')
            ->from(WikiPageRevision::class, 'r')
            ->where('r.createdAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()->getSingleColumnResult();

        $pages   = 0;
        $removed = 0;
        foreach ($pageIds as $pageId) {
            $ids = $this->thinnable($pageId, $cutoff);
            if ($ids === []) {
                continue;
            }
            ++$pages;
            $removed += count($ids);
            if ($dryRun) {
                continue;
            }
            // 1.33.0: in a savepoint inside the application's transaction (private entity manager only).
            WikiSavepoint::write($em, static function () use ($em, $ids): void {
                foreach (array_chunk($ids, self::DELETE_CHUNK) as $chunk) {
                    $em->createQueryBuilder()
                        ->delete(WikiPageRevision::class, 'r')
                        ->where('r.id IN (:ids)')
                        ->setParameter('ids', $chunk)
                        ->getQuery()->execute();
                }
            });
        }

        return ['pages' => $pages, 'revisions' => $removed];
    }

    /**
     * Ids of the revisions of a page that the retention removes.
     *
     * @return list<string>
     */
    private function thinnable(string $pageId, DateTimeImmutable $cutoff): array
    {
        $em = $this->em();
        /** @var list<array{id: string, number: int, createdAt: DateTimeInterface, author: int|string|Stringable|null}> $old */
        $old = $em->createQueryBuilder()
            ->select('r.id AS id', 'r.revisionNumber AS number', 'r.createdAt AS createdAt', WikiUserFields::identity($em, WikiPageRevision::class, 'r', 'author') . ' AS author')
            ->from(WikiPageRevision::class, 'r')
            ->where('r.page = :page')->andWhere('r.createdAt < :cutoff')
            ->setParameter('page', $pageId)->setParameter('cutoff', $cutoff)
            ->orderBy('r.revisionNumber', 'DESC')
            ->getQuery()->getArrayResult();
        /** @var list<string> $namedIds */
        $namedIds = $em->createQueryBuilder()
            ->select('IDENTITY(l.revision)')
            ->from(WikiRevisionLabel::class, 'l')
            ->join('l.revision', 'r')
            ->where('r.page = :page')->setParameter('page', $pageId)
            ->getQuery()->getSingleColumnResult();
        /** @var list<array{current: string|null, newest: int|string|null}> $page */
        $page = $em->createQueryBuilder()
            ->select('IDENTITY(p.currentRevision) AS current', '(SELECT MAX(n.revisionNumber) FROM ' . WikiPageRevision::class . ' n WHERE n.page = p) AS newest')
            ->from(WikiPage::class, 'p')
            ->where('p.id = :page')->setParameter('page', $pageId)
            ->getQuery()->getArrayResult();

        $named  = array_fill_keys($namedIds, true);
        $keep   = $named;
        $newest = (int) ($page[0]['newest'] ?? 0);
        if (($page[0]['current'] ?? null) !== null) {
            $keep[(string) $page[0]['current']] = true;
        }

        $ids  = [];
        $prev = null;
        foreach ($old as $row) {
            $kept = isset($keep[$row['id']]) || $row['number'] === $newest || !$prev || $this->breaks($prev, $row, $named);
            if (!$kept) {
                $ids[] = $row['id'];
            }
            $prev = $row;
        }

        return $ids;
    }

    /**
     * Whether $older starts a new session after $newer (both old revisions of one page, newest first).
     *
     * 1.26: a revision whose author was deleted (null) is a session of its own: nothing tells two former people apart,
     * so none of their revisions is thinned as an older save of someone else's session.
     *
     * @param array{id: string, number: int, createdAt: DateTimeInterface, author: int|string|Stringable|null} $newer
     * @param array{id: string, number: int, createdAt: DateTimeInterface, author: int|string|Stringable|null} $older
     * @param array<string, true> $named ids of the named versions (a session of their own)
     */
    private function breaks(array $newer, array $older, array $named): bool
    {
        return isset($named[$newer['id']])
            || $newer['author'] === null || $older['author'] === null
            || (string) $newer['author'] !== (string) $older['author']
            || $newer['createdAt']->format('Y-m-d') !== $older['createdAt']->format('Y-m-d')
            || $newer['createdAt']->getTimestamp() - $older['createdAt']->getTimestamp() > $this->sessionMinutes * 60;
    }

    private function em(): EntityManagerInterface
    {
        return $this->entityManager instanceof WikiEntityManagerProvider ? $this->entityManager->get() : $this->entityManager;
    }
}
