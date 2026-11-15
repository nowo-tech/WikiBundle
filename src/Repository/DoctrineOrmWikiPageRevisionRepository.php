<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Doctrine\WikiFlusher;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Provenance\WikiWriteProvenance;
use Nowo\WikiBundle\User\WikiUserFields;
use Nowo\WikiBundle\ValueObject\WikiStoredUserRef;
use SortDirection;

use function is_int;
use function is_string;
use function max;

/**
 * Doctrine ORM implementation of the wiki page revision repository.
 */
final readonly class DoctrineOrmWikiPageRevisionRepository implements WikiPageRevisionRepositoryInterface, WikiPageProvenanceFinderInterface
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
        WikiFlusher::flush($entityManager);
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

    /**
     * Spec 234: two queries whatever the number of pages — the oldest stored revision's author per page, and the
     * current revision's channel, actor label and author. 1.36.0: the author is read through
     * {@see WikiUserFields::identity()}, so the stored id comes back in both modes (a relation to `user_class`, or the
     * id column of `database.user_reference: id`).
     *
     * @param list<string> $pageIds page ids
     *
     * @return array<string, WikiPageProvenance> by page id
     */
    public function pageProvenance(array $pageIds): array
    {
        if ($pageIds === []) {
            return [];
        }
        $entityManager = $this->entityManager();
        $author        = WikiUserFields::identity($entityManager, WikiPageRevision::class, 'r', 'author');
        /** @var list<array{pageId: string, authorId: mixed}> $creators */
        $creators = $entityManager->createQueryBuilder()
            ->select('IDENTITY(r.page) AS pageId', $author . ' AS authorId')
            ->from(WikiPageRevision::class, 'r')
            ->where('IDENTITY(r.page) IN (:ids)')
            ->andWhere('r.revisionNumber = (SELECT MIN(f.revisionNumber) FROM ' . WikiPageRevision::class . ' f WHERE f.page = r.page)')
            ->setParameter('ids', $pageIds)
            ->getQuery()
            ->getArrayResult();
        /** @var list<array{pageId: string, channel: string|null, actorLabel: string|null, authorId: mixed}> $currents */
        $currents = $entityManager->createQueryBuilder()
            ->select('p.id AS pageId', 'r.channel AS channel', 'r.actorLabel AS actorLabel', $author . ' AS authorId')
            ->from(WikiPage::class, 'p')
            ->innerJoin('p.currentRevision', 'r')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $pageIds)
            ->getQuery()
            ->getArrayResult();

        $creatorIds = [];
        foreach ($creators as $row) {
            $creatorIds[(string) $row['pageId']] = $this->authorId($row['authorId']);
        }
        $provenance = [];
        foreach ($currents as $row) {
            $pageId              = (string) $row['pageId'];
            $provenance[$pageId] = new WikiPageProvenance(
                $creatorIds[$pageId] ?? null,
                WikiWriteProvenance::channelOrNull($row['channel']),
                $row['actorLabel'],
                $this->authorId($row['authorId']),
            );
        }

        return $provenance;
    }

    /**
     * The stored author id of a scalar row: the id of a {@see WikiStoredUserRef} (id mode, converted by its column
     * type), the related id as a string (entity mode), null for none.
     */
    private function authorId(mixed $value): ?string
    {
        return match (true) {
            $value instanceof WikiStoredUserRef => $value->id,
            is_int($value), is_string($value)   => (string) $value,
            default                             => null,
        };
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->entityManager instanceof WikiEntityManagerProvider ? $this->entityManager->get() : $this->entityManager;
    }
}
