<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Service\WikiPageService;

/**
 * 1.24 (spec 218): the write lock of one page, for a save that must start from a known revision
 * ({@see WikiPageService::saveRevision()} with an expected revision). The Doctrine page
 * repository implements it; a host repository that does not cannot take an expected revision.
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
