<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\User;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\WikiBundle\ValueObject\WikiStoredUserRef;

/**
 * Mode-aware access to the user fields of the wiki rows (1.34.0, `database.user_reference`). The mode is read from the
 * loaded metadata: a relation to `user_class` (`entity`, default) or a {@see WikiStoredUserRef} column (`id`), so the
 * repositories need no extra configuration and the default path is unchanged.
 *
 * In the id mode every lookup keyed by a user compares the stored id with `===` in PHP after the SQL (REQ-SEC-006 item
 * 2), so a folding collation (a host that has not run the UPGRADING `ALTER TABLE`) never lets a look-alike id match.
 *
 * @internal
 */
final class WikiUserFields
{
    /**
     * Whether the field is stored as an id column (`database.user_reference: id`).
     *
     * @param EntityManagerInterface $manager the wiki's entity manager
     * @param class-string $class the wiki entity class
     * @param string $field the user field
     *
     * @return bool true in the id mode
     */
    public static function byId(EntityManagerInterface $manager, string $class, string $field): bool
    {
        return !$manager->getClassMetadata($class)->hasAssociation($field);
    }

    /**
     * The DQL expression of the stored user id: `IDENTITY(alias.field)` for a relation, the column itself in the id
     * mode.
     *
     * @param EntityManagerInterface $manager the wiki's entity manager
     * @param class-string $class the wiki entity class
     * @param string $alias the DQL alias of the entity
     * @param string $field the user field
     *
     * @return string the DQL expression
     */
    public static function identity(EntityManagerInterface $manager, string $class, string $alias, string $field): string
    {
        return self::byId($manager, $class, $field) ? $alias . '.' . $field : 'IDENTITY(' . $alias . '.' . $field . ')';
    }

    /**
     * The id a user is stored under in the id mode; null when it has none that can be matched exactly (it then owns
     * nothing).
     *
     * @param object $user a stored reference, a host user or a security user
     *
     * @return string|null the exact id, or null
     */
    public static function exactId(object $user): ?string
    {
        $id = $user instanceof WikiStoredUserRef ? $user->id : WikiUserId::of($user);

        return $id !== null && WikiStoredUserRef::isValid($id) ? $id : null;
    }

    /**
     * Whether a stored user (a field value) is exactly that id.
     *
     * @param object|null $stored the field value
     * @param string $id the exact id
     *
     * @return bool true when the ids are equal byte for byte
     */
    public static function is(?object $stored, string $id): bool
    {
        return $stored !== null && self::exactId($stored) === $id;
    }
}
