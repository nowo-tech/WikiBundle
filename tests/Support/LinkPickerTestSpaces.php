<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Support;

use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolverInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use function in_array;

/**
 * The spaces of every user of the link picker tests.
 */
final readonly class LinkPickerTestSpaces implements WikiSpaceAccessResolverInterface
{
    /**
     * @param list<WikiSpace> $spaces
     */
    public function __construct(private array $spaces)
    {
    }

    public function listSpacesForUser(UserInterface $user): array
    {
        return $this->spaces;
    }

    public function canAccessSpace(UserInterface $user, WikiSpace $space): bool
    {
        return in_array($space, $this->spaces, true);
    }
}
