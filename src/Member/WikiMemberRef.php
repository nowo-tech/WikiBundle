<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Member;

use InvalidArgumentException;
use Nowo\WikiBundle\Port\WikiUserRefs;
use Nowo\WikiBundle\User\WikiRefUser;
use Nowo\WikiBundle\ValueObject\WikiStoredUserRef;

use function ltrim;
use function mb_strlen;
use function preg_match;
use function strlen;
use function strrpos;
use function substr;

/**
 * Spec 234: a wiki member as {@see WikiMemberBadgeProviderInterface} sees it — the user's class (Doctrine
 * proxy prefix removed) and id, the same class + id model as the id user references of REQ-ARCH-001 (a member is the
 * same as another only with the same class and the same id, byte for byte). A user without an id the wiki can match
 * exactly gets no reference (and so no badge). Framework-free value object (REQ-ARCH-001 rule 3).
 */
final readonly class WikiMemberRef
{
    /** Characters of an id at most. */
    public const MAX_LENGTH = 128;

    private const PROXY_MARKER = '\\__CG__\\';

    /**
     * @param string $userClass the user's class
     * @param string $id the user's id: valid UTF-8, 1 to 128 characters, no whitespace at its edges, no control or
     *                   format characters
     *
     * @throws InvalidArgumentException when the id cannot be matched exactly
     */
    public function __construct(public string $userClass, public string $id)
    {
        if (!self::isValidId($id)) {
            throw new InvalidArgumentException('A member id is a valid UTF-8 id of 1 to 128 characters, without whitespace at its edges and without control or format characters.');
        }
    }

    /**
     * The reference of a user object (its `getId()` as a string), or null when it has no id the wiki can match exactly.
     *
     * 1.36.0 (REQ-ARCH-001, `database.user_reference: id`): read through {@see WikiUserRefs::fromUser()}, so a stored
     * id reference ({@see WikiStoredUserRef}, what a wiki row holds in the id mode) is a member of `$storedUserClass`
     * (`nowo_wiki.user_class`), and a framework-free security user ({@see WikiRefUser}) is the member its
     * reference names. A stored id without a user class names no member (null: no badge).
     *
     * @param object $user a host user, a security user, a user read back from a wiki row or a stored id reference
     * @param string $storedUserClass the class a stored id reference belongs to (`nowo_wiki.user_class`)
     */
    public static function of(object $user, string $storedUserClass = ''): ?self
    {
        $ref = WikiUserRefs::fromUser($user, $storedUserClass);
        if ($ref->id === null || !self::isValidId($ref->id) || $ref->userClass === WikiStoredUserRef::class) {
            return null;
        }

        return new self(self::realClass(ltrim($ref->userClass, '\\')), $ref->id);
    }

    /** A class name without the Doctrine proxy prefix. */
    private static function realClass(string $class): string
    {
        $pos = strrpos($class, self::PROXY_MARKER);

        return $pos === false ? $class : substr($class, $pos + strlen(self::PROXY_MARKER));
    }

    /** Whether both references name the same member (class and id, byte for byte). */
    public function isSameMemberAs(self $other): bool
    {
        return $this->id === $other->id && $this->userClass === $other->userClass;
    }

    /** Whether an id can be matched byte for byte. */
    public static function isValidId(string $id): bool
    {
        if ($id === '' || preg_match('//u', $id) !== 1 || mb_strlen($id) > self::MAX_LENGTH) {
            return false;
        }

        return preg_match('/\A[\s\p{Z}]|[\s\p{Z}]\z|\p{C}/u', $id) !== 1;
    }
}
