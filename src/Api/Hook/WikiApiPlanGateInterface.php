<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Hook;

use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * 1.24 (spec 218 FR-018): whether the wiki module is in the plan paying for this wiki. Asked only after the wiki is
 * known to be reachable, so a plan answer never reveals that a wiki exists.
 */
interface WikiApiPlanGateInterface
{
    public function allowsWiki(UserInterface $user, WikiApiWiki $wiki): bool;
}
