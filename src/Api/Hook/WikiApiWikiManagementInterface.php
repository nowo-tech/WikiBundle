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
 * owner's permissions, the plan's `wiki_spaces`). The bundle has checked reach, the level (Full) and, for an existing
 * space, `Manage`; each method refuses with a {@see WikiApiRefusal} (403 `PLAN_002`, 409 `WIKI_003` for a taken name, …).
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
