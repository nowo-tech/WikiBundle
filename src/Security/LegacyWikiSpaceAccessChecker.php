<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Security;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolverInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Service\ResetInterface;
use WeakMap;

use function count;

/**
 * Default space checker: the 1.x behaviour (the user-level {@see WikiAccessCheckerInterface} decision plus
 * membership of the space through {@see WikiSpaceAccessResolverInterface}), so applications that configure no space
 * checker keep working unchanged.
 *
 * 1.13 (audit CV-L1): the spaces of a user are read once per request — a page with many links to one space asks
 * {@see WikiSpaceAccessResolverInterface::listSpacesForUser()} once, not once per link. The memo is keyed by user
 * object and identifier, bounded, and cleared by {@see self::reset()} (`kernel.reset`), so a FrankenPHP worker never
 * carries it over. Call {@see self::reset()} after changing memberships inside one request or in a long-running loop.
 */
final class LegacyWikiSpaceAccessChecker implements WikiSpaceAccessCheckerInterface, ResetInterface
{
    /** Users memoised at most (the memo is emptied beyond it). */
    public const MAX_MEMO = 64;

    /** @var WeakMap<UserInterface, array{0: string, 1: array<string, true>}> user => [identifier, ids of its spaces] (this request) */
    private WeakMap $memberships;

    /**
     * @param WikiAccessCheckerInterface $accessChecker user-level checker of 1.x
     * @param WikiSpaceAccessResolverInterface $spaceAccessResolver spaces the user belongs to
     */
    public function __construct(
        private readonly WikiAccessCheckerInterface $accessChecker,
        private readonly WikiSpaceAccessResolverInterface $spaceAccessResolver,
    ) {
        $this->memberships = new WeakMap();
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
            // 1.14 trash: deleting is archiving with sub-pages; purging needs the space-management role (D-W5).
            WikiAction::Delete => $this->accessChecker->canArchive($user),
            WikiAction::Purge  => $this->accessChecker->canImport($user),
        };
    }

    /**
     * Forgets the memberships read during the request (`kernel.reset`).
     */
    public function reset(): void
    {
        $this->memberships = new WeakMap();
    }

    /** The 1.x membership rule: the space is one of the user's spaces. */
    private function isMember(UserInterface $user, WikiSpace $space): bool
    {
        // 1.13.1 (CV-I9): one entry per user object, released with it (WeakMap: no reused object ids in long loops);
        // the identifier is checked too, and the memo is bounded for processes that never reset.
        $entry = $this->memberships[$user] ?? null;
        if ($entry === null || $entry[0] !== $user->getUserIdentifier()) {
            if (count($this->memberships) >= self::MAX_MEMO) {
                $this->memberships = new WeakMap();
            }
            $ids = [];
            foreach ($this->spaceAccessResolver->listSpacesForUser($user) as $accessible) {
                $ids[$accessible->getId()] = true;
            }
            $entry                    = [$user->getUserIdentifier(), $ids];
            $this->memberships[$user] = $entry;
        }

        return isset($entry[1][$space->getId()]);
    }
}
