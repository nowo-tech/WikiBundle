<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Hook;

use Nowo\WikiBundle\Api\Value\WikiApiNewWiki;
use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Nowo\WikiBundle\Api\Value\WikiApiWikiPatch;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiRefusal;

/**
 * 1.24 (spec 218 FR-033, Beacon Q-B1): creating, renaming, archiving and unarchiving wikis, with the host's checks (the
 * owner's permissions, the plan's `wiki_spaces`). Each method refuses with a {@see WikiApiRefusal} (404 `ACCESS_001`,
 * 403 `PLAN_002`, 409 `WIKI_003` for a taken name, …).
 *
 * What the bundle has checked before a call (audit IF-L7):
 *
 * - `create()`: the level (Full) only. **The host must** check that the owner is within the token's *where*
 *   (`$principal->where->reachesOwner($ownerKey)`, with the key its directory uses; refuse with 404 otherwise) and that
 *   the person may manage that owner. The bundle checks afterwards that the wiki answered is within *where*.
 * - `update()`, `archive()`, `unarchive()`: reach (*where*, the directory, the plan), the level (Full), and — when the
 *   wiki's space exists — that the person can open it and holds `Manage` on it. For a wiki nobody opened yet (no space)
 *   only the level was checked: the host decides on the owner.
 */
interface WikiApiWikiManagementInterface
{
    /** @throws WikiApiRefusal */
    public function create(WikiApiPrincipal $principal, WikiApiNewWiki $wiki): WikiApiWiki;

    /** @throws WikiApiRefusal */
    public function update(WikiApiPrincipal $principal, WikiApiWiki $wiki, WikiApiWikiPatch $patch): WikiApiWiki;

    /** @throws WikiApiRefusal */
    public function archive(WikiApiPrincipal $principal, WikiApiWiki $wiki): WikiApiWiki;

    /** @throws WikiApiRefusal */
    public function unarchive(WikiApiPrincipal $principal, WikiApiWiki $wiki): WikiApiWiki;
}
