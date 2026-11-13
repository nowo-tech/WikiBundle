<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use Nowo\WikiBundle\ValueObject\WikiUserRef;

/**
 * Finds the space a URL slug names for a user, framework-free (1.34.0): the twin of the deprecated
 * {@see WikiSpaceLocatorInterface} (alias either interface to your service). Return the space's id; the bundle loads
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
