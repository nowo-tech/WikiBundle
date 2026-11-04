<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Panel;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;

use function hash;

/**
 * Default panel state store (1.16): the session of the current request, one flag per user and panel. Without a
 * session (stateless requests) nothing is kept and every panel reads closed.
 */
final readonly class SessionWikiPanelStateStore implements WikiPanelStateStoreInterface
{
    public function __construct(private RequestStack $requestStack)
    {
    }

    public function isOpen(UserInterface $user, string $panel): bool
    {
        if ($this->requestStack->getCurrentRequest()?->hasSession() !== true) {
            return false;
        }

        return $this->requestStack->getSession()->get($this->key($user, $panel)) === true;
    }

    public function setOpen(UserInterface $user, string $panel, bool $open): void
    {
        if ($this->requestStack->getCurrentRequest()?->hasSession() !== true) {
            return;
        }
        $this->requestStack->getSession()->set($this->key($user, $panel), $open);
    }

    private function key(UserInterface $user, string $panel): string
    {
        return 'nowo_wiki.panel.' . hash('sha256', $user->getUserIdentifier()) . '.' . $panel;
    }
}
