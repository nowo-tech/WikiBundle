<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Collaboration;

use LogicException;

use function hash_hmac;
use function preg_match;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * 2.0.1 (Beacon 207 T030, DATA-WIKI-COLLAB-SEEDEDBY): what `seeded_by` stores for a seed claim of the co-editing log.
 *
 * Never the user identifier (an e-mail in many hosts): a keyed hash of it, `h1:` + HMAC-SHA256 under a subkey of
 * `kernel.secret` used for this purpose only, plus `#<tab id>` when the client sent its Yjs client id (one seed per tab).
 * The claim is only ever compared for equality, so the hash is enough; the key keeps it from being reversed by hashing
 * a list of known e-mails. The person's erasure and export find their claims by the same hash (prefix before `#`).
 *
 * Pure functions, no state (worker mode).
 *
 * @internal
 */
final class WikiCollabSeedClaimant
{
    /** Marks a hashed claim (a version tag, so a later scheme can be told apart). */
    public const PREFIX = 'h1:';

    /** A stored value written by 2.0.1 or later: the hashed user, optionally followed by the tab id. */
    private const PATTERN = '/^h1:[0-9a-f]{64}(#\d{1,16})?$/D';

    /**
     * The hashed claimant of a user (without the tab).
     *
     * @throws LogicException when the secret is empty (an unkeyed hash could be reversed by dictionary)
     */
    public static function user(string $identifier, string $secret): string
    {
        if ($secret === '') {
            throw new LogicException('nowo_wiki.collaboration needs a non-empty kernel.secret to hash the seed claims.');
        }

        return self::PREFIX . hash_hmac('sha256', $identifier, hash_hmac('sha256', 'nowo_wiki_collab_seed', $secret));
    }

    /**
     * The claimant of a tab: the hashed user plus `#<client id>` when the client id is a valid Yjs client id.
     *
     * @throws LogicException when the secret is empty
     */
    public static function of(string $identifier, string $client, string $secret): string
    {
        return self::user($identifier, $secret) . (preg_match('/^\d{1,16}$/D', $client) === 1 ? '#' . $client : '');
    }

    /**
     * Whether a stored value has the hashed form (anything else is a raw identifier of 2.0.0 or earlier).
     */
    public static function isHashed(string $value): bool
    {
        return preg_match(self::PATTERN, $value) === 1;
    }

    /**
     * Whether a stored claim is the claimant `$base` (a hashed user, or the raw identifier a 2.0.0 claim stored) or one
     * of its tabs (`$base#<client id>`); compared byte for byte.
     */
    public static function belongsTo(string $value, string $base): bool
    {
        return $value === $base
            || (str_starts_with($value, $base . '#') && preg_match('/^\d{1,16}$/D', substr($value, strlen($base) + 1)) === 1);
    }
}
