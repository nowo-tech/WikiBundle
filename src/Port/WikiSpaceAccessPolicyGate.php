<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Port;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiSpaceAccessPolicyInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Asks a framework-free {@see WikiSpaceAccessPolicyInterface} (1.33.0) with snapshots of the space and page.
 *
 * @internal
 */
final readonly class WikiSpaceAccessPolicyGate implements WikiSpaceAccessCheckerInterface
{
    public function __construct(private WikiSpaceAccessPolicyInterface $policy)
    {
    }

    public function isGranted(UserInterface $user, WikiAction $action, WikiSpace $space, ?WikiPage $page = null): bool
    {
        return $this->policy->isGranted(WikiUserRefs::fromUser($user), $action, WikiSnapshots::space($space), WikiSnapshots::pageOrNull($page));
    }
}
