<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Security;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Symfony\Component\Security\Core\User\UserInterface;

use function in_array;

/**
 * 1.50: decorates the space checker the application configured (`WikiSpaceAccessCheckerInterface`) with the rule of an
 * archived space: read-only for everyone, and open only to the people who may manage it.
 *
 * - `View`, `History`, `Export`, `ViewComments`: only for a person the inner checker grants `Manage` on the space (and
 *   the action itself). Everyone else meets the space as a missing one: it leaves every list, tree, search, link picker,
 *   favourite and recent built on the checker.
 * - `Manage` on the space itself (rename, unarchive, purge): as the inner checker decides. On a page: refused.
 * - Every other action (create, edit, archive, restore, delete, purge from the trash, import, comment, …): refused.
 *
 * An active space is decided by the inner checker alone. Stateless.
 */
final readonly class WikiArchivedSpaceAccessChecker implements WikiSpaceAccessCheckerInterface
{
    /** The read actions a manager keeps on an archived space. */
    private const READ_ACTIONS = [WikiAction::View, WikiAction::History, WikiAction::Export, WikiAction::ViewComments];

    public function __construct(private WikiSpaceAccessCheckerInterface $inner)
    {
    }

    public function isGranted(UserInterface $user, WikiAction $action, WikiSpace $space, ?WikiPage $page = null): bool
    {
        if (!$space->isArchived()) {
            return $this->inner->isGranted($user, $action, $space, $page);
        }
        if ($action === WikiAction::Manage) {
            return !$page instanceof WikiPage && $this->inner->isGranted($user, WikiAction::Manage, $space);
        }

        return in_array($action, self::READ_ACTIONS, true)
            && $this->inner->isGranted($user, WikiAction::Manage, $space)
            && $this->inner->isGranted($user, $action, $space, $page);
    }
}
