<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Support;

use Nowo\WikiBundle\Avatar\WikiAvatarKey;
use Nowo\WikiBundle\Avatar\WikiUserAvatarResolverInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use function array_map;

/**
 * Records every batch (keys only) and answers from a fixed map.
 */
final class RecordingAvatarResolver implements WikiUserAvatarResolverInterface
{
    /** @var list<list<string>> */
    public array $calls = [];

    public int $size = 0;

    /**
     * @param array<string, string|null> $urls
     */
    public function __construct(private readonly array $urls)
    {
    }

    public function urlFor(UserInterface $user, int $size): ?string
    {
        return $this->urlsFor([$user], $size)[WikiAvatarKey::of($user)] ?? null;
    }

    public function urlsFor(array $users, int $size): array
    {
        $this->size    = $size;
        $this->calls[] = array_map(WikiAvatarKey::of(...), $users);
        $answer        = [];
        foreach ($users as $user) {
            $answer[WikiAvatarKey::of($user)] = $this->urls[WikiAvatarKey::of($user)] ?? null;
        }

        return $answer;
    }
}
