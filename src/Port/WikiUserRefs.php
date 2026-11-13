<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Port;

use Nowo\WikiBundle\User\WikiRefUser;
use Nowo\WikiBundle\User\WikiUserId;
use Nowo\WikiBundle\ValueObject\WikiStoredUserRef;
use Nowo\WikiBundle\ValueObject\WikiUserRef;
use Symfony\Component\Security\Core\User\UserInterface;

use function strlen;
use function strrpos;
use function substr;

/**
 * Builds the {@see WikiUserRef} a host port twin is asked about (1.32.0) from the user object the bundle holds.
 *
 * @internal
 */
final class WikiUserRefs
{
    private const PROXY_MARKER = '\\__CG__\\';

    /**
     * @param object $user a security user, a user read back from a wiki row or a {@see WikiStoredUserRef}
     * @param string $storedUserClass the class named by a stored id reference (`nowo_wiki.user_class`)
     *
     * @return WikiUserRef the reference (id null when the user has no id the wiki can match exactly)
     */
    public static function fromUser(object $user, string $storedUserClass = ''): WikiUserRef
    {
        if ($user instanceof WikiRefUser) {
            return $user->ref();
        }
        if ($user instanceof WikiStoredUserRef) {
            return new WikiUserRef($storedUserClass !== '' ? $storedUserClass : WikiStoredUserRef::class, $user->id, '', [], $user);
        }

        $id = WikiUserId::of($user);
        if ($id !== null && !WikiStoredUserRef::isValid($id)) {
            $id = null; // REQ-SEC-006: an id that cannot be matched exactly identifies nobody
        }

        return $user instanceof UserInterface
            ? new WikiUserRef(self::className($user), $id, $user->getUserIdentifier(), $user->getRoles(), $user)
            : new WikiUserRef(self::className($user), $id, '', [], $user);
    }

    /**
     * @param object|null $user a user object, or null
     * @param string $storedUserClass the class named by a stored id reference
     *
     * @return WikiUserRef|null the reference, or null
     */
    public static function fromUserOrNull(?object $user, string $storedUserClass = ''): ?WikiUserRef
    {
        return $user === null ? null : self::fromUser($user, $storedUserClass);
    }

    /**
     * The security user the bundle works with for a reference: the object it was built from when that is a Symfony
     * user, else a {@see WikiRefUser} standing for it.
     *
     * @param WikiUserRef $ref the reference
     *
     * @return UserInterface the security user
     */
    public static function securityUser(WikiUserRef $ref): UserInterface
    {
        $user = $ref->user();

        return $user instanceof UserInterface ? $user : new WikiRefUser($ref);
    }

    /**
     * The user object the bundle stores or hands on for a reference (an actor, an author): the object it was built
     * from, else a {@see WikiRefUser}.
     *
     * @param WikiUserRef $ref the reference
     *
     * @return object the user object
     */
    public static function object(WikiUserRef $ref): object
    {
        return $ref->user() ?? new WikiRefUser($ref);
    }

    /**
     * The real class of a user object (the Doctrine proxy prefix removed).
     *
     * @param object $user the user object
     *
     * @return string its class
     */
    public static function className(object $user): string
    {
        $class = $user::class;
        $pos   = strrpos($class, self::PROXY_MARKER);

        return $pos === false ? $class : substr($class, $pos + strlen(self::PROXY_MARKER));
    }
}
