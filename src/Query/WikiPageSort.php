<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Query;

/**
 * 1.51: the order of {@see WikiPageQueryInterface::findPages()}. Every order ends with the page id, ascending, so pages
 * of results never overlap or skip a row.
 */
enum WikiPageSort: string
{
    /** By title (as the database compares it). */
    case TitleAsc = 'title_asc';

    /** Oldest first (an export keeps the order pages were written in). */
    case CreatedAsc = 'created_asc';

    /** Latest change first. */
    case UpdatedDesc = 'updated_desc';

    /** The tree order of the sidebar inside each space: space id, then position among siblings. */
    case Position = 'position';
}
