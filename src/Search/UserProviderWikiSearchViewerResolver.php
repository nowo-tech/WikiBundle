<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Search;

use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Default {@see WikiSearchViewerResolverInterface} (1.44): loads the viewer through a Symfony user provider
 * (`search_kit_provider.user_provider`, `security.user_providers` by default: every provider of the security
 * configuration). An unknown identifier, a provider that refuses it or no provider at all answer null. Stateless: the
 * provider is asked on every search (FrankenPHP worker mode, `kernel.reset` off).
 */
final readonly class UserProviderWikiSearchViewerResolver implements WikiSearchViewerResolverInterface
{
    /**
     * @param UserProviderInterface<UserInterface>|null $users the user provider, or null (no viewer: no rows)
     */
    public function __construct(private ?UserProviderInterface $users)
    {
    }

    public function viewer(string $userIdentifier): ?UserInterface
    {
        if (!$this->users instanceof UserProviderInterface || $userIdentifier === '') {
            return null;
        }

        try {
            return $this->users->loadUserByIdentifier($userIdentifier);
        } catch (AuthenticationException) {
            // UserNotFoundException and every other refusal of the provider: nobody.
            return null;
        }
    }
}
