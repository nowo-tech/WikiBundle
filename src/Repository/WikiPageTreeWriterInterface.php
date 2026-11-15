<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Nowo\WikiBundle\Entity\WikiSpace;

/**
 * Atomic page tree changes (1.14): moves of one space run one at a time, so two concurrent moves cannot build a cycle
 * or interleave their sibling positions.
 *
 * Persistence adapter of the bundle's own entities, not a host port (REQ-ARCH-001): it is entity-typed by design;
 * hosts reach wiki data through the services, the id operations and the events.
 */
interface WikiPageTreeWriterInterface
{
    /**
     * Starts a transaction that holds a write lock on the space (pages read afterwards are read after the lock).
     */
    public function beginSpaceTransaction(WikiSpace $space): void;

    /**
     * Flushes the changes and commits the transaction started by {@see self::beginSpaceTransaction()}.
     */
    public function commitSpaceTransaction(): void;

    /**
     * Rolls back the transaction started by {@see self::beginSpaceTransaction()} (on failure; the caller rethrows).
     */
    public function rollbackSpaceTransaction(): void;
}
