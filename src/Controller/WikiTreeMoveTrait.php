<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Controller;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Service\WikiPageMoveScope;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Who may move a page (1.14, shared by the tree endpoint and the "Move" form): {@see WikiAction::View} and
 * {@see WikiAction::Edit} on the page, Edit on its current parent and on the new parent (the space for the top
 * level). Callers pass a page and a new parent the user may read with every ancestor ({@see self::isReadableBranch()};
 * the endpoint answers 404 otherwise, the form only offers such pages). Moving a page moves its whole sub-tree,
 * descendants the actor cannot see included (as in Outline and Notion; audit DI-I3): their readers become those of
 * the new branch.
 *
 * @property WikiSpaceGuard $spaceGuard
 */
trait WikiTreeMoveTrait
{
    /**
     * @param WikiPage|null $from the page's parent as read once by the caller; the same id goes into the
     *                            {@see WikiPageMoveScope}, so the mover re-checks the parent checked here (DI-I6)
     *
     * @throws AccessDeniedHttpException when an edit right is missing
     */
    private function requireMovable(UserInterface $user, WikiSpace $space, WikiPage $page, ?WikiPage $from, ?WikiPage $parent): void
    {
        foreach ([$page, $from, $parent] as $node) {
            if (!$this->spaceGuard->isGranted($user, WikiAction::Edit, $space, $node)) {
                throw new AccessDeniedHttpException('Access denied.');
            }
        }
    }

    /**
     * Whether the user may read the page and every page above it, as the sidebar tree decides (audit DI-L1): a hidden
     * page hides its whole sub-tree. A parent cycle in the data resolves to hidden.
     */
    private function isReadableBranch(UserInterface $user, WikiSpace $space, WikiPage $page): bool
    {
        $seen = [];
        for ($node = $page; $node instanceof WikiPage; $node = $node->getParent()) {
            if (isset($seen[$node->getId()]) || !$this->spaceGuard->isGranted($user, WikiAction::View, $space, $node)) {
                return false;
            }
            $seen[$node->getId()] = true;
        }

        return true;
    }

    /**
     * Ids of the pages of the list the user may read with every ancestor (the sidebar's rule), for
     * {@see WikiPageMoveScope}.
     *
     * @param list<WikiPage> $pages
     *
     * @return array<string, true>
     */
    private function readableIds(UserInterface $user, WikiSpace $space, array $pages): array
    {
        $ids = [];
        foreach ($pages as $page) {
            if ($this->isReadableBranch($user, $space, $page)) {
                $ids[$page->getId()] = true;
            }
        }

        return $ids;
    }

    /**
     * Whether the user may move pages of the space at all (Edit on the space: the "Move" action and a movable tree).
     */
    private function canMoveIn(UserInterface $user, WikiSpace $space): bool
    {
        return $this->spaceGuard->isGranted($user, WikiAction::Edit, $space);
    }
}
