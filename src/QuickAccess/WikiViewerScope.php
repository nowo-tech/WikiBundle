<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\QuickAccess;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Security\WikiPageVisibility;
use Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface;
use Nowo\WikiBundle\User\WikiUserId;
use Nowo\WikiBundle\ValueObject\WikiStoredUserRef;
use Symfony\Component\Security\Core\User\UserInterface;

use function array_map;

/**
 * 1.45 (audit VC-9, owner decision A): what one viewer may see in the per-viewer lists of one view — favourites,
 * recents and backlinks — computed once per view by {@see WikiReadableSpaces::forView()} and passed to each list:
 *
 * - the person's id ({@see WikiUserId}, null when the user has none or it is not a valid stored id, audit VC-2);
 * - the spaces of the **current tenant** the viewer may read now (role gate, tenant / team resolver, View on the space,
 *   and the same tenant key as the space being viewed);
 * - the page rule ({@see WikiPageVisibility}: View on the page and every ancestor), with the decisions of this view.
 *
 * A value object made for one view and dropped with it: never a service, never kept between requests (worker mode).
 */
final class WikiViewerScope
{
    /** @var array<string, bool> page id => readable with its ancestors, for this view only */
    private array $decisions = [];

    /**
     * @param list<WikiSpace> $spaces the readable spaces of the current tenant
     */
    public function __construct(
        public readonly UserInterface $viewer,
        public readonly array $spaces,
        private readonly WikiSpaceAccessCheckerInterface $checker,
    ) {
    }

    /** The viewer's id as stored in the favourite / visit rows, or null (no lists for this viewer). */
    public function userId(): ?string
    {
        return self::idOf($this->viewer);
    }

    /** A user's id when it is a valid stored id, else null (audit VC-2). */
    public static function idOf(object $user): ?string
    {
        $id = WikiUserId::of($user);

        return $id !== null && WikiStoredUserRef::isValid($id) ? $id : null;
    }

    /**
     * The ids of the spaces in scope.
     *
     * @return list<string>
     */
    public function spaceIds(): array
    {
        return array_map(static fn (WikiSpace $space): string => $space->getId(), $this->spaces);
    }

    /** Whether the page is in one of the scope's spaces and the viewer may read it with every ancestor. */
    public function canRead(WikiPage $page): bool
    {
        $spaceId = $page->getSpace()->getId();
        foreach ($this->spaces as $space) {
            if ($space->getId() === $spaceId) {
                return !$page->isArchived() && WikiPageVisibility::isReadable($this->checker, $this->viewer, $space, $page, $this->decisions);
            }
        }

        return false;
    }
}
