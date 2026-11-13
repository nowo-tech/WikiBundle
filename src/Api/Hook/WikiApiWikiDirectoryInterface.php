<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Hook;

use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Nowo\WikiBundle\Api\Value\WikiApiWikiFilter;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiRefusal;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;

/**
 * The REST API's wiki directory, framework-free (1.34.0): the twin of the deprecated {@see WikiApiDirectoryInterface}
 * (`nowo_wiki.api.hooks.directory` takes either). Name a wiki's space by id (`WikiApiWiki::$spaceId`, no entity);
 * the bundle loads it and refuses an id that names no stored space.
 */
interface WikiApiWikiDirectoryInterface
{
    /**
     * Every wiki the token may list, unpaged (the bundle pages them).
     *
     * @return list<WikiApiWiki>
     */
    public function list(WikiApiPrincipal $principal, WikiApiWikiFilter $filter): array;

    /** One wiki by slug; null when the token may not see it (the API answers 404). */
    public function find(WikiApiPrincipal $principal, string $slug): ?WikiApiWiki;

    /** The wiki of a space (search hits, links); null when the token may not see it. */
    public function ofSpace(WikiApiPrincipal $principal, WikiSpaceSnapshot $space): ?WikiApiWiki;

    /**
     * Creates the space of a wiki nobody opened, on its first write, with the rights of a first visit.
     *
     * @throws WikiApiRefusal
     *
     * @return string the new space's id
     */
    public function provision(WikiApiPrincipal $principal, WikiApiWiki $wiki): string;
}
