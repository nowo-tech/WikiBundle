<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\TransactionContract\TransientStorageFailure;
use Throwable;

/**
 * 1.37 (Beacon spec 239): the flush of the wiki's repositories. A closed manager answers {@see TransientStorageFailure}
 * (503 + Retry-After on the wiki's routes). The private manager ({@see WikiEntityManager}) is created again on its next
 * call after a failed flush closed it, so with the bundle's services this only concerns a manager given by hand.
 *
 * @internal
 */
final class WikiFlusher
{
    /**
     * Flushes the wiki's manager, or refuses with a transient failure when it is closed.
     *
     * @param EntityManagerInterface $entityManager the manager to flush
     *
     * @throws TransientStorageFailure when the manager was closed by an earlier failure
     */
    public static function flush(EntityManagerInterface $entityManager): void
    {
        if (!$entityManager->isOpen()) {
            throw new TransientStorageFailure(1, 'The wiki\'s entity manager was closed by an earlier failure of this request; it is replaced at the next request.');
        }
        $entityManager->flush();
    }

    /**
     * Rolls back the transaction level a wiki repository opened (its tree or page lock). When that rollback fails (after
     * a MySQL / MariaDB deadlock the server dropped the savepoints: 1305), the connection is closed, so the worker's next
     * request never inherits a nested connection; the caller rethrows its ORIGINAL failure.
     */
    public static function abandon(EntityManagerInterface $entityManager): void
    {
        try {
            $entityManager->rollback();
        } catch (Throwable) {
            $entityManager->getConnection()->close();
        }
    }
}
