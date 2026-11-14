<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Support;

use Nowo\WikiBundle\Member\WikiMemberBadge;
use Nowo\WikiBundle\Member\WikiMemberBadgeProviderInterface;
use Nowo\WikiBundle\Member\WikiMemberRef;

use function array_map;

/**
 * Spec 234: records every batch (member ids) and answers from a map of member id => badges (a host whose answers may change
 * between requests: {@see self::$badges} is public).
 */
final class RecordingBadgeProvider implements WikiMemberBadgeProviderInterface
{
    /** @var list<list<string>> */
    public array $calls = [];

    /**
     * @param array<string, list<mixed>> $badges member id => what the host returns (non-badges included, for the filter)
     */
    public function __construct(public array $badges = [])
    {
    }

    public function badgesFor(WikiMemberRef $member): array
    {
        return $this->badgesForMany([$member])[0] ?? [];
    }

    public function badgesForMany(array $members): array
    {
        $this->calls[] = array_map(static fn (WikiMemberRef $member): string => $member->id, $members);
        $answer        = [];
        foreach ($members as $index => $member) {
            if (isset($this->badges[$member->id])) {
                /** @var list<WikiMemberBadge> $badges the filter is under test: a host may return anything */
                $badges         = $this->badges[$member->id];
                $answer[$index] = $badges;
            }
        }

        return $answer;
    }
}
