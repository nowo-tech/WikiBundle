<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\ValueObject;

use Nowo\WikiBundle\Doctrine\WikiSpaceCollationListener;

use function preg_match;

/**
 * Rule for the owner of a space (`owner_scope_id`): the id of a team or user (1.31.1, house rule LQ).
 *
 * MySQL and MariaDB compare strings with the column collation: the host defaults (`utf8mb4_0900_ai_ci`,
 * `utf8mb4_unicode_ci`, MariaDB `utf8mb4_uca1400_ai_ci`) fold case and accents and ignore some invisible characters,
 * and the PAD SPACE ones also ignore trailing spaces. So `Alice`, `álice`, `alice ` or `alice` plus a zero-width space
 * would match the spaces of `alice`. Every lookup re-checks the owner with `===` in PHP and the column gets a binary
 * NO PAD collation ({@see WikiSpaceCollationListener}); on top of that, an id that cannot be
 * told apart from another one is refused where it enters the bundle: empty, longer than the column (64 characters),
 * invalid UTF-8, leading or trailing whitespace, or a control or format character (zero-width space, bidi marks, BOM).
 *
 * Integer and UUID ids always pass, as does a user identifier (e-mail, username) with no such characters. Refused,
 * never normalised: trimming or case-folding would make two ids one.
 */
final class WikiOwnerScopeId
{
    /** Length of the `owner_scope_id` column, in characters. */
    public const MAX_LENGTH = 64;

    /** Whether the id can own a space and be looked up without matching a different id. */
    public static function isUsable(string $id): bool
    {
        // One to 64 characters of valid UTF-8 (the `u` modifier rejects invalid sequences).
        if (preg_match('/\A.{1,' . self::MAX_LENGTH . '}\z/su', $id) !== 1) {
            return false;
        }

        // No control (Cc) or format (Cf) character anywhere, no whitespace or separator at either end.
        return preg_match('/[\p{Cc}\p{Cf}]|\A[\s\p{Z}]|[\s\p{Z}]\z/u', $id) !== 1;
    }
}
