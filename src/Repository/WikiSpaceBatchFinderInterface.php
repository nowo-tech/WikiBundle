<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Nowo\WikiBundle\Entity\WikiSpace;

/**
 * 1.55.2: optional reads of a space repository for callers that ask many spaces at once (a host access checker, a
 * listing). {@see DoctrineOrmWikiSpaceRepository} implements it (autowire this interface); a repository without it is
 * read one id at a time through {@see WikiSpaceRepositoryInterface::findById()}. 2.0 folds both reads into
 * {@see WikiSpaceRepositoryInterface}.
 *
 * Which read to use:
 *
 * - {@see WikiSpaceRepositoryInterface::findById()} (unchanged): the row read again from the database on every call
 *   (one `refresh()`), so a change another worker or request committed is never hidden by the identity map of a
 *   kept-alive kernel. Use it on every write path and before any decision that must see the latest committed state.
 * - {@see self::findByIds()}: the same fresh state for many ids in ONE query (the rows are refreshed by the query
 *   itself), instead of 2 queries per id. Use it wherever a list of spaces is checked or shown.
 * - {@see self::findByIdWithoutRefresh()}: the instance the wiki's manager already holds, else one load. No query when
 *   the space was read earlier in the same unit of work, so its state may be as old as that read (another worker's
 *   archive, rename or owner change since then is not seen). Read paths only (a repeated access check inside one
 *   request): never on a write path, never to decide a write (REQ-RUNTIME-003). In FrankenPHP worker mode the wiki's
 *   private manager is dropped at every request boundary; the shared one is cleared when the application clears it, so
 *   there the state can be older than the request.
 *
 * Persistence adapter of the bundle's own entities, not a host port (REQ-ARCH-001): it is entity-typed by design.
 */
interface WikiSpaceBatchFinderInterface
{
    /**
     * Finds the spaces of the given ids in one query (one per 500 ids in the bundle's repository), read fresh from the database (archived ones included; unknown or
     * look-alike ids are left out).
     *
     * @param list<string> $ids space ids (duplicates are ignored)
     *
     * @return array<string, WikiSpace> the spaces found, keyed by id, in the order of `$ids`
     */
    public function findByIds(array $ids): array;

    /**
     * Finds a space by id without reading it again when the wiki's manager already holds it (see the interface
     * description for the stale-state risk: read paths only).
     *
     * @param string $id space id
     *
     * @return WikiSpace|null the space, or null when none has exactly that id
     */
    public function findByIdWithoutRefresh(string $id): ?WikiSpace;
}
