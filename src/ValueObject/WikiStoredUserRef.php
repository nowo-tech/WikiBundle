<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\ValueObject;

use InvalidArgumentException;
use Stringable;

use function mb_strlen;
use function preg_match;

/**
 * A host user stored on a wiki row by id (1.33.0, `nowo_wiki.database.user_reference: id`, REQ-ARCH-001 rule 2).
 *
 * With the id mode the revision author, the link creator, the uploader, the comment and thread authors, the resolver
 * and the reaction's user are no Doctrine relation to `user_class`: the row keeps the user's id in a string column
 * (`Doctrine\Type\WikiUserRefType`) and reads it back as this value object. `getId()` makes it usable wherever the
 * bundle only needs the id (events, ownership checks, the REST API); labels and avatars ask the host's
 * `User\WikiUserDirectoryInterface` for the user behind it.
 *
 * REQ-SEC-006 item 1: an id with whitespace or a separator at either edge, a control or format character (ZWSP, BOM,
 * bidi marks), invalid UTF-8 or more than {@see self::MAX_LENGTH} characters is refused, never trimmed or truncated.
 */
final readonly class WikiStoredUserRef implements Stringable
{
    /** Length of the id columns. */
    public const MAX_LENGTH = 128;

    /**
     * @param string $id the host user's id, exactly as stored
     *
     * @throws InvalidArgumentException when the id cannot be matched exactly ({@see self::isValid()})
     */
    public function __construct(public string $id)
    {
        if (!self::isValid($id)) {
            throw new InvalidArgumentException('A wiki user reference is a valid UTF-8 id of 1 to 128 characters, without whitespace at its edges and without control or format characters.');
        }
    }

    /**
     * Whether an id can be stored and matched byte for byte (REQ-SEC-006 item 1).
     *
     * @param string $id candidate id
     *
     * @return bool true when the id is usable
     */
    public static function isValid(string $id): bool
    {
        if ($id === '' || preg_match('//u', $id) !== 1 || mb_strlen($id) > self::MAX_LENGTH) {
            return false;
        }

        return preg_match('/\A[\s\p{Z}]|[\s\p{Z}]\z|\p{C}/u', $id) !== 1;
    }

    /**
     * The stored id (the same accessor as a host user entity, so id-only code treats both alike).
     *
     * @return string the host user's id
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string the host user's id
     */
    public function __toString(): string
    {
        return $this->id;
    }
}
