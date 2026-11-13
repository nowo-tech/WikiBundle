<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\StringType;
use InvalidArgumentException;
use Nowo\WikiBundle\User\WikiUserId;
use Nowo\WikiBundle\ValueObject\WikiStoredUserRef;

use function is_int;
use function is_object;
use function is_string;

/**
 * The column type of the user fields with `nowo_wiki.database.user_reference: id` (1.32.0, REQ-ARCH-001 rule 2):
 * the host user's id in a string column, read back as {@see WikiStoredUserRef}.
 *
 * A {@see WikiStoredUserRef}, a string id or a host user object (stored by its `getId()`) can be written. REQ-SEC-006
 * item 1: an id that cannot be matched exactly (edge whitespace, control or format characters, invalid UTF-8, more
 * than 128 characters) is refused before any write, on every platform (SQLite does not enforce the length).
 */
final class WikiUserRefType extends StringType
{
    public const NAME = 'wiki_user_ref';

    /**
     * @param mixed $value a stored reference, a string id, a user object or null
     * @param AbstractPlatform $platform the database platform
     *
     * @throws InvalidType when the value cannot carry an id
     * @throws InvalidArgumentException when the id cannot be matched exactly
     *
     * @return string|null the id, or null
     */
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }
        $id = match (true) {
            $value instanceof WikiStoredUserRef => $value->id,
            is_string($value)                   => $value,
            is_object($value)                   => WikiUserId::of($value),
            default                             => null,
        };
        if ($id === null) {
            throw InvalidType::new($value, self::NAME, ['null', 'string', WikiStoredUserRef::class, 'an object with getId()']);
        }
        if (!WikiStoredUserRef::isValid($id)) {
            throw new InvalidArgumentException('A wiki user reference is a valid UTF-8 id of 1 to 128 characters, without whitespace at its edges and without control or format characters.');
        }

        return $id;
    }

    /**
     * @param mixed $value the column value
     * @param AbstractPlatform $platform the database platform
     *
     * @return WikiStoredUserRef|null the stored reference; null for NULL, '' or a legacy value that cannot be matched
     *                                exactly (fail closed: it identifies nobody)
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?WikiStoredUserRef
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }
        $id = (string) $value;

        return WikiStoredUserRef::isValid($id) ? new WikiStoredUserRef($id) : null;
    }
}
