<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Security;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolverInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Default space checker: the 1.x behaviour (the user-level {@see WikiAccessCheckerInterface} decision plus
 * membership of the space through {@see WikiSpaceAccessResolverInterface}), so applications that configure no space
 * checker keep working unchanged.
 */
final readonly class LegacyWikiSpaceAccessChecker implements WikiSpaceAccessCheckerInterface
{
    /**
     * @param WikiAccessCheckerInterface $accessChecker user-level checker of 1.x
     * @param WikiSpaceAccessResolverInterface $spaceAccessResolver spaces the user belongs to
     */
    public function __construct(
        private WikiAccessCheckerInterface $accessChecker,
        private WikiSpaceAccessResolverInterface $spaceAccessResolver,
    ) {
    }

    /**
     * Maps the action onto the 1.x user-level checks, inside a space the user may access.
     *
     * @param UserInterface $user signed-in user
     * @param WikiAction $action requested action
     * @param WikiSpace $space space the action targets
     * @param WikiPage|null $page unused: 1.x decides per user only
     *
     * @return bool true when allowed
     */
    public function isGranted(UserInterface $user, WikiAction $action, WikiSpace $space, ?WikiPage $page = null): bool
    {
        if (!$this->isMember($user, $space)) {
            return false;
        }

        return match ($action) {
            // 1.x gates reading per route (list / history features); membership is the space-level part.
            WikiAction::View                       => true,
            WikiAction::Create                     => $this->accessChecker->canCreate($user),
            WikiAction::Edit                       => $this->accessChecker->canEdit($user),
            WikiAction::Archive                    => $this->accessChecker->canArchive($user),
            WikiAction::History                    => $this->accessChecker->canViewHistory($user),
            WikiAction::Restore                    => $this->accessChecker->canEdit($user) && $this->accessChecker->canViewHistory($user),
            WikiAction::Import, WikiAction::Manage => $this->accessChecker->canImport($user),
            WikiAction::Export                     => $this->accessChecker->canExport($user),
            WikiAction::AskAi                      => $this->accessChecker->canAskAi($user),
        };
    }

    /** The 1.x membership rule: the space is one of the user's spaces. */
    private function isMember(UserInterface $user, WikiSpace $space): bool
    {
        foreach ($this->spaceAccessResolver->listSpacesForUser($user) as $accessible) {
            if ($accessible->getId() === $space->getId()) {
                return true;
            }
        }

        return false;
    }
}
