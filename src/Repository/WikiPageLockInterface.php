<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Service\WikiPageService;

/**
 * 1.24 (spec 218): the write lock of one page, for a save that must start from a known revision
 * ({@see WikiPageService::saveRevision()} with an expected revision). The Doctrine page
 * repository implements it; a host repository that does not cannot take an expected revision.
 *
 * Persistence adapter of the bundle's own entities, not a host port (REQ-ARCH-001): it is entity-typed by design;
 * hosts reach wiki data through the services, the id operations and the events.
 */
interface WikiPageLockInterface
{
    /**
     * Begins a transaction and takes a write lock on the page row (`SELECT … FOR UPDATE` where the platform has it),
     * then refreshes the page from the database, its current revision included. A second writer waits here until the
     * first one commits or rolls back.
     */
    public function beginPageTransaction(WikiPage $page): void;

    /** Flushes and commits the page transaction. */
    public function commitPageTransaction(): void;

    /** Rolls the page transaction back. */
    public function rollbackPageTransaction(): void;
}
