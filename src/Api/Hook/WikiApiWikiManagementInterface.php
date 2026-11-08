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
 * - `ownerKey()`, then `create()`: the level (Full), then the owner key `ownerKey()` names against the token's *where*
 *   (`WikiApiWhere::reachesOwner()`; 404 and nothing created otherwise). **The host must** still check that the person
 *   may manage that owner, and must create the wiki under exactly that owner: the wiki `create()` answers must carry
 *   the same `ownerKey`, or the bundle answers 500 and logs the hook as faulty (the write is audited all the same).
 * - `update()`, `archive()`, `unarchive()`: reach (*where*, the directory, the plan), the level (Full), and — when the
 *   wiki's space exists — that the person can open it and holds `Manage` on it. For a wiki nobody opened yet (no space)
 *   only the level was checked: the host decides on the owner.
 */
interface WikiApiWikiManagementInterface
{
    /**
     * The owner key (as the directory's {@see WikiApiWiki::$ownerKey}) of a wiki about to be created; null: no such owner
     * (404). Must not write.
     *
     * @throws WikiApiRefusal
     */
    public function ownerKey(WikiApiPrincipal $principal, WikiApiNewWiki $wiki): ?string;

    /** @throws WikiApiRefusal */
    public function create(WikiApiPrincipal $principal, WikiApiNewWiki $wiki): WikiApiWiki;

    /** @throws WikiApiRefusal */
    public function update(WikiApiPrincipal $principal, WikiApiWiki $wiki, WikiApiWikiPatch $patch): WikiApiWiki;

    /** @throws WikiApiRefusal */
    public function archive(WikiApiPrincipal $principal, WikiApiWiki $wiki): WikiApiWiki;

    /** @throws WikiApiRefusal */
    public function unarchive(WikiApiPrincipal $principal, WikiApiWiki $wiki): WikiApiWiki;
}
