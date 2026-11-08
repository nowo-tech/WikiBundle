<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Hook;

use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Nowo\WikiBundle\Api\Value\WikiApiWikiFilter;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiRefusal;
use Nowo\WikiBundle\Entity\WikiSpace;

/**
 * 1.24 (spec 218): the host's wikis, required with `api.enabled`. Answers within the token's *where* and the person's
 * access; restricted wikis only when named and granted, archived ones only to their managers (FR-009, FR-011). A read
 * never writes: a wiki nobody opened is answered with a null space. The bundle checks *where* and the space checker
 * again on every answer.
 */
interface WikiApiDirectoryInterface
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
    public function ofSpace(WikiApiPrincipal $principal, WikiSpace $space): ?WikiApiWiki;

    /**
     * Creates the space of a wiki nobody opened, on its first write, with the rights of a first visit.
     *
     * @throws WikiApiRefusal
     */
    public function provision(WikiApiPrincipal $principal, WikiApiWiki $wiki): WikiSpace;
}
