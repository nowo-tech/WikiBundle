<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Member;

use Nowo\WikiBundle\Service\WikiRequestBoundaryResetter;
use Symfony\Contracts\Service\ResetInterface;

use function array_key_exists;
use function array_slice;
use function array_values;
use function count;
use function is_array;

/**
 * Batches and memoises the member badges of one response (spec 234, `@internal`): {@see self::prime()} asks the
 * host's {@see WikiMemberBadgeProviderInterface} once for every member not seen yet (no N+1 over a list),
 * {@see self::of()} reads the result. Anything the provider returns that is not a {@see WikiMemberBadge} is dropped,
 * and at most {@see self::MAX_PER_MEMBER} badges are kept per member.
 *
 * Worker mode (REQ-RUNTIME-002): the memo is bounded, cleared on `kernel.reset` and at every request / console /
 * Messenger boundary by {@see WikiRequestBoundaryResetter}.
 *
 * @internal
 */
final class WikiMemberBadges implements ResetInterface
{
    /** Members memoised at most (the memo is emptied beyond it). */
    public const MAX_MEMO = 500;

    /** Badges kept per member. */
    public const MAX_PER_MEMBER = 3;

    /** @var array<string, list<WikiMemberBadge>> */
    private array $badges = [];

    public function __construct(private readonly WikiMemberBadgeProviderInterface $provider)
    {
    }

    /**
     * Resolves the members not seen yet in one provider call.
     *
     * @param iterable<object> $users the users shown (users without a usable id are skipped)
     */
    public function prime(iterable $users): void
    {
        $missing = [];
        foreach ($users as $user) {
            $ref = WikiMemberRef::of($user);
            if (!$ref instanceof WikiMemberRef) {
                continue;
            }
            $key = self::key($ref);
            if (!isset($missing[$key]) && !array_key_exists($key, $this->badges)) {
                $missing[$key] = $ref;
            }
        }
        if ($missing === []) {
            return;
        }
        if (count($this->badges) + count($missing) > self::MAX_MEMO) {
            $this->badges = [];
        }
        $refs     = array_values($missing);
        $resolved = $this->provider->badgesForMany($refs);
        foreach ($refs as $index => $ref) {
            $this->badges[self::key($ref)] = self::kept($resolved[$index] ?? []);
        }
    }

    /**
     * The badges of a user (resolved on the spot when it was not primed); none for a user without a usable id.
     *
     * @return list<WikiMemberBadge>
     */
    public function of(?object $user): array
    {
        $ref = $user === null ? null : WikiMemberRef::of($user);
        if (!$ref instanceof WikiMemberRef) {
            return [];
        }
        $key = self::key($ref);
        if (!array_key_exists($key, $this->badges)) {
            $this->prime([$user]);
        }

        return $this->badges[$key] ?? [];
    }

    public function reset(): void
    {
        $this->badges = [];
    }

    private static function key(WikiMemberRef $ref): string
    {
        return $ref->userClass . "\0" . $ref->id;
    }

    /**
     * @return list<WikiMemberBadge>
     */
    private static function kept(mixed $badges): array
    {
        $kept = [];
        foreach (is_array($badges) ? $badges : [] as $badge) {
            if ($badge instanceof WikiMemberBadge) {
                $kept[] = $badge;
            }
        }

        return array_slice($kept, 0, self::MAX_PER_MEMBER);
    }
}
