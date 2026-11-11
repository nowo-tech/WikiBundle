<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Security;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Service\WikiRequestBoundaryResetter;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolverInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Service\ResetInterface;
use WeakMap;
use WeakReference;

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
 *
 * 1.29.1 (REQ-RUNTIME-002, kernel reset false): `kernel.reset` alone let a worker that never runs the services
 * resetter keep a user's memberships for as long as the host's EntityManager kept that user object managed (the
 * identity map hands the same instance to every later request), so a revoked membership kept granting access. The
 * memo now belongs to one main request: it is dropped when the main request changes, it is not kept at all outside a
 * request (console, Messenger: memberships are read on every call), and
 * {@see WikiRequestBoundaryResetter} also empties it at every request, console and Messenger
 * boundary. Memberships are always re-read through {@see WikiSpaceAccessResolverInterface} (a query), never taken from
 * a collection of the user entity.
 */
final class LegacyWikiSpaceAccessChecker implements WikiSpaceAccessCheckerInterface, ResetInterface
{
    /** Users memoised at most (the memo is emptied beyond it). */
    public const MAX_MEMO = 64;

    /** @var WeakMap<UserInterface, array{0: string, 1: array<string, true>}> user => [identifier, ids of its spaces] (this request) */
    private WeakMap $memberships;

    /** @var WeakReference<Request>|null the main request the memo was read for */
    private ?WeakReference $memoRequest = null;

    /**
     * @param WikiAccessCheckerInterface $accessChecker user-level checker of 1.x
     * @param WikiSpaceAccessResolverInterface $spaceAccessResolver spaces the user belongs to
     * @param RequestStack|null $requestStack 1.29.1: scopes the memo to one main request (null: no memo at all)
     */
    public function __construct(
        private readonly WikiAccessCheckerInterface $accessChecker,
        private readonly WikiSpaceAccessResolverInterface $spaceAccessResolver,
        private readonly ?RequestStack $requestStack = null,
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
            // 1.16 comments: default deny; a host checker grants them (Beacon: View, Edit, manage, manage).
            WikiAction::ViewComments, WikiAction::Comment, WikiAction::ResolveComment, WikiAction::ModerateComments => false,
        };
    }

    /**
     * Forgets the memberships read during the request (`kernel.reset`).
     */
    public function reset(): void
    {
        $this->memberships = new WeakMap();
        $this->memoRequest = null;
    }

    /** The 1.x membership rule: the space is one of the user's spaces. */
    private function isMember(UserInterface $user, WikiSpace $space): bool
    {
        // 1.29.1: outside a main request nothing is memoised; a new main request starts from an empty memo.
        $request = $this->requestStack?->getMainRequest();
        if (!$request instanceof Request) {
            $this->reset();

            return isset($this->spaceIdsOf($user)[$space->getId()]);
        }
        if ($this->memoRequest?->get() !== $request) {
            $this->reset();
            $this->memoRequest = WeakReference::create($request);
        }
        // 1.13.1 (CV-I9): one entry per user object, released with it (WeakMap: no reused object ids in long loops);
        // the identifier is checked too, and the memo is bounded for processes that never reset.
        $entry = $this->memberships[$user] ?? null;
        if ($entry === null || $entry[0] !== $user->getUserIdentifier()) {
            if (count($this->memberships) >= self::MAX_MEMO) {
                $this->memberships = new WeakMap();
            }
            $entry                    = [$user->getUserIdentifier(), $this->spaceIdsOf($user)];
            $this->memberships[$user] = $entry;
        }

        return isset($entry[1][$space->getId()]);
    }

    /**
     * The ids of the user's spaces, read now.
     *
     * @return array<string, true>
     */
    private function spaceIdsOf(UserInterface $user): array
    {
        $ids = [];
        foreach ($this->spaceAccessResolver->listSpacesForUser($user) as $accessible) {
            $ids[$accessible->getId()] = true;
        }

        return $ids;
    }
}
