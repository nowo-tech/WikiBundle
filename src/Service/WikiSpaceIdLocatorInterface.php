<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use Nowo\WikiBundle\Port\Internal\WikiSpaceLocatorInterface;
use Nowo\WikiBundle\ValueObject\WikiUserRef;

/**
 * Finds the space a URL slug names for a user, framework-free (1.36.0): since 2.0 the only host port; it replaced the removed 1.x
 * `WikiSpaceLocatorInterface` (alias this interface to your service). Return the space's id; the bundle loads
 * it and refuses it unless both its id and its slug are equal byte for byte to what was asked and returned.
 */
interface WikiSpaceIdLocatorInterface
{
    /**
     * @param string $slug the slug of the URL
     * @param WikiUserRef $user the user
     *
     * @return string|null the space id, or null (404)
     */
    public function locate(string $slug, WikiUserRef $user): ?string;
}
