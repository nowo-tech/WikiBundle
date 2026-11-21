<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Doctrine\WikiFlusher;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageLink;
use SortDirection;

use function array_filter;
use function array_map;
use function array_values;
use function in_array;

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
        WikiFlusher::flush($entityManager, $this->entityManager);
    }

    public function remove(WikiPageLink $link): void
    {
        $entityManager = $this->entityManager();
        // @igor-ignore - Doctrine unit of work; the entity manager is reset by Doctrine between requests.
        $entityManager->remove($link);
        WikiFlusher::flush($entityManager, $this->entityManager);
    }

    public function findOne(WikiPage $page, string $objectType, string $objectId): ?WikiPageLink
    {
        /** @var list<WikiPageLink> $links */
        $links = $this->entityManager()->createQueryBuilder()
            ->select('l')
            ->from(WikiPageLink::class, 'l')
            ->where('l.page = :page')
            ->andWhere('l.objectType = :type')
            ->andWhere('l.objectId = :id')
            ->setParameter('page', $page)
            ->setParameter('type', $objectType)
            ->setParameter('id', $objectId)
            ->getQuery()
            ->getResult();
        // 1.37 (house rule LQ): exact ids only, whatever the column's collation (`ABC` is not `abc`).
        foreach ($links as $link) {
            if ($link->getObjectType() === $objectType && $link->getObjectId() === $objectId) {
                return $link;
            }
        }

        return null;
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

        /** @var list<WikiPageLink> $links */
        $links = $this->entityManager()->createQueryBuilder()
            ->select('l', 'p', 's')
            ->from(WikiPageLink::class, 'l')
            ->innerJoin('l.page', 'p')
            ->innerJoin('p.space', 's')
            ->where('l.objectType = :type')
            ->andWhere('l.objectId IN (:ids)')
            ->andWhere('p.archivedAt IS NULL')
            ->andWhere('s.archivedAt IS NULL') // 1.50: no page of an archived space
            ->setParameter('type', $objectType)
            ->setParameter('ids', $objectIds)
            ->orderBy('l.createdAt', SortDirection::Descending)
            ->getQuery()
            ->getResult();

        // 1.37 (house rule LQ): exact ids only, whatever the column's collation.
        $exact = array_map(strval(...), $objectIds);

        return array_values(array_filter($links, static fn (WikiPageLink $link): bool => $link->getObjectType() === $objectType && in_array($link->getObjectId(), $exact, true)));
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
