<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

use function array_key_exists;
use function array_values;

/**
 * 1.46 ({@see WikiEvents::SEARCH_KIT_SPACES}): the host-rules hook of the wiki's SearchKit group, per space. Dispatched
 * once per search, results page and recent check, **before the SQL**, with the spaces the wiki itself lets the viewer
 * read (role gate, tenant / team resolver, `View` on the space, the SearchKit scope and the `space:` filters). A listener
 * leaves spaces out with {@see self::exclude()} or {@see self::excludeAll()}: their pages are then never read, matched,
 * counted or shown, so the totals and the "see all" link stay those of the remaining spaces (no count oracle).
 *
 * Narrowing only: there is no way to add a space or to undo an exclusion, so an exclusion wins whatever the listener
 * order, and the event cannot be stopped (every listener runs). Per-page rules go to SearchKit's own item visibility
 * event (`nowo_search_kit.item_visibility`, subjects of type `wiki` with the page id), dispatched after the wiki's page
 * checks. Made for one dispatch and dropped with it: no state outlives the search (FrankenPHP worker mode).
 */
final class WikiSearchSpacesEvent extends Event
{
    /** @var array<string, true> space id => excluded */
    private array $excluded = [];

    /** @var array<string, WikiSearchSpace> space id => space */
    private readonly array $byId;

    /**
     * @param string $userIdentifier the viewer (SearchKit's user identifier)
     * @param string|null $scope the SearchKit scope id, or null
     * @param string|null $locale the UI locale, or null
     * @param list<WikiSearchSpace> $spaces the spaces the wiki lets the viewer read for this search
     * @param list<string> $tenants 1.47: the current tenant keys of the search (empty with `search_kit_provider.tenant: all`)
     */
    public function __construct(
        public readonly string $userIdentifier,
        public readonly ?string $scope,
        public readonly ?string $locale,
        array $spaces,
        public readonly array $tenants = [],
    ) {
        $byId = [];
        foreach ($spaces as $space) {
            $byId[$space->space->id] = $space;
        }
        $this->byId = $byId;
    }

    /**
     * The spaces of the search, in the wiki's order.
     *
     * @return list<WikiSearchSpace>
     */
    public function spaces(): array
    {
        return array_values($this->byId);
    }

    /** Leaves a space out of the search; an id that is not one of {@see self::spaces()} is ignored. */
    public function exclude(string $spaceId): void
    {
        if (array_key_exists($spaceId, $this->byId)) {
            // @igor-ignore - Request-scoped event, created per dispatch and excluded from the services; no worker state.
            $this->excluded[$spaceId] = true;
        }
    }

    /** Leaves every space out: the search finds nothing. */
    public function excludeAll(): void
    {
        foreach ($this->byId as $id => $unused) {
            // @igor-ignore - Request-scoped event, created per dispatch and excluded from the services; no worker state.
            $this->excluded[$id] = true;
        }
    }

    public function isExcluded(string $spaceId): bool
    {
        return isset($this->excluded[$spaceId]);
    }

    /** Never stopped: every listener runs, and an exclusion of any of them wins. */
    public function isPropagationStopped(): bool
    {
        return false;
    }

    /** Ignored (see {@see self::isPropagationStopped()}). */
    public function stopPropagation(): void
    {
    }
}
