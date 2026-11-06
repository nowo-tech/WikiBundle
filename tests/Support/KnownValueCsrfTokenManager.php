<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Support;

use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * CSRF manager of the kernel tests: a token is valid only when it equals `ok-<id>`, so token ids are checked without a
 * session.
 */
final class KnownValueCsrfTokenManager implements CsrfTokenManagerInterface
{
    public function getToken(string $tokenId): CsrfToken
    {
        return new CsrfToken($tokenId, 'ok-' . $tokenId);
    }

    public function refreshToken(string $tokenId): CsrfToken
    {
        return $this->getToken($tokenId);
    }

    public function removeToken(string $tokenId): ?string
    {
        return null;
    }

    public function isTokenValid(CsrfToken $token): bool
    {
        return $token->getValue() === 'ok-' . $token->getId();
    }
}
