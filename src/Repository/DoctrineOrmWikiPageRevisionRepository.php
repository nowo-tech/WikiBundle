<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use SortDirection;

use function max;

/**
 * Doctrine ORM implementation of the wiki page revision repository.
 */
final readonly class DoctrineOrmWikiPageRevisionRepository implements WikiPageRevisionRepositoryInterface
{
    /**
     * Creates the repository.
     *
     * @param EntityManagerInterface|WikiEntityManagerProvider $entityManager entity manager, or a provider resolving the configured one lazily
     */
    public function __construct(
        private EntityManagerInterface|WikiEntityManagerProvider $entityManager,
    ) {
    }

    /**
     * Persists the revision and flushes it immediately.
     *
     * @param WikiPageRevision $revision revision to save
     */
    public function save(WikiPageRevision $revision): void
    {
        $entityManager = $this->entityManager();
        $entityManager->persist($revision);
        $entityManager->flush();
    }

    /**
     * Finds a revision by its identifier.
     *
     * @param string $id revision UUID
     *
     * @return WikiPageRevision|null the revision, or null when none exists
     */
    public function findById(string $id): ?WikiPageRevision
    {
        return $this->entityManager()->find(WikiPageRevision::class, $id);
    }

    /**
     * Returns the revisions of a page, newest first.
     *
     * @param WikiPage $page page to read
     * @param int|null $limit maximum number of revisions (null = all)
     * @param int $offset number of newest revisions to skip (negative values are treated as 0)
     *
     * @return list<WikiPageRevision>
     */
    public function findByPage(WikiPage $page, ?int $limit = null, int $offset = 0): array
    {
        /* @var list<WikiPageRevision> */
        return $this->entityManager()->createQueryBuilder()
            ->select('r')
            ->from(WikiPageRevision::class, 'r')
            ->where('r.page = :page')
            ->setParameter('page', $page)
            ->orderBy('r.revisionNumber', SortDirection::Descending)
            ->setFirstResult(max(0, $offset))
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Finds a revision of a page by its revision number.
     *
     * @param WikiPage $page page the revision belongs to
     * @param int $number revision number
     *
     * @return WikiPageRevision|null the revision, or null when none matches
     */
    public function findByPageAndNumber(WikiPage $page, int $number): ?WikiPageRevision
    {
        return $this->entityManager()->getRepository(WikiPageRevision::class)->findOneBy([
            'page'           => $page,
            'revisionNumber' => $number,
        ]);
    }

    /**
     * Returns the number to use for the next revision of a page (highest existing number plus one, 1 for a page without revisions).
     *
     * @param WikiPage $page page to inspect
     *
     * @return int next revision number
     */
    public function getNextRevisionNumber(WikiPage $page): int
    {
        $max = $this->entityManager()->createQueryBuilder()
            ->select('MAX(r.revisionNumber)')
            ->from(WikiPageRevision::class, 'r')
            ->where('r.page = :page')
            ->setParameter('page', $page)
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) $max) + 1;
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->entityManager instanceof WikiEntityManagerProvider ? $this->entityManager->get() : $this->entityManager;
    }
}
