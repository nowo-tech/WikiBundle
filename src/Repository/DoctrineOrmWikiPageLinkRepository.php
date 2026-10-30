<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageLink;
use SortDirection;

/**
 * Doctrine ORM implementation of {@see WikiPageLinkRepositoryInterface} (1.9).
 */
final readonly class DoctrineOrmWikiPageLinkRepository implements WikiPageLinkRepositoryInterface
{
    /**
     * @param EntityManagerInterface|WikiEntityManagerProvider $entityManager entity manager or its provider
     */
    public function __construct(
        private EntityManagerInterface|WikiEntityManagerProvider $entityManager,
    ) {
    }

    public function save(WikiPageLink $link): void
    {
        $entityManager = $this->entityManager();
        $entityManager->persist($link);
        $entityManager->flush();
    }

    public function remove(WikiPageLink $link): void
    {
        $entityManager = $this->entityManager();
        // @igor-ignore - Doctrine unit of work; the entity manager is reset by Doctrine between requests.
        $entityManager->remove($link);
        $entityManager->flush();
    }

    public function findOne(WikiPage $page, string $objectType, string $objectId): ?WikiPageLink
    {
        /** @var WikiPageLink|null $link */
        $link = $this->entityManager()->createQueryBuilder()
            ->select('l')
            ->from(WikiPageLink::class, 'l')
            ->where('l.page = :page')
            ->andWhere('l.objectType = :type')
            ->andWhere('l.objectId = :id')
            ->setParameter('page', $page)
            ->setParameter('type', $objectType)
            ->setParameter('id', $objectId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $link;
    }

    public function findByHostObject(string $objectType, string $objectId): array
    {
        return $this->findByHostObjects($objectType, [$objectId]);
    }

    public function findByHostObjects(string $objectType, array $objectIds): array
    {
        if ($objectIds === []) {
            return [];
        }

        /* @var list<WikiPageLink> */
        return $this->entityManager()->createQueryBuilder()
            ->select('l', 'p', 's')
            ->from(WikiPageLink::class, 'l')
            ->innerJoin('l.page', 'p')
            ->innerJoin('p.space', 's')
            ->where('l.objectType = :type')
            ->andWhere('l.objectId IN (:ids)')
            ->andWhere('p.archivedAt IS NULL')
            ->setParameter('type', $objectType)
            ->setParameter('ids', $objectIds)
            ->orderBy('l.createdAt', SortDirection::Descending)
            ->getQuery()
            ->getResult();
    }

    public function findByPage(WikiPage $page): array
    {
        /* @var list<WikiPageLink> */
        return $this->entityManager()->createQueryBuilder()
            ->select('l')
            ->from(WikiPageLink::class, 'l')
            ->where('l.page = :page')
            ->setParameter('page', $page)
            ->orderBy('l.createdAt', SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->entityManager instanceof WikiEntityManagerProvider ? $this->entityManager->get() : $this->entityManager;
    }
}
