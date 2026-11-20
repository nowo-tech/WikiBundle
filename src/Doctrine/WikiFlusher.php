<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;
use Throwable;

/**
 * 1.37 (Beacon spec 239): the flush of the wiki's repositories. A manager that an earlier failure of this request closed
 * is not reset in the middle of the request (that would detach the application's entities behind its back, audit
 * QD-M1): the write answers {@see TransientStorageFailure} (503 + Retry-After on the wiki's routes) and the manager is
 * replaced at the next request, message or command boundary ({@see WikiTransactionRunner::reset()}).
 * The private manager ({@see WikiEntityManager}) is created again on its next call, so it is never closed here.
 *
 * @internal
 */
final class WikiFlusher
{
    /**
     * Flushes the wiki's manager, or refuses with a transient failure when it is closed.
     *
     * 1.49: when `$source` is the bundle's {@see WikiEntityManagerProvider} and the manager is the shared one
     * (`database.private_entity_manager: false`), the flush goes through {@see WikiHostFlushProbe}: the application's
     * pending changes it writes are reported first (REQ-ARCH-001 rule 6 deprecation layer). Read-only; nothing else
     * changes.
     *
     * @param EntityManagerInterface $entityManager the manager to flush
     * @param EntityManagerInterface|WikiEntityManagerProvider|null $source what the caller resolved it from (a repository's
     *                                                                      constructor argument)
     *
     * @throws TransientStorageFailure when the manager was closed by an earlier failure
     */
    public static function flush(EntityManagerInterface $entityManager, EntityManagerInterface|WikiEntityManagerProvider|null $source = null): void
    {
        if (!$entityManager->isOpen()) {
            throw new TransientStorageFailure(1, 'The wiki\'s entity manager was closed by an earlier failure of this request; it is replaced at the next request.');
        }
        $probe = $source instanceof WikiEntityManagerProvider && !$entityManager instanceof WikiEntityManager ? $source->hostFlushProbe() : null;
        if ($probe instanceof WikiHostFlushProbe) {
            $probe->around($entityManager, static fn () => $entityManager->flush());

            return;
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
