<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Port;

use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiAccessPolicyInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Asks a framework-free {@see WikiAccessPolicyInterface} (1.32.0) where the bundle asks the deprecated interface.
 *
 * @internal
 */
final readonly class WikiAccessPolicyGate implements WikiAccessCheckerInterface
{
    public function __construct(private WikiAccessPolicyInterface $policy)
    {
    }

    public function canAccess(?UserInterface $user = null): bool
    {
        return $this->policy->canAccess(WikiUserRefs::fromUserOrNull($user));
    }

    public function canList(?UserInterface $user = null): bool
    {
        return $this->policy->canList(WikiUserRefs::fromUserOrNull($user));
    }

    public function canCreate(?UserInterface $user = null): bool
    {
        return $this->policy->canCreate(WikiUserRefs::fromUserOrNull($user));
    }

    public function canEdit(?UserInterface $user = null): bool
    {
        return $this->policy->canEdit(WikiUserRefs::fromUserOrNull($user));
    }

    public function canViewHistory(?UserInterface $user = null): bool
    {
        return $this->policy->canViewHistory(WikiUserRefs::fromUserOrNull($user));
    }

    public function canArchive(?UserInterface $user = null): bool
    {
        return $this->policy->canArchive(WikiUserRefs::fromUserOrNull($user));
    }

    public function canAskAi(?UserInterface $user = null): bool
    {
        return $this->policy->canAskAi(WikiUserRefs::fromUserOrNull($user));
    }

    public function canImport(?UserInterface $user = null): bool
    {
        return $this->policy->canImport(WikiUserRefs::fromUserOrNull($user));
    }

    public function canExport(?UserInterface $user = null): bool
    {
        return $this->policy->canExport(WikiUserRefs::fromUserOrNull($user));
    }
}
