<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Security;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolverInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Default space checker: the 1.x behaviour (the user-level {@see WikiAccessCheckerInterface} decision plus
 * membership of the space through {@see WikiSpaceAccessResolverInterface}), so applications that configure no space
 * checker keep working unchanged.
 *
 * 1.13 (audit CV-L1): the spaces of a user are read once per request — a page with many links to one space asks
 * {@see WikiSpaceAccessResolverInterface::listSpacesForUser()} once, not once per link. The memo is keyed by user
 * identifier and cleared by {@see self::reset()} (`kernel.reset`), so a FrankenPHP worker never carries it over.
 */
final class LegacyWikiSpaceAccessChecker implements WikiSpaceAccessCheckerInterface, ResetInterface
{
    /** @var array<string, array<string, true>> user identifier => ids of the spaces the user belongs to (this request) */
    private array $memberships = [];

    /**
     * @param WikiAccessCheckerInterface $accessChecker user-level checker of 1.x
     * @param WikiSpaceAccessResolverInterface $spaceAccessResolver spaces the user belongs to
     */
    public function __construct(
        private readonly WikiAccessCheckerInterface $accessChecker,
        private readonly WikiSpaceAccessResolverInterface $spaceAccessResolver,
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

    /**
     * Forgets the memberships read during the request (`kernel.reset`).
     */
    public function reset(): void
    {
        $this->memberships = [];
    }

    /** The 1.x membership rule: the space is one of the user's spaces. */
    private function isMember(UserInterface $user, WikiSpace $space): bool
    {
        $key = $user->getUserIdentifier();
        if (!isset($this->memberships[$key])) {
            $ids = [];
            foreach ($this->spaceAccessResolver->listSpacesForUser($user) as $accessible) {
                $ids[$accessible->getId()] = true;
            }

            $this->memberships[$key] = $ids;
        }

        return isset($this->memberships[$key][$space->getId()]);
    }
}
