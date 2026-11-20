<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Hook;

use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * 1.24 (spec 218 FR-018): whether the wiki module is in the plan paying for this wiki. Asked only after the wiki is
 * known to be reachable, so a plan answer never reveals that a wiki exists.
 *
 * @deprecated since 1.36, removed in 2.0: implement {@see WikiApiPlanPolicyInterface} instead
 *             (1.49: a host service that still implements it triggers a deprecation notice)
 */
interface WikiApiPlanGateInterface
{
    public function allowsWiki(UserInterface $user, WikiApiWiki $wiki): bool;
}
