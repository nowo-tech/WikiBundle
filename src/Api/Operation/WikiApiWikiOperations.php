<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Operation;

use Nowo\WikiBundle\Api\Hook\WikiApiDirectoryInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiPlanGateInterface;
use Nowo\WikiBundle\Api\Security\WikiApiGuard;
use Nowo\WikiBundle\Api\Serializer\WikiApiSerializer;
use Nowo\WikiBundle\Api\Value\WikiApiWikiFilter;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiQuery;
use Nowo\WikiBundle\Api\WikiApiRefusal;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;

use function array_map;
use function array_slice;
use function array_values;
use function count;

/**
 * 1.24 (spec 218): the wiki reads. Public, for in-process callers; each method answers the JSON body of its endpoint.
 */
final readonly class WikiApiWikiOperations
{
    public function __construct(
        private WikiApiDirectoryInterface $directory,
        private WikiApiPlanGateInterface $planGate,
        private WikiApiGuard $guard,
        private WikiAccessCheckerInterface $roles,
        private WikiApiSerializer $serializer,
    ) {
    }

    /**
     * `GET /wikis`: what the directory lists, each entry checked again (where, space access) and the plan's refusals
     * left out silently, then paged.
     *
     * @throws WikiApiRefusal
     *
     * @return array{wikis: list<array<string, mixed>>, last_page: bool}
     */
    public function list(WikiApiPrincipal $principal, WikiApiWikiFilter $filter, int $page): array
    {
        if (!$this->roles->canList($principal->user)) {
            throw WikiApiRefusal::permissionRefused();
        }
        $visible = [];
        foreach ($this->directory->list($principal, $filter) as $wiki) {
            if ($this->guard->isSlug($wiki->slug) && $this->guard->sees($principal, $wiki) && $this->planGate->allowsWiki($principal->user, $wiki)) {
                $visible[] = $wiki;
            }
        }
        $rows = array_slice($visible, $page * WikiApiQuery::PAGE_SIZE, WikiApiQuery::PAGE_SIZE);

        return [
            'wikis'     => array_values(array_map($this->serializer->wiki(...), $rows)),
            'last_page' => count($visible) <= ($page + 1) * WikiApiQuery::PAGE_SIZE,
        ];
    }

    /**
     * `GET /wikis/{wiki}`.
     *
     * @throws WikiApiRefusal
     *
     * @return array<string, mixed>
     */
    public function get(WikiApiPrincipal $principal, string $slug): array
    {
        if (!$this->roles->canList($principal->user)) {
            throw WikiApiRefusal::permissionRefused();
        }

        return $this->serializer->wiki($this->guard->wiki($principal, $slug));
    }
}
