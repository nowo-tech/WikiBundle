<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Avatar;

use Nowo\UiKitBundle\Avatar\AvatarUrlPolicy;
use Nowo\WikiBundle\History\WikiAuthorLabel;
use Nowo\WikiBundle\Service\WikiRequestBoundaryResetter;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Service\ResetInterface;

use function array_key_exists;
use function array_values;
use function count;
use function is_string;

/**
 * Batches and memoises the avatar URLs of one response (1.16, `@internal`): {@see self::prime()} asks the host's
 * {@see WikiUserAvatarResolverInterface} once for every user not seen yet, {@see self::urlOf()} reads the result
 * through the UiKit avatar URL policy. Callers prime only users whose real name they show (the privacy gate of
 * {@see WikiAuthorLabel::presentAll()}). The memo is bounded and cleared on `kernel.reset`
 * and, since 1.29.1, at every request / console / Messenger boundary by
 * {@see WikiRequestBoundaryResetter} (where the services resetter does not run).
 *
 * @internal
 */
final class WikiAvatars implements ResetInterface
{
    /** Size asked of the resolver, in CSS pixels (UiKit `_avatar` default; the history uses `xs`). */
    public const SIZE = 32;

    /** Users memoised at most (the memo is emptied beyond it). */
    public const MAX_MEMO = 500;

    /** @var array<string, string|null> key => sanitized URL (null: none) */
    private array $urls = [];

    public function __construct(private readonly WikiUserAvatarResolverInterface $resolver)
    {
    }

    /**
     * Resolves the users not seen yet in one call.
     *
     * @param iterable<UserInterface> $users users whose real name is shown
     */
    public function prime(iterable $users): void
    {
        $missing = [];
        foreach ($users as $user) {
            $key = WikiAvatarKey::of($user);
            if (!isset($missing[$key]) && !array_key_exists($key, $this->urls)) {
                $missing[$key] = $user;
            }
        }
        if ($missing === []) {
            return;
        }
        if (count($this->urls) + count($missing) > self::MAX_MEMO) {
            $this->urls = [];
        }
        $resolved = $this->resolver->urlsFor(array_values($missing), self::SIZE);
        foreach ($missing as $key => $user) {
            $url              = $resolved[$key] ?? null;
            $this->urls[$key] = AvatarUrlPolicy::sanitize(is_string($url) ? $url : null);
        }
    }

    /**
     * The sanitized URL of a user (resolved on the spot when it was not primed).
     */
    public function urlOf(UserInterface $user): ?string
    {
        $key = WikiAvatarKey::of($user);
        if (!array_key_exists($key, $this->urls)) {
            $this->prime([$user]);
        }

        return $this->urls[$key] ?? null;
    }

    public function reset(): void
    {
        $this->urls = [];
    }
}
