<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Controller;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Who may move a page (1.14, shared by the tree endpoint and the "Move" form): {@see WikiAction::View} and
 * {@see WikiAction::Edit} on the page, Edit on its current parent and on the new parent (the space for the top
 * level). Callers pass a new parent the user may read (the endpoint answers 404 otherwise; the form only offers
 * readable pages).
 *
 * @property WikiSpaceGuard $spaceGuard
 */
trait WikiTreeMoveTrait
{
    /**
     * @throws AccessDeniedHttpException when an edit right is missing
     */
    private function requireMovable(UserInterface $user, WikiSpace $space, WikiPage $page, ?WikiPage $parent): void
    {
        $from = $page->getParent();
        foreach ([$page, $from, $parent] as $node) {
            if (!$this->spaceGuard->isGranted($user, WikiAction::Edit, $space, $node)) {
                throw new AccessDeniedHttpException('Access denied.');
            }
        }
    }

    /**
     * Whether the user may move pages of the space at all (Edit on the space: the "Move" action and a movable tree).
     */
    private function canMoveIn(UserInterface $user, WikiSpace $space): bool
    {
        return $this->spaceGuard->isGranted($user, WikiAction::Edit, $space);
    }
}
