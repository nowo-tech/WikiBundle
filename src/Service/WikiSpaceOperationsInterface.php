<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use InvalidArgumentException;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Query\WikiSpaceQueryInterface;
use Nowo\WikiBundle\Space\WikiSpaceLifecycleInterface;
use Nowo\WikiBundle\Util\WikiSpaceSlugRule;
use Nowo\WikiBundle\ValueObject\WikiNewSpace;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;

/**
 * The space operations a host calls, returning snapshots (1.36.0, REQ-ARCH-001 rule 1): the framework-free twin of
 * {@see WikiSpaceServiceInterface} (public alias). Owner ids and slugs are matched byte for byte.
 *
 * Since 1.55 both creates write through the wiki's own transaction runner and connection (private entity manager or
 * not): a host never persists a `WikiSpace` with its own manager. Rename, archive, unarchive and purge by id are
 * {@see WikiSpaceLifecycleInterface}; lists and counts are {@see WikiSpaceQueryInterface}.
 */
interface WikiSpaceOperationsInterface
{
    /** The space with exactly that id, or null. */
    public function findSpace(string $spaceId): ?WikiSpaceSnapshot;

    /**
     * Creates a space named `$name` (slug from the name when none is given), as {@see self::createSpaceFrom()}.
     *
     * @throws InvalidArgumentException when the owner id or slug is invalid, or the slug is taken by this owner
     *                                  ({@see WikiSlugTakenException}, also when another request took it meanwhile)
     */
    public function createSpace(string $name, WikiSpaceOwnerScope $ownerScopeType, string $ownerScopeId, ?string $slug = null): WikiSpaceSnapshot;

    /**
     * 1.55 (Beacon): creates a space with exactly the given slug, name, owner and settings, and returns its id.
     *
     * The values are checked as the space settings are: the name is trimmed, 1 to 255 characters; the owner id is
     * usable (no leading or trailing whitespace, control or format characters, at most 64 characters); the slug follows
     * {@see WikiSpaceSlugRule} (the bundle's rule, or a configured tenant path's); the icon is `<prefix>:<name>` with a
     * prefix of `space_icon` (null: the default icon).
     *
     * One unit of the wiki's runner: the slug is looked up, then the row is written by one `INSERT`. Two requests that
     * create the same slug for the same owner at once: one wins, the other gets {@see WikiSlugTakenException} from the
     * unique key — never a duplicate, a 500 or a closed entity manager. Inside a transaction your application opened the
     * insert joins it (a savepoint): it is committed or rolled back with your work. No event is dispatched.
     *
     * @throws InvalidArgumentException when the name or owner id is invalid
     * @throws WikiInvalidSlugException when the slug does not follow the slug rule
     * @throws WikiInvalidIconException when the icon is not a valid icon of an allowed prefix
     * @throws WikiSlugTakenException when the owner already has a space with that slug (nothing written)
     * @throws TransientStorageFailure on a lock conflict (nothing written: retry-safe)
     *
     * @return string the new space's id (UUID)
     */
    public function createSpaceFrom(WikiNewSpace $space): string;

    public function findSpaceBySlug(WikiSpaceOwnerScope $scopeType, string $ownerScopeId, string $slug): ?WikiSpaceSnapshot;
}
