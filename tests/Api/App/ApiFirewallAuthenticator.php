<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\App;

use Nowo\WikiBundle\Api\WikiApiCredential;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * The host's stateless firewall on the API, as Beacon's: a valid token makes its person the security user; a bad one
 * lets the request through anonymous, and the bundle answers.
 */
final class ApiFirewallAuthenticator extends AbstractAuthenticator
{
    public function __construct(private readonly ApiTestHost $host)
    {
    }

    public function supports(Request $request): bool
    {
        return WikiApiCredential::fromRequest($request) !== null;
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $user = $this->host->userOf((string) WikiApiCredential::fromRequest($request));
        if (!$user instanceof UserInterface) {
            throw new CustomUserMessageAuthenticationException('Invalid token.');
        }

        return new SelfValidatingPassport(new UserBadge($user->getUserIdentifier(), static fn (): UserInterface => $user));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return null;
    }
}
