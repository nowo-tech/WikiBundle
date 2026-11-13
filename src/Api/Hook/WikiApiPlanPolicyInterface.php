<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Hook;

use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Nowo\WikiBundle\ValueObject\WikiUserRef;

/**
 * Whether the person's plan includes the wiki, framework-free (1.33.0): the twin of the deprecated
 * {@see WikiApiPlanGateInterface} (`nowo_wiki.api.hooks.plan_gate` takes either).
 */
interface WikiApiPlanPolicyInterface
{
    public function allowsWiki(WikiUserRef $user, WikiApiWiki $wiki): bool;
}
