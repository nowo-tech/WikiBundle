<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Port;

use Nowo\WikiBundle\Panel\WikiPanelStatesInterface;
use Nowo\WikiBundle\Panel\WikiPanelStateStoreInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Asks a framework-free {@see WikiPanelStatesInterface} (1.32.0).
 *
 * @internal
 */
final readonly class WikiPanelStatesGate implements WikiPanelStateStoreInterface
{
    public function __construct(private WikiPanelStatesInterface $states)
    {
    }

    public function isOpen(UserInterface $user, string $panel): bool
    {
        return $this->states->isOpen(WikiUserRefs::fromUser($user), $panel);
    }

    public function setOpen(UserInterface $user, string $panel, bool $open): void
    {
        $this->states->setOpen(WikiUserRefs::fromUser($user), $panel, $open);
    }
}
