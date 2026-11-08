<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Hook;

/**
 * 1.24 (spec 218): the default: nobody has an id in the API.
 */
final readonly class NullWikiApiUsers implements WikiApiUsersInterface
{
    public function apiId(object $user): ?string
    {
        return null;
    }
}
