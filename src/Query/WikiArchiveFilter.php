<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Query;

/**
 * 1.51: which rows a read port returns by their archive state: a space's `archivedAt` (1.50), a page's `archivedAt`
 * (archived and trashed pages alike).
 */
enum WikiArchiveFilter: string
{
    /** Only rows that are not archived. */
    case Active = 'active';

    /** Only archived rows. */
    case Archived = 'archived';

    /** Every row, archived or not. */
    case Any = 'any';
}
