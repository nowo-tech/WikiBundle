<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Enum;

/**
 * How a wiki space is scoped to an owner (team or individual user).
 */
enum WikiSpaceOwnerScope: string
{
    case Team = 'team';
    case User = 'user';
}
