<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\App;

use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Nowo\WikiBundle\Service\WikiSpaceLocatorInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A host locator that finds a space by slug only (as Beacon's project locator does) and leaves the access decision to
 * the space checker, so the checker is the only gate of the request.
 */
final readonly class ApiSlugLocator implements WikiSpaceLocatorInterface
{
    public function __construct(private WikiSpaceRepositoryInterface $spaces)
    {
    }

    public function locate(string $slug, UserInterface $user): ?WikiSpace
    {
        return $this->spaces->findFirstBySlug($slug);
    }
}
