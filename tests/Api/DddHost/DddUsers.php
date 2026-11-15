<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\DddHost;

use Nowo\WikiBundle\User\WikiUserDirectoryInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * The DDD host's user directory (no table): the firewall's user provider and the wiki's `user_directory`.
 *
 * @implements UserProviderInterface<DddUser>
 */
final class DddUsers implements UserProviderInterface, WikiUserDirectoryInterface
{
    /** @var array<string, DddUser> */
    public const TEAMS = ['u-ana' => 'team-a', 'u-bob' => 'team-b', 'U-ana' => 'team-b'];

    public function find(string $id): ?object
    {
        return isset(self::TEAMS[$id]) ? new DddUser($id, self::TEAMS[$id]) : null;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === DddUser::class;
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        return $this->find($identifier) ?? throw new UserNotFoundException();
    }
}
