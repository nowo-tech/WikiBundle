<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Avatar;

use Nowo\WikiBundle\ValueObject\WikiUserRef;

/**
 * User pictures, framework-free (1.33.0): the twin of the deprecated {@see WikiUserAvatarResolverInterface} (alias
 * either interface to your service).
 */
interface WikiUserAvatarSourceInterface
{
    /**
     * The picture of one user at `$size` CSS pixels, or null.
     */
    public function urlFor(WikiUserRef $user, int $size): ?string;

    /**
     * The pictures of several users, keyed like the list given (a missing key means no picture).
     *
     * @param list<WikiUserRef> $users distinct users
     *
     * @return array<int, string|null>
     */
    public function urlsFor(array $users, int $size): array;
}
