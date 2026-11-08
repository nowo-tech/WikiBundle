<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Hook;

use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * 1.24 (spec 218): the default plan gate: 403 `PLAN_001` until the host sets `api.hooks.plan_gate`.
 */
final readonly class DenyingWikiApiPlanGate implements WikiApiPlanGateInterface
{
    public function allowsWiki(UserInterface $user, WikiApiWiki $wiki): bool
    {
        return false;
    }
}
