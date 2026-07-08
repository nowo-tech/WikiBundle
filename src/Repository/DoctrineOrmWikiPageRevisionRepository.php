<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;

final readonly class DoctrineOrmWikiPageRevisionRepository implements WikiPageRevisionRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(WikiPageRevision $revision): void
    {
        $this->entityManager->persist($revision);
        $this->entityManager->flush();
    }

    public function findById(string $id): ?WikiPageRevision
    {
        return $this->entityManager->find(WikiPageRevision::class, $id);
    }

    public function findByPage(WikiPage $page): array
    {
        /* @var list<WikiPageRevision> */
        return $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from(WikiPageRevision::class, 'r')
            ->where('r.page = :page')
            ->setParameter('page', $page)
            ->orderBy('r.revisionNumber', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByPageAndNumber(WikiPage $page, int $number): ?WikiPageRevision
    {
        return $this->entityManager->getRepository(WikiPageRevision::class)->findOneBy([
            'page'           => $page,
            'revisionNumber' => $number,
        ]);
    }

    public function getNextRevisionNumber(WikiPage $page): int
    {
        $max = $this->entityManager->createQueryBuilder()
            ->select('MAX(r.revisionNumber)')
            ->from(WikiPageRevision::class, 'r')
            ->where('r.page = :page')
            ->setParameter('page', $page)
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) $max) + 1;
    }
}
