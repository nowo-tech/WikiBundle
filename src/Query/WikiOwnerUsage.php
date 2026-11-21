<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Query;

/**
 * 1.51: what one owner holds in the wiki, from {@see WikiSpaceQueryInterface::usage()} (plan limits, billing).
 */
final readonly class WikiOwnerUsage
{
    /**
     * @param string $ownerScopeId the owner, exactly as asked
     * @param int $spaces spaces that are not archived
     * @param int $archivedSpaces archived spaces
     * @param int $pages pages that are not archived, in all of the owner's spaces
     * @param int $archivedPages archived (and trashed) pages
     * @param int $attachmentBytes size of every attachment of the owner's spaces, in bytes
     */
    public function __construct(
        public string $ownerScopeId,
        public int $spaces = 0,
        public int $archivedSpaces = 0,
        public int $pages = 0,
        public int $archivedPages = 0,
        public int $attachmentBytes = 0,
    ) {
    }

    /**
     * The sum of several owners' usage (a payer with many owners).
     *
     * @param iterable<WikiOwnerUsage> $usages the usages to add up
     * @param string $ownerScopeId the owner id the sum carries ('' by default)
     *
     * @return self the sum
     */
    public static function total(iterable $usages, string $ownerScopeId = ''): self
    {
        $spaces = $archivedSpaces = $pages = $archivedPages = $bytes = 0;
        foreach ($usages as $usage) {
            $spaces += $usage->spaces;
            $archivedSpaces += $usage->archivedSpaces;
            $pages += $usage->pages;
            $archivedPages += $usage->archivedPages;
            $bytes += $usage->attachmentBytes;
        }

        return new self($ownerScopeId, $spaces, $archivedSpaces, $pages, $archivedPages, $bytes);
    }
}
