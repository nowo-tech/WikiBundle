<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Hook;

use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Nowo\WikiBundle\Port\Internal\WikiApiPlanGateInterface;
use Nowo\WikiBundle\ValueObject\WikiUserRef;

/**
 * Whether the person's plan includes the wiki, framework-free (1.36.0): since 2.0 the only host port; it replaced the removed 1.x
 * `WikiApiPlanGateInterface` (`nowo_wiki.api.hooks.plan_gate` names your service).
 */
interface WikiApiPlanPolicyInterface
{
    public function allowsWiki(WikiUserRef $user, WikiApiWiki $wiki): bool;
}
