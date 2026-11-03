<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tree;

use Nowo\WikiBundle\Entity\WikiSpace;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;

use function array_filter;
use function array_slice;
use function array_values;
use function hash;
use function is_array;
use function is_string;

/**
 * Default fold state store (1.14): the session of the current request, one list per user and space, capped at
 * {@see self::MAX_IDS} ids (the oldest dropped first). Without a session (stateless requests) nothing is kept.
 */
final readonly class SessionWikiTreeStateStore implements WikiTreeStateStoreInterface
{
    /** Most expanded ids kept per user and space. */
    public const MAX_IDS = 500;

    public function __construct(private RequestStack $requestStack)
    {
    }

    public function expanded(UserInterface $user, WikiSpace $space): array
    {
        $session = $this->requestStack->getCurrentRequest()?->hasSession() === true ? $this->requestStack->getSession() : null;
        $ids     = $session?->get($this->key($user, $space));

        return is_array($ids) ? array_values(array_filter($ids, is_string(...))) : [];
    }

    public function setExpanded(UserInterface $user, WikiSpace $space, string $pageId, bool $expanded): void
    {
        if ($this->requestStack->getCurrentRequest()?->hasSession() !== true) {
            return;
        }
        $ids = array_values(array_filter($this->expanded($user, $space), static fn (string $id): bool => $id !== $pageId));
        if ($expanded) {
            $ids[] = $pageId;
        }
        $this->requestStack->getSession()->set($this->key($user, $space), array_slice($ids, -self::MAX_IDS));
    }

    private function key(UserInterface $user, WikiSpace $space): string
    {
        return 'nowo_wiki.tree.' . hash('sha256', $user->getUserIdentifier()) . '.' . $space->getId();
    }
}
