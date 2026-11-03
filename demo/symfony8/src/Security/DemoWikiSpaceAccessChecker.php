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
 */
#[AsDecorator(LegacyWikiSpaceAccessChecker::class)]
final readonly class DemoWikiSpaceAccessChecker implements WikiSpaceAccessCheckerInterface, ResetInterface
{
    public function __construct(#[AutowireDecorated] private WikiSpaceAccessCheckerInterface $inner)
    {
    }

    public function isGranted(UserInterface $user, WikiAction $action, WikiSpace $space, ?WikiPage $page = null): bool
    {
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
