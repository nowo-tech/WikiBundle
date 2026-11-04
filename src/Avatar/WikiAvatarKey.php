<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Avatar;

use Symfony\Component\Security\Core\User\UserInterface;

use function is_int;
use function is_string;
use function method_exists;

/**
 * The key of a user in {@see WikiUserAvatarResolverInterface::urlsFor()} (1.16): `getId()` as a string when the user
 * has a scalar id, else the user identifier.
 */
final class WikiAvatarKey
{
    public static function of(UserInterface $user): string
    {
        $id = method_exists($user, 'getId') ? $user->getId() : null;

        return is_int($id) || (is_string($id) && $id !== '') ? (string) $id : $user->getUserIdentifier();
    }
}
