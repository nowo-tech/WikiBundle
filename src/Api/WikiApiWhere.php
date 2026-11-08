<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api;

use Nowo\WikiBundle\Api\Value\WikiApiWiki;

use function in_array;

/**
 * 1.24 (spec 218): where a token reaches, as plain data the host builds on each request. Owners (projects,
 * organizations) are keys the host chooses; wikis are named by slug. Where only narrows: access still comes from the
 * checker and the directory.
 *
 * A restricted wiki is reached only when its slug is named (spec 218 Q2, Beacon Q-B6), never through "everything" or
 * through its owner.
 */
final readonly class WikiApiWhere
{
    /**
     * @param list<string> $exceptOwnerKeys
     * @param list<string> $ownerKeys
     * @param list<string> $wikiSlugs
     */
    private function __construct(
        private bool $everything,
        private array $exceptOwnerKeys,
        private array $ownerKeys,
        private array $wikiSlugs,
    ) {
    }

    /**
     * Everything the person can reach, minus the owners the host removes (an organization that forbids tokens). Map only
     * a token's explicit "everything" to it; an empty, missing or unreadable scope is {@see self::limitedTo()}`([], [])`.
     *
     * @param list<string> $exceptOwnerKeys
     */
    public static function everything(array $exceptOwnerKeys = []): self
    {
        return new self(true, $exceptOwnerKeys, [], []);
    }

    /**
     * Each entry grants on its own: a named owner reaches its wikis, a named wiki is reached even when its owner is not.
     *
     * @param list<string> $ownerKeys
     * @param list<string> $wikiSlugs
     */
    public static function limitedTo(array $ownerKeys, array $wikiSlugs): self
    {
        return new self(false, [], $ownerKeys, $wikiSlugs);
    }

    public function reaches(WikiApiWiki $wiki): bool
    {
        if ($this->everything && $wiki->ownerKey !== null && in_array($wiki->ownerKey, $this->exceptOwnerKeys, true)) {
            return false;
        }
        if (!$this->everything && in_array($wiki->slug, $this->wikiSlugs, true)) {
            return true;
        }
        if ($wiki->restricted) {
            return false;
        }

        return $this->everything || ($wiki->ownerKey !== null && in_array($wiki->ownerKey, $this->ownerKeys, true));
    }
}
