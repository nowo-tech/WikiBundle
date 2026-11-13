<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Port;

use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Nowo\WikiBundle\Service\WikiSpaceIdLocatorInterface;
use Nowo\WikiBundle\Service\WikiSpaceLocatorInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Asks a framework-free {@see WikiSpaceIdLocatorInterface} (1.32.0) and loads the space it names. A space whose id or
 * slug is not exactly what was returned and asked (REQ-SEC-006 items 2 and 4) is refused: null, the page answers 404.
 *
 * @internal
 */
final readonly class WikiSpaceIdLocatorGate implements WikiSpaceLocatorInterface
{
    public function __construct(
        private WikiSpaceIdLocatorInterface $locator,
        private WikiSpaceRepositoryInterface $spaces,
    ) {
    }

    public function locate(string $slug, UserInterface $user): ?WikiSpace
    {
        $id = $this->locator->locate($slug, WikiUserRefs::fromUser($user));
        if ($id === null) {
            return null;
        }
        $space = $this->spaces->findById($id);

        return $space instanceof WikiSpace && $space->getId() === $id && $space->getSlug() === $slug ? $space : null;
    }
}
