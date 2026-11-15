<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Collaboration;

use Nowo\TransactionContract\TransientStorageFailure;
use RuntimeException;

/**
 * 1.37 (audit QN-L4): a transient failure of the co-editing store inside a transaction it did not open (the log's lock
 * around a save, the application's transaction). The server may have rolled back that whole transaction (a MySQL
 * deadlock does), so the failure is never retried under the lock: the save it belongs to answers a collision (nothing
 * written) and the caller retries.
 */
final class WikiCollabUnitLostException extends RuntimeException
{
    public function __construct(TransientStorageFailure $previous)
    {
        parent::__construct('A co-editing write lost its transaction: nothing of the unit it belongs to was written.', 0, $previous);
    }
}
