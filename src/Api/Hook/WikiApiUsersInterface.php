<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Hook;

/**
 * 1.24 (spec 218): the id a client sees for a person. Shown only for the token's own user (owner Q-O5).
 */
interface WikiApiUsersInterface
{
    public function apiId(object $user): ?string;
}
