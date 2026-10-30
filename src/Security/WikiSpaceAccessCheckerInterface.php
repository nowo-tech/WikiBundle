<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Security;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Space- and page-aware access decision (1.9).
 *
 * Every manage route asks it with the resolved space (and page) before doing anything: a denied
 * {@see WikiAction::View} answers 404 (a forbidden space is indistinguishable from a missing one), any other denied
 * action on a readable space answers 403. Override the {@see WikiSpaceAccessCheckerInterface} alias with a host
 * service to decide per tenant; the default {@see LegacyWikiSpaceAccessChecker} keeps the 1.x user-only decision.
 */
interface WikiSpaceAccessCheckerInterface
{
    /**
     * Tells whether the user may perform the action on the space (and page, when given).
     *
     * @param UserInterface $user signed-in user
     * @param WikiAction $action requested action
     * @param WikiSpace $space space the action targets
     * @param WikiPage|null $page page the action targets, when it is page-level
     *
     * @return bool true when allowed
     */
    public function isGranted(UserInterface $user, WikiAction $action, WikiSpace $space, ?WikiPage $page = null): bool;
}
