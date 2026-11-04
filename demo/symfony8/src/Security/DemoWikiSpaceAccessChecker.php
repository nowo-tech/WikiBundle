<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Security\LegacyWikiSpaceAccessChecker;
use Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Demo of the 1.14 trash permission hook: the owner of a personal space may also purge its trash (WikiAction::Purge),
 * on top of the 1.x mapping (purge = import / manage roles), so the demo user can try "Delete for good".
 *
 * 1.16: the comment rights are denied by the 1.x mapping (default deny); the demo maps them like Beacon does:
 * ViewComments → View, Comment → Edit, ResolveComment and ModerateComments → Archive (the demo's "manage"), in the
 * Product space only.
 */
#[AsDecorator(LegacyWikiSpaceAccessChecker::class)]
final readonly class DemoWikiSpaceAccessChecker implements WikiSpaceAccessCheckerInterface, ResetInterface
{
    public function __construct(#[AutowireDecorated] private WikiSpaceAccessCheckerInterface $inner)
    {
    }

    public function isGranted(UserInterface $user, WikiAction $action, WikiSpace $space, ?WikiPage $page = null): bool
    {
        $mapped = match ($action) {
            WikiAction::ViewComments                                 => WikiAction::View,
            WikiAction::Comment                                      => WikiAction::Edit,
            WikiAction::ResolveComment, WikiAction::ModerateComments => WikiAction::Archive,
            default                                                  => null,
        };
        if ($mapped !== null) {
            // Comments are on in the Product space only (the Engineering pages keep their server-rendered read view
            // for the 1.13 rendering checks of the e2e suite).
            return $space->getSlug() === 'product' && $this->inner->isGranted($user, $mapped, $space, $page);
        }

        if ($action === WikiAction::Purge && $user instanceof User && $space->getOwnerScopeType() === WikiSpaceOwnerScope::User
            && $space->getOwnerScopeId() === (string) $user->getId()) {
            return $this->inner->isGranted($user, WikiAction::Delete, $space, $page);
        }

        return $this->inner->isGranted($user, $action, $space, $page);
    }

    /** The decorated checker is reset between requests (kernel.reset, worker mode): pass it on. */
    public function reset(): void
    {
        if ($this->inner instanceof ResetInterface || method_exists($this->inner, 'reset')) {
            $this->inner->reset();
        }
    }
}
