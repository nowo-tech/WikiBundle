<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Query;

/**
 * 1.51: the order of {@see WikiSpaceQueryInterface::findSpaces()}. Every order ends with the space id, ascending, so pages
 * of results never overlap or skip a row.
 */
enum WikiSpaceSort: string
{
    case NameAsc     = 'name_asc';
    case NameDesc    = 'name_desc';
    case SlugAsc     = 'slug_asc';
    case CreatedAsc  = 'created_asc';
    case CreatedDesc = 'created_desc';
    case UpdatedDesc = 'updated_desc';
}
