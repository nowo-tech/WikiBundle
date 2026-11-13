<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Port;

use Nowo\WikiBundle\Api\Hook\WikiApiPlanGateInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiPlanPolicyInterface;
use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Asks a framework-free {@see WikiApiPlanPolicyInterface} (1.32.0).
 *
 * @internal
 */
final readonly class WikiApiPlanPolicyGate implements WikiApiPlanGateInterface
{
    public function __construct(private WikiApiPlanPolicyInterface $policy)
    {
    }

    public function allowsWiki(UserInterface $user, WikiApiWiki $wiki): bool
    {
        return $this->policy->allowsWiki(WikiUserRefs::fromUser($user), $wiki);
    }
}
