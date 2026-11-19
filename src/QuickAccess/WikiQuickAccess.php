<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\QuickAccess;

use Doctrine\DBAL\Exception\RetryableException;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Security\WikiPageVisibility;
use Nowo\WikiBundle\User\WikiUserId;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use function count;

/**
 * 1.45: favourites (`favourites.enabled`) and recently viewed pages (`recents.enabled`) of the person viewing the wiki.
 *
 * Every list is built again for the viewer on every call, in this order, before anything is cut to the shown size:
 *
 * 1. the person: rows of their own id ({@see WikiUserId}: the user's `getId()`; a user without one has no lists);
 * 2. the tenant and spaces: only the spaces the viewer may read now ({@see WikiReadableSpaces}) reach the SQL, and only
 *    live pages (archived and trashed pages are left out by the query);
 * 3. the page: {@see WikiPageVisibility} (View on the page and every ancestor), the page view's own rule.
 *
 * A page the viewer lost access to is therefore never listed, and its row is kept (it shows again if access comes
 * back). Rows read per list are bounded (`favourites.max`, `recents.keep`). Stateless (worker mode).
 */
final readonly class WikiQuickAccess
{
    /**
     * @param array{enabled: bool, max: int, shown: int} $favourites `favourites` options
     * @param array{enabled: bool, keep: int, shown: int} $recents `recents` options
     */
    public function __construct(
        private WikiQuickAccessStore $store,
        private WikiReadableSpaces $spaces,
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
     * The viewer's favourites they may still read, the newest first, at most `favourites.shown`.
     *
     * @return list<WikiPage>
     */
    public function favourites(UserInterface $viewer): array
    {
        $userId = WikiUserId::of($viewer);
        if (!$this->favourites['enabled'] || $userId === null) {
            return [];
        }

        return $this->readable($viewer, $this->store->favouritePages($userId, $this->spaces->of($viewer), $this->favourites['max']), $this->favourites['shown']);
    }

    /**
     * The pages the viewer opened last and may still read, the last first, without `$current`, at most `recents.shown`.
     *
     * @return list<WikiPage>
     */
    public function recents(UserInterface $viewer, ?WikiPage $current = null): array
    {
        $userId = WikiUserId::of($viewer);
        if (!$this->recents['enabled'] || $userId === null) {
            return [];
        }

        return $this->readable($viewer, $this->store->recentPages($userId, $this->spaces->of($viewer), $this->recents['keep']), $this->recents['shown'], $current);
    }

    /** Whether the page is one of the viewer's favourites (false when favourites are off). */
    public function isFavourite(UserInterface $viewer, WikiPage $page): bool
    {
        $userId = WikiUserId::of($viewer);

        return $this->favourites['enabled'] && $userId !== null && $this->store->isFavourite($page, $userId);
    }

    /** Whether the viewer can keep favourites at all (favourites on, a user with an id). */
    public function canKeepFavourites(UserInterface $viewer): bool
    {
        return $this->favourites['enabled'] && WikiUserId::of($viewer) !== null;
    }

    /**
     * Adds the page to, or removes it from, the viewer's favourites. The caller has checked that the viewer may read
     * the page. Lock conflicts propagate (the route answers 409 + CONFLICT_001 / CONFLICT_002).
     */
    public function setFavourite(UserInterface $viewer, WikiPage $page, bool $favourite): WikiFavouriteResult
    {
        $userId = WikiUserId::of($viewer);
        if (!$this->favourites['enabled'] || $userId === null) {
            return WikiFavouriteResult::Unchanged;
        }

        if ($favourite) {
            // @igor-ignore - a write to the database through a stateless service (rows, not service state); nothing is kept between requests.
            return $this->store->addFavourite($page, $userId, $this->favourites['max']);
        }

        // @igor-ignore - a write to the database through a stateless service (rows, not service state); nothing is kept between requests.
        return $this->store->removeFavourite($page, $userId);
    }

    /**
     * Records that the viewer opened the page (recents on). Best effort: the page view is a read, so a lock conflict
     * of this bookkeeping is logged and the page is shown anyway (the visit is recorded on the next one), unless the
     * caller had a transaction open (then it propagates).
     */
    public function recordVisit(UserInterface $viewer, WikiPage $page): void
    {
        $userId = WikiUserId::of($viewer);
        if (!$this->recents['enabled'] || $userId === null) {
            return;
        }

        // Inside a transaction the caller opened, a lock conflict may have rolled back the caller's work too: it is not
        // ours to hide, so it propagates.
        $nested = $this->store->inTransaction();
        try {
            $this->store->recordVisit($page, $userId, $this->recents['keep']);
        } catch (TransientStorageFailure|RetryableException $failure) {
            if ($nested) {
                throw $failure;
            }
            $this->logger?->warning('Wiki: a page visit was not recorded (lock conflict); the recents list catches up on the next visit.', ['bundle' => 'nowo_wiki', 'exception_class' => $failure::class]);
        }
    }

    /**
     * @param list<WikiPage> $pages
     *
     * @return list<WikiPage>
     */
    private function readable(UserInterface $viewer, array $pages, int $shown, ?WikiPage $current = null): array
    {
        $readable  = [];
        $decisions = [];
        foreach ($pages as $page) {
            if (count($readable) >= $shown) {
                break;
            }
            if (($current instanceof WikiPage && $page->getId() === $current->getId())
                || !WikiPageVisibility::isReadable($this->spaces->checker(), $viewer, $page->getSpace(), $page, $decisions)) {
                continue;
            }
            $readable[] = $page;
        }

        return $readable;
    }
}
