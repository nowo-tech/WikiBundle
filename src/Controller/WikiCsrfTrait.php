<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Controller;

use Symfony\Component\HttpFoundation\Request;

/**
 * CSRF validation for wiki manage POST actions.
 */
trait WikiCsrfTrait
{
    private function isValidCsrf(Request $request, string $tokenId): bool
    {
        $token = $request->request->getString('_token');

        return $this->isCsrfTokenValid($tokenId, $token);
    }
}
