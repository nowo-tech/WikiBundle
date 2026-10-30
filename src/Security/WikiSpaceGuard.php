<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Security;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Service\WikiSpaceLocatorInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Resolves the space of a route and enforces {@see WikiSpaceAccessCheckerInterface} (1.9): a space that is missing
 * or not readable answers 404, a denied action on a readable space answers 403.
 */
final readonly class WikiSpaceGuard
{
    /**
     * @param WikiSpaceLocatorInterface $locator finds (or provisions) the space of a slug
     * @param WikiSpaceAccessCheckerInterface $checker space- and page-aware decision
     */
    public function __construct(
        private WikiSpaceLocatorInterface $locator,
        private WikiSpaceAccessCheckerInterface $checker,
    ) {
    }

    /**
     * Returns the readable space of the slug.
     *
     * @throws NotFoundHttpException when the space is missing or the user may not view it
     */
    public function requireSpace(UserInterface $user, string $slug): WikiSpace
    {
        $space = $this->locator->locate($slug, $user);
        if (!$space instanceof WikiSpace || !$this->checker->isGranted($user, WikiAction::View, $space)) {
            throw new NotFoundHttpException('Space not found.');
        }

        return $space;
    }

    /**
     * Denies the action with a 403 (404 for {@see WikiAction::View}, so a forbidden page reads as missing).
     *
     * @throws AccessDeniedHttpException|NotFoundHttpException when the action is denied
     */
    public function requireGranted(UserInterface $user, WikiAction $action, WikiSpace $space, ?WikiPage $page = null): void
    {
        if ($this->checker->isGranted($user, $action, $space, $page)) {
            return;
        }

        if ($action === WikiAction::View) {
            throw new NotFoundHttpException('Page not found.');
        }

        throw new AccessDeniedHttpException('Access denied.');
    }

    /** Tells whether the action is allowed. */
    public function isGranted(UserInterface $user, WikiAction $action, WikiSpace $space, ?WikiPage $page = null): bool
    {
        return $this->checker->isGranted($user, $action, $space, $page);
    }

    /**
     * Every action's decision, keyed by the action value (for templates: {@code permissions.edit}, …).
     *
     * @return array<string, bool>
     */
    public function grants(UserInterface $user, WikiSpace $space, ?WikiPage $page = null): array
    {
        $grants = [];
        foreach (WikiAction::cases() as $action) {
            $grants[$action->value] = $this->checker->isGranted($user, $action, $space, $page);
        }

        return $grants;
    }
}
