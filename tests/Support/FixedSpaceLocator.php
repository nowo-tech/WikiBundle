<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Support;

use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Service\WikiSpaceLocatorInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Locator returning the given spaces by slug.
 */
final readonly class FixedSpaceLocator implements WikiSpaceLocatorInterface
{
    /** @var list<WikiSpace> */
    private array $spaces;

    public function __construct(WikiSpace ...$spaces)
    {
        $this->spaces = array_values($spaces);
    }

    public function locate(string $slug, UserInterface $user): ?WikiSpace
    {
        foreach ($this->spaces as $space) {
            if ($space->getSlug() === $slug) {
                return $space;
            }
        }

        return null;
    }
}
