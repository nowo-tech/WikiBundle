<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Provenance;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\User\WikiUserReferenceResolverInterface;

use function array_map;

use const DATE_ATOM;

/**
 * Spec 234: privacy export and erasure of the actor labels (personal data) stored on page revisions. Call it
 * from the host's personal-data exporter / eraser next to the comment erasure, before the user row is deleted
 * (afterwards the revisions no longer name the user: use {@see self::eraseOrphaned()}).
 *
 * Erasure runs as one DQL `UPDATE` on the wiki entity manager (no flush of pending changes); revisions already loaded
 * in that manager keep their in-memory label until reloaded. The channel is not personal data and stays.
 */
final readonly class WikiWriteProvenanceErasure
{
    public function __construct(
        private EntityManagerInterface|WikiEntityManagerProvider $entityManager,
        private ?WikiUserReferenceResolverInterface $users = null,
    ) {
    }

    /**
     * The actor labels recorded on the user's revisions, oldest first.
     *
     * @return list<array{revision_id: string, page_id: string, revision: int, channel: string|null, actor_label: string, created_at: string}>
     */
    public function exportActor(object $user): array
    {
        /** @var list<WikiPageRevision> $revisions */
        $revisions = $this->em()->createQueryBuilder()
            ->select('r')
            ->from(WikiPageRevision::class, 'r')
            ->where('r.author = :user')
            ->andWhere('r.actorLabel IS NOT NULL')
            ->setParameter('user', $this->users?->reference($user) ?? $user)
            ->orderBy('r.createdAt', 'ASC')
            ->addOrderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(static fn (WikiPageRevision $revision): array => [
            'revision_id' => $revision->getId(),
            'page_id'     => $revision->getPage()->getId(),
            'revision'    => $revision->getRevisionNumber(),
            'channel'     => $revision->getChannel(),
            'actor_label' => (string) $revision->getActorLabel(),
            'created_at'  => $revision->getCreatedAt()->format(DATE_ATOM),
        ], $revisions);
    }

    /**
     * Clears the actor label of every revision of the user.
     *
     * @return int the revisions changed
     */
    public function eraseActor(object $user): int
    {
        return (int) $this->em()->createQueryBuilder()
            ->update(WikiPageRevision::class, 'r')
            ->set('r.actorLabel', ':none')
            ->where('r.author = :user')
            ->andWhere('r.actorLabel IS NOT NULL')
            ->setParameter('none', null)
            ->setParameter('user', $this->users?->reference($user) ?? $user)
            ->getQuery()
            ->execute();
    }

    /**
     * Clears the actor label of every revision whose author was deleted (`author_id` set to NULL by the database).
     *
     * @return int the revisions changed
     */
    public function eraseOrphaned(): int
    {
        return (int) $this->em()->createQueryBuilder()
            ->update(WikiPageRevision::class, 'r')
            ->set('r.actorLabel', ':none')
            ->where('r.author IS NULL')
            ->andWhere('r.actorLabel IS NOT NULL')
            ->setParameter('none', null)
            ->getQuery()
            ->execute();
    }

    private function em(): EntityManagerInterface
    {
        return $this->entityManager instanceof WikiEntityManagerProvider ? $this->entityManager->get() : $this->entityManager;
    }
}
