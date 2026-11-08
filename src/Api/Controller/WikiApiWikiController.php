<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Controller;

use Nowo\WikiBundle\Api\Operation\WikiApiWikiOperations;
use Nowo\WikiBundle\Api\Security\WikiApiRequestListener;
use Nowo\WikiBundle\Api\Value\WikiApiWikiFilter;
use Nowo\WikiBundle\Api\WikiApiQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * 1.24 (spec 218): the wiki reads. Thin: parse, call one operation, answer.
 */
final readonly class WikiApiWikiController
{
    public function __construct(private WikiApiWikiOperations $operations)
    {
    }

    public function list(Request $request): JsonResponse
    {
        $query = new WikiApiQuery($request);

        return new JsonResponse($this->operations->list(
            WikiApiRequestListener::principalOf($request),
            new WikiApiWikiFilter($query->string('owner_type', 32), $query->string('owner_id', 64), $query->bool('archived')),
            $query->page(),
        ));
    }

    public function get(Request $request, string $wiki): JsonResponse
    {
        return new JsonResponse($this->operations->get(WikiApiRequestListener::principalOf($request), $wiki));
    }
}
