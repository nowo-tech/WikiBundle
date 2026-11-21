<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Space;

use LogicException;

/**
 * 1.50 (audit XO-1): {@see WikiSpaceLifecycleInterface::purgeSpace()} was called while a transaction is open on the
 * wiki's connection. Each batch would only be a savepoint, yet its files are deleted and its events dispatched at once:
 * a later rollback would bring the rows back without their files. Call the purge outside any transaction (a message
 * handler, a command, a controller after your own transaction committed). Nothing was written. An API answers 409
 * without `Retry-After`.
 */
final class WikiSpacePurgeInTransactionException extends LogicException
{
    public function __construct()
    {
        parent::__construct('A space purge runs outside any transaction: call it after your transaction committed.');
    }
}
