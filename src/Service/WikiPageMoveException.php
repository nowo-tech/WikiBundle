<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use RuntimeException;

/**
 * A refused page move (1.14): `page_missing`, `parent_missing`, `cycle`, `too_deep` or `stale` (the page
 * moved meanwhile, audit DI-I2). The reason is also the suffix of
 * the translation key `wiki.tree.error.<reason>`.
 */
final class WikiPageMoveException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('Page move refused: ' . $reason . '.');
    }
}
