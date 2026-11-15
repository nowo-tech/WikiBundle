<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Provenance;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Doctrine\WikiSavepoint;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Port\WikiUserRefs;
use Nowo\WikiBundle\User\WikiUserFields;
use Nowo\WikiBundle\User\WikiUserReferenceResolverInterface;

use function array_chunk;
use function array_filter;
use function array_map;
use function array_values;
use function is_object;
use function is_string;

use const DATE_ATOM;

/**
 * Spec 234: privacy export and erasure of the actor labels (personal data) stored on page revisions. Call it
 * from the host's personal-data exporter / eraser next to the comment erasure, before the user row is deleted
 * (afterwards the revisions no longer name the user: use {@see self::eraseOrphaned()}).
 *
 * Erasure runs as one DQL `UPDATE` on the wiki entity manager (no flush of pending changes); revisions already loaded
 * in that manager keep their in-memory label until reloaded. The channel is not personal data and stays. 1.36.0: with
 * `database.user_reference: id` the user is matched by their exact id (rows re-checked with `===`, cleared by primary
 * key); with the private entity manager inside the application's transaction the statements run in a savepoint.
 */
final readonly class WikiWriteProvenanceErasure
{
    /** Revisions cleared per statement in the id mode. */
    private const CHUNK = 500;

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
        $em        = $this->em();
        $parameter = $this->userParameter($em, $user);
        if ($parameter === null) {
            return [];
        }
        /** @var list<WikiPageRevision> $revisions */
        $revisions = $em->createQueryBuilder()
            ->select('r')
            ->from(WikiPageRevision::class, 'r')
            ->where('r.author = :user')
            ->andWhere('r.actorLabel IS NOT NULL')
            ->setParameter('user', $parameter)
            ->orderBy('r.createdAt', 'ASC')
            ->addOrderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult();
        if (is_string($parameter)) {
            // 1.36.0 (id mode, REQ-SEC-006 item 2): re-checked byte for byte after the SQL (a folding collation).
            $revisions = array_values(array_filter($revisions, static fn (WikiPageRevision $revision): bool => WikiUserFields::is($revision->getAuthor(), $parameter)));
        }

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
        $em        = $this->em();
        $parameter = $this->userParameter($em, $user);
        if ($parameter === null) {
            return 0;
        }
        if (!is_string($parameter)) {
            return (int) WikiSavepoint::write($em, static fn (): mixed => $em->createQueryBuilder()
                ->update(WikiPageRevision::class, 'r')
                ->set('r.actorLabel', ':none')
                ->where('r.author = :user')
                ->andWhere('r.actorLabel IS NOT NULL')
                ->setParameter('none', null)
                ->setParameter('user', $parameter)
                ->getQuery()
                ->execute());
        }

        // 1.36.0 (id mode, REQ-SEC-006 item 2): the exact rows are selected first and cleared by primary key, so a
        // look-alike id on a folding collation is never touched.
        $ids = [];
        foreach ($em->createQueryBuilder()->select('r.id AS id', 'r.author AS ref')->from(WikiPageRevision::class, 'r')
            ->where('r.author = :user')->andWhere('r.actorLabel IS NOT NULL')->setParameter('user', $parameter)
            ->getQuery()->getArrayResult() as $row) {
            if (WikiUserFields::is(is_object($row['ref']) ? $row['ref'] : null, $parameter)) {
                $ids[] = (string) $row['id'];
            }
        }

        return WikiSavepoint::write($em, static function () use ($em, $ids): int {
            $changed = 0;
            foreach (array_chunk($ids, self::CHUNK) as $chunk) {
                $changed += (int) $em->createQueryBuilder()
                    ->update(WikiPageRevision::class, 'r')
                    ->set('r.actorLabel', ':none')
                    ->where('r.id IN (:ids)')
                    ->setParameter('none', null)
                    ->setParameter('ids', $chunk)
                    ->getQuery()
                    ->execute();
            }

            return $changed;
        });
    }

    /**
     * Clears the actor label of every revision whose author was deleted (`author_id` set to NULL by the database).
     *
     * @return int the revisions changed
     */
    public function eraseOrphaned(): int
    {
        $em = $this->em();

        return (int) WikiSavepoint::write($em, static fn (): mixed => $em->createQueryBuilder()
            ->update(WikiPageRevision::class, 'r')
            ->set('r.actorLabel', ':none')
            ->where('r.author IS NULL')
            ->andWhere('r.actorLabel IS NOT NULL')
            ->setParameter('none', null)
            ->getQuery()
            ->execute());
    }

    /**
     * The query parameter of the user: the stored reference (a relation, entity mode), or in the id mode
     * (`database.user_reference: id`, 1.36.0) its exact id through {@see WikiUserRefs::fromUser()}: null when it has
     * none the wiki can match exactly (it then owns no label).
     */
    private function userParameter(EntityManagerInterface $em, object $user): object|string|null
    {
        $reference = $this->users?->reference($user) ?? $user;

        return WikiUserFields::byId($em, WikiPageRevision::class, 'author') ? WikiUserRefs::fromUser($reference)->id : $reference;
    }

    private function em(): EntityManagerInterface
    {
        return $this->entityManager instanceof WikiEntityManagerProvider ? $this->entityManager->get() : $this->entityManager;
    }
}
