<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\ValueObject;

use InvalidArgumentException;

use function in_array;
use function is_string;

/**
 * The user a host port is asked about (1.34.0, REQ-ARCH-001 rules 1 and 3): the framework-free twin of the Symfony
 * `UserInterface` the deprecated ports take.
 *
 * It carries the user's class (Doctrine proxy prefixes removed), id (null when the user has none the wiki can match
 * exactly), security identifier and roles, all copied when the port is called. {@see self::user()} gives back the
 * object the wiki was given (the security user, a user read back from a wiki row, a stored id reference), for hosts
 * that still need it; a DDD host never has to.
 *
 * Compare users with {@see self::isSameUserAs()} (class and id, byte for byte), never with `==`.
 */
final readonly class WikiUserRef
{
    /** @var list<string> */
    public array $roles;

    /**
     * @param string $userClass the user's class (no Doctrine proxy prefix)
     * @param string|null $id the user's id; null when the user has no id the wiki can match exactly
     * @param string $identifier the security identifier (`getUserIdentifier()`), '' when unknown
     * @param array<mixed> $roles the user's roles (non-string entries are dropped)
     * @param object|null $user the object the reference was built from
     *
     * @throws InvalidArgumentException when the id is given but cannot be matched exactly (REQ-SEC-006 item 1)
     */
    public function __construct(
        public string $userClass,
        public ?string $id,
        public string $identifier = '',
        array $roles = [],
        private ?object $user = null,
    ) {
        if ($id !== null && !WikiStoredUserRef::isValid($id)) {
            throw new InvalidArgumentException('A wiki user id is a valid UTF-8 id of 1 to 128 characters, without whitespace at its edges and without control or format characters; pass null for a user without one.');
        }

        $kept = [];
        foreach ($roles as $role) {
            if (is_string($role)) {
                $kept[] = $role;
            }
        }
        $this->roles = $kept;
    }

    /**
     * A reference to a user known only by class and id (a DDD host's own user, a test).
     *
     * @param string $userClass the user's class
     * @param string $id the user's id
     * @param string $identifier the security identifier, '' when unknown
     * @param array<mixed> $roles the user's roles
     *
     * @return self the reference
     */
    public static function ofId(string $userClass, string $id, string $identifier = '', array $roles = []): self
    {
        return new self($userClass, $id, $identifier, $roles);
    }

    /**
     * The object the reference was built from (the security user, a user read back from a wiki row, a
     * {@see WikiStoredUserRef}); null when built from an id.
     *
     * @return object|null the original user object
     */
    public function user(): ?object
    {
        return $this->user;
    }

    /**
     * Whether the user has an id the wiki can match exactly.
     *
     * @return bool true when {@see self::$id} is set
     */
    public function hasId(): bool
    {
        return $this->id !== null;
    }

    /**
     * Whether the user holds a role (exact match).
     *
     * @param string $role role name
     *
     * @return bool true when the role is in {@see self::$roles}
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    /**
     * Whether both references name the same user: same class and same id, byte for byte (REQ-SEC-006 item 7). Users
     * without an id are never the same as anyone.
     *
     * @param self $other the other reference
     *
     * @return bool true for the same user
     */
    public function isSameUserAs(self $other): bool
    {
        return $this->id !== null && $this->id === $other->id && $this->userClass === $other->userClass;
    }
}
