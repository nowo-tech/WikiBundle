<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\User;

use Stringable;

use function is_int;
use function is_string;
use function method_exists;

/**
 * Scalar id of a user object (1.27): its `getId()` as a string (int, string or Stringable such as a Uuid), null when
 * it has none. A Doctrine reference answers without loading. Used by the `actorId()` of the wiki events.
 */
final class WikiUserId
{
    /**
     * The user's `getId()` as a string, or null.
     */
    public static function of(?object $user): ?string
    {
        if ($user === null || !method_exists($user, 'getId')) {
            return null;
        }

        $id = $user->getId();
        if (is_int($id)) {
            return (string) $id;
        }

        return (is_string($id) || $id instanceof Stringable) && (string) $id !== '' ? (string) $id : null;
    }
}
