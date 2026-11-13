<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\User;

use Nowo\WikiBundle\Service\WikiRequestBoundaryResetter;
use Nowo\WikiBundle\ValueObject\WikiStoredUserRef;
use Symfony\Contracts\Service\ResetInterface;

use function array_key_exists;
use function count;

/**
 * The host users behind stored id references (1.34.0, `database.user_reference: id`).
 *
 * A wiki row read in the id mode holds a {@see WikiStoredUserRef}; names, avatars and the REST API's people need the
 * host user, which the host's {@see WikiUserDirectoryInterface} (`user_directory`) finds by id. Every answer is
 * re-checked with `===` on its `getId()` (REQ-SEC-006 item 4): a user with another id is ignored. In the entity mode
 * (default) every value is passed through unchanged.
 *
 * The answers are kept for one unit of work only: emptied at every request, Messenger and console boundary by
 * {@see WikiRequestBoundaryResetter} and at `kernel.reset` (REQ-RUNTIME-002, kernel kept alive without reset), and
 * bounded to {@see self::MAX_FOUND} entries.
 */
final class WikiUsers implements ResetInterface
{
    /** Users kept per unit of work at most (a page of history or comments asks for a few dozen). */
    public const MAX_FOUND = 1000;

    /** @var array<string, object|null> */
    private array $found = [];

    /**
     * @param string $mode `database.user_reference` (`entity` or `id`)
     * @param WikiUserDirectoryInterface|null $directory the host's directory (`user_directory`); null: no names
     */
    public function __construct(
        private readonly string $mode = 'entity',
        private readonly ?WikiUserDirectoryInterface $directory = null,
    ) {
    }

    /**
     * Whether the user fields are stored by id.
     *
     * @return bool true with `database.user_reference: id`
     */
    public function idMode(): bool
    {
        return $this->mode === 'id';
    }

    /**
     * The host user with exactly this id, or null (no directory, no such user, or the directory answered another id).
     *
     * @param string $id the stored id
     *
     * @return object|null the host user
     */
    public function find(string $id): ?object
    {
        if (!$this->directory instanceof WikiUserDirectoryInterface || !WikiStoredUserRef::isValid($id)) {
            return null;
        }
        if (!array_key_exists($id, $this->found)) {
            $user = $this->directory->find($id);
            if (count($this->found) >= self::MAX_FOUND) {
                $this->reset();
            }
            // @igor-ignore - Per unit of work: emptied at every request, Messenger and console boundary and at kernel.reset.
            $this->found[$id] = $user !== null && WikiUserId::of($user) === $id ? $user : null;
        }

        return $this->found[$id];
    }

    /**
     * The object to present for a stored user: the host user behind a {@see WikiStoredUserRef} when the directory
     * finds it, else the value unchanged.
     *
     * @param object|null $stored a field value
     *
     * @return object|null the host user, the reference, or null
     */
    public function resolve(?object $stored): ?object
    {
        if (!$stored instanceof WikiStoredUserRef) {
            return $stored;
        }

        return $this->find($stored->id) ?? $stored;
    }

    /**
     * Forgets the users found in this unit of work.
     */
    public function reset(): void
    {
        $this->found = [];
    }
}
