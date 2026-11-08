<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Hook;

use Nowo\WikiBundle\Api\Value\WikiApiNewWiki;
use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Nowo\WikiBundle\Api\Value\WikiApiWikiPatch;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiRefusal;

/**
 * 1.24 (spec 218): the default: wikis are not managed through the API until the host sets `api.hooks.wiki_management`.
 */
final readonly class DenyingWikiApiWikiManagement implements WikiApiWikiManagementInterface
{
    public function ownerKey(WikiApiPrincipal $principal, WikiApiNewWiki $wiki): ?string
    {
        throw self::refusal();
    }

    public function create(WikiApiPrincipal $principal, WikiApiNewWiki $wiki): WikiApiWiki
    {
        throw self::refusal();
    }

    public function update(WikiApiPrincipal $principal, WikiApiWiki $wiki, WikiApiWikiPatch $patch): WikiApiWiki
    {
        throw self::refusal();
    }

    public function archive(WikiApiPrincipal $principal, WikiApiWiki $wiki): WikiApiWiki
    {
        throw self::refusal();
    }

    public function unarchive(WikiApiPrincipal $principal, WikiApiWiki $wiki): WikiApiWiki
    {
        throw self::refusal();
    }

    private static function refusal(): WikiApiRefusal
    {
        return new WikiApiRefusal(403, 'WIKI_001', 'Wikis are not managed through this API');
    }
}
