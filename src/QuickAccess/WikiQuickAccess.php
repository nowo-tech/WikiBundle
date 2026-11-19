<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\QuickAccess;

use DateTimeImmutable;
use Doctrine\DBAL\Exception\RetryableException;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageFavourite;
use Nowo\WikiBundle\Entity\WikiPageVisit;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Throwable;

use function count;

/**
 * 1.45: favourites (`favourites.enabled`) and recently viewed pages (`recents.enabled`) of the person viewing the wiki.
 *
 * Every list is built for one view ({@see WikiViewerScope}, computed once per view) before anything is cut to the shown
 * size: the person's own rows (exact id), only the spaces of the current tenant the viewer may read now (in the SQL),
 * only live pages (archived and trashed pages are left out by the query), then View on the page and every ancestor.
 * A page the viewer lost access to is never listed; its row is kept (it shows again if access comes back), never counts
 * toward `favourites.max` (audit VC-1) and can be removed by its id ({@see self::removeFavouriteById()}). Visits older
 * than `recents.retention_days` are never listed and are deleted on the next visit and by `wiki:recents:purge`.
 * Stateless (worker mode).
 */
final readonly class WikiQuickAccess
{
    /** Favourite rows read at most to count the readable ones against `favourites.max`. */
    public const MAX_FAVOURITE_SCAN = 1000;

    /**
     * @param array{enabled: bool, max: int, shown: int} $favourites `favourites` options
     * @param array{enabled: bool, keep: int, shown: int, retention_days: int} $recents `recents` options
     */
    public function __construct(
        private WikiQuickAccessStore $store,
        private array $favourites,
        private array $recents,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function favouritesEnabled(): bool
    {
        return $this->favourites['enabled'];
    }

    public function recentsEnabled(): bool
    {
        return $this->recents['enabled'];
    }

    /**
     * The viewer's favourites they may still read in this view's tenant, the newest first, at most `favourites.shown`.
     *
     * @return list<WikiPageFavourite>
     */
    public function favourites(WikiViewerScope $scope): array
    {
        $userId = $scope->userId();
        if (!$this->favourites['enabled'] || $userId === null) {
            return [];
        }

        /** @var list<WikiPageFavourite> $rows */
        $rows = $this->readable($scope, $this->store->favourites($userId, $scope->spaceIds(), self::MAX_FAVOURITE_SCAN), $this->favourites['shown']);

        return $rows;
    }

    /**
     * The pages the viewer opened last and may still read in this view's tenant, the last first, without `$current`,
     * at most `recents.shown`.
     *
     * @return list<WikiPageVisit>
     */
    public function recents(WikiViewerScope $scope, ?WikiPage $current = null, ?DateTimeImmutable $now = null): array
    {
        $userId = $scope->userId();
        if (!$this->recents['enabled'] || $userId === null) {
            return [];
        }
        $since = ($now ?? new DateTimeImmutable())->modify('-' . $this->recents['retention_days'] . ' days');

        /** @var list<WikiPageVisit> $rows */
        $rows = $this->readable($scope, $this->store->visits($userId, $scope->spaceIds(), $this->recents['keep'], $since), $this->recents['shown'], $current);

        return $rows;
    }

    /** Whether the page is one of the viewer's favourites (false when favourites are off). */
    public function isFavourite(UserInterface $viewer, WikiPage $page): bool
    {
        $userId = WikiViewerScope::idOf($viewer);

        return $this->favourites['enabled'] && $userId !== null && $this->store->isFavourite($page, $userId);
    }

    /** Whether the viewer can keep favourites at all (favourites on, a user with a valid id). */
    public function canKeepFavourites(UserInterface $viewer): bool
    {
        return $this->favourites['enabled'] && WikiViewerScope::idOf($viewer) !== null;
    }

    /**
     * Adds the page to, or removes it from, the viewer's favourites. The caller has checked that the viewer may read
     * the page. Only the favourites the viewer can still read in this tenant count toward `favourites.max` (audit
     * VC-1): hidden ones never block an add, and the refusal says nothing about them. Lock conflicts propagate (the route
     * answers 409 + CONFLICT_001 / CONFLICT_002).
     */
    public function setFavourite(WikiViewerScope $scope, WikiPage $page, bool $favourite): WikiFavouriteResult
    {
        $userId = $scope->userId();
        if (!$this->favourites['enabled'] || $userId === null) {
            return WikiFavouriteResult::Unchanged;
        }
        if (!$favourite) {
            // @igor-ignore - a write to the database through a stateless service (rows, not service state); nothing is kept between requests.
            return $this->store->removeFavourite($page, $userId);
        }
        if ($this->store->isFavourite($page, $userId)) {
            return WikiFavouriteResult::Unchanged;
        }
        $visible = $this->readable($scope, $this->store->favourites($userId, $scope->spaceIds(), self::MAX_FAVOURITE_SCAN), $this->favourites['max']);
        if (count($visible) >= $this->favourites['max']) {
            return WikiFavouriteResult::LimitReached;
        }

        // @igor-ignore - a write to the database through a stateless service (rows, not service state); nothing is kept between requests.
        return $this->store->addFavourite($page, $userId);
    }

    /**
     * Removes one of the viewer's favourites by its id, whatever its page (audit VC-1). The same outcome whether the
     * row existed, belonged to someone else or never existed, so the call reveals nothing.
     */
    public function removeFavouriteById(UserInterface $viewer, string $favouriteId): void
    {
        $userId = WikiViewerScope::idOf($viewer);
        if ($this->favourites['enabled'] && $userId !== null) {
            // @igor-ignore - a write to the database through a stateless service (rows, not service state); nothing is kept between requests.
            $this->store->removeFavouriteById($favouriteId, $userId);
        }
    }

    /**
     * Records that the viewer opened the page (recents on). Best effort, never a 500 (audit VC-2): the page view is a
     * read, so any failure of this bookkeeping is logged and the page is shown anyway (the visit is recorded on the next
     * one), except a lock conflict inside a transaction the caller had open: it may have rolled back the caller's work
     * too, so it propagates.
     */
    public function recordVisit(UserInterface $viewer, WikiPage $page): void
    {
        $userId = WikiViewerScope::idOf($viewer);
        if (!$this->recents['enabled'] || $userId === null) {
            return;
        }

        $nested = $this->store->inTransaction();
        try {
            // @igor-ignore - a write to the database through a stateless service (rows, not service state); nothing is kept between requests.
            $this->store->recordVisit($page, $userId, $this->recents['keep'], $this->recents['retention_days']);
        } catch (TransientStorageFailure|RetryableException $failure) {
            if ($nested) {
                throw $failure;
            }
            $this->logger?->warning('Wiki: a page visit was not recorded (lock conflict); the recents list catches up on the next visit.', ['bundle' => 'nowo_wiki', 'exception_class' => $failure::class]);
        } catch (Throwable $failure) {
            $this->logger?->error('Wiki: a page visit was not recorded.', ['bundle' => 'nowo_wiki', 'exception_class' => $failure::class]);
        }
    }

    /**
     * @param list<WikiPageFavourite|WikiPageVisit> $rows
     *
     * @return list<WikiPageFavourite|WikiPageVisit>
     */
    private function readable(WikiViewerScope $scope, array $rows, int $shown, ?WikiPage $current = null): array
    {
        $readable = [];
        foreach ($rows as $row) {
            if (count($readable) >= $shown) {
                break;
            }
            $page = $row->getPage();
            if (($current instanceof WikiPage && $page->getId() === $current->getId()) || !$scope->canRead($page)) {
                continue;
            }
            $readable[] = $row;
        }

        return $readable;
    }
}
