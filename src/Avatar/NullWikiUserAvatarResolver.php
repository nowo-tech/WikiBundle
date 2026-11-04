<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Avatar;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Default {@see WikiUserAvatarResolverInterface} (1.16): no pictures, so every person shows initials.
 */
final readonly class NullWikiUserAvatarResolver implements WikiUserAvatarResolverInterface
{
    public function urlFor(UserInterface $user, int $size): ?string
    {
        return null;
    }

    public function urlsFor(array $users, int $size): array
    {
        return [];
    }
}
