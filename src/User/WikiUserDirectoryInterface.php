<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\User;

/**
 * Finds a host user by id (1.32.0, `nowo_wiki.database.user_reference: id`; config key `user_directory`).
 *
 * With the id mode a wiki row keeps only the user's id (`ValueObject\WikiStoredUserRef`); the bundle asks this port
 * when it needs more than the id: names and avatars of revision and comment authors, the REST API's people. Return the
 * user whose id is exactly `$id` (byte for byte, REQ-SEC-006 item 4), or null; the bundle re-checks the returned
 * user's `getId()` with `===` and ignores a mismatch.
 */
interface WikiUserDirectoryInterface
{
    /**
     * @param string $id the stored user id, exactly as stored
     *
     * @return object|null the host user with that id (any object with `getId()`; a Symfony security user gets its name
     *                     from `getUserIdentifier()`), or null when there is none
     */
    public function find(string $id): ?object;
}
