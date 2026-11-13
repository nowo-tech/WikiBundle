<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Port;

use Nowo\WikiBundle\Avatar\WikiAvatarKey;
use Nowo\WikiBundle\Avatar\WikiUserAvatarResolverInterface;
use Nowo\WikiBundle\Avatar\WikiUserAvatarSourceInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use function array_key_exists;
use function array_values;

/**
 * Asks a framework-free {@see WikiUserAvatarSourceInterface} (1.32.0) and keys its pictures like the deprecated
 * interface ({@see WikiAvatarKey}).
 *
 * @internal
 */
final readonly class WikiUserAvatarSourceGate implements WikiUserAvatarResolverInterface
{
    public function __construct(private WikiUserAvatarSourceInterface $source)
    {
    }

    public function urlFor(UserInterface $user, int $size): ?string
    {
        return $this->source->urlFor(WikiUserRefs::fromUser($user), $size);
    }

    public function urlsFor(array $users, int $size): array
    {
        $users = array_values($users);
        $refs  = [];
        foreach ($users as $user) {
            $refs[] = WikiUserRefs::fromUser($user);
        }
        $urls  = $this->source->urlsFor($refs, $size);
        $keyed = [];
        foreach ($users as $index => $user) {
            if (array_key_exists($index, $urls)) {
                $keyed[WikiAvatarKey::of($user)] = $urls[$index];
            }
        }

        return $keyed;
    }
}
