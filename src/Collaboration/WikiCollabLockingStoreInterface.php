<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Collaboration;

/**
 * A {@see WikiCollabStateStoreInterface} that can hold a page's log still while a check, a save and a drop happen, so
 * an update cannot land between them and be lost (audit HO-L1). A store without it runs them with no lock.
 */
interface WikiCollabLockingStoreInterface
{
    /**
     * Runs the work with the page's log locked, in one transaction: an update waits until it ends. Rolled back if the
     * work throws.
     *
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function whileLocked(string $pageId, callable $work): mixed;
}
