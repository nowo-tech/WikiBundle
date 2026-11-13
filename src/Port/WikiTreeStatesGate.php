<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Port;

use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Tree\WikiTreeStatesInterface;
use Nowo\WikiBundle\Tree\WikiTreeStateStoreInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use function is_string;

/**
 * Asks a framework-free {@see WikiTreeStatesInterface} (1.32.0) with the space's id; non-string page ids are dropped.
 *
 * @internal
 */
final readonly class WikiTreeStatesGate implements WikiTreeStateStoreInterface
{
    public function __construct(private WikiTreeStatesInterface $states)
    {
    }

    public function expanded(UserInterface $user, WikiSpace $space): array
    {
        $ids = [];
        foreach ($this->states->expanded(WikiUserRefs::fromUser($user), $space->getId()) as $id) {
            if (is_string($id)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function setExpanded(UserInterface $user, WikiSpace $space, string $pageId, bool $expanded): void
    {
        // @igor-ignore - Host store write (the remembered state lives in the host's store); the gate keeps no state.
        $this->states->setExpanded(WikiUserRefs::fromUser($user), $space->getId(), $pageId, $expanded);
    }
}
