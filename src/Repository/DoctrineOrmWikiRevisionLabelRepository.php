<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiRevisionLabel;

use function array_map;

/**
 * Doctrine named versions (1.15): `<prefix>_revision_labels`, part of the schema only with `history.timeline`.
 */
final readonly class DoctrineOrmWikiRevisionLabelRepository implements WikiRevisionLabelRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface|WikiEntityManagerProvider $entityManager,
    ) {
    }

    public function save(WikiRevisionLabel $label): void
    {
        $entityManager = $this->entityManager();
        $entityManager->persist($label);
        $entityManager->flush();
    }

    public function findForRevision(WikiPageRevision $revision): ?WikiRevisionLabel
    {
        $label = $this->entityManager()->getRepository(WikiRevisionLabel::class)->findOneBy(['revision' => $revision]);

        return $label instanceof WikiRevisionLabel ? $label : null;
    }

    public function labelsFor(array $revisions): array
    {
        if ($revisions === []) {
            return [];
        }
        /** @var list<array{id: string, label: string}> $rows */
        $rows = $this->entityManager()->createQueryBuilder()
            ->select('IDENTITY(l.revision) AS id', 'l.label AS label')
            ->from(WikiRevisionLabel::class, 'l')
            ->where('l.revision IN (:revisions)')
            ->setParameter('revisions', array_map(static fn (WikiPageRevision $r): string => $r->getId(), $revisions))
            ->getQuery()
            ->getArrayResult();
        $labels = [];
        foreach ($rows as $row) {
            $labels[$row['id']] = $row['label'];
        }

        return $labels;
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->entityManager instanceof WikiEntityManagerProvider ? $this->entityManager->get() : $this->entityManager;
    }
}
