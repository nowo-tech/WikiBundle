<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Controller;

use Nowo\WikiBundle\Api\Operation\WikiApiCommentReadOperations;
use Nowo\WikiBundle\Api\Operation\WikiApiPageReadOperations;
use Nowo\WikiBundle\Api\Operation\WikiApiRevisionOperations;
use Nowo\WikiBundle\Api\Operation\WikiApiSearchOperations;
use Nowo\WikiBundle\Api\Security\WikiApiRequestListener;
use Nowo\WikiBundle\Api\WikiApiQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * 1.24 (spec 218): page, revision, search and thread reads. Thin: parse, call one operation, answer.
 */
final readonly class WikiApiReadController
{
    public function __construct(
        private WikiApiPageReadOperations $pages,
        private WikiApiRevisionOperations $revisions,
        private WikiApiSearchOperations $search,
        private WikiApiCommentReadOperations $comments,
    ) {
    }

    public function pages(Request $request, string $wiki): JsonResponse
    {
        $query = new WikiApiQuery($request);

        return new JsonResponse($this->pages->list(WikiApiRequestListener::principalOf($request), $wiki, $query->string('parent', 36), $query->bool('archived'), $query->date('updated_since'), $query->page()));
    }

    public function page(Request $request, string $wiki, string $page): JsonResponse
    {
        return new JsonResponse($this->pages->get(WikiApiRequestListener::principalOf($request), $wiki, $page));
    }

    public function revisions(Request $request, string $wiki, string $page): JsonResponse
    {
        return new JsonResponse($this->revisions->list(WikiApiRequestListener::principalOf($request), $wiki, $page, (new WikiApiQuery($request))->page()));
    }

    public function revision(Request $request, string $wiki, string $page, string $number): JsonResponse
    {
        return new JsonResponse($this->revisions->get(WikiApiRequestListener::principalOf($request), $wiki, $page, $number));
    }

    public function diff(Request $request, string $wiki, string $page, string $number): JsonResponse
    {
        return new JsonResponse($this->revisions->diff(WikiApiRequestListener::principalOf($request), $wiki, $page, $number, (new WikiApiQuery($request))->string('against', 10)));
    }

    public function search(Request $request): JsonResponse
    {
        $query = new WikiApiQuery($request);

        return new JsonResponse($this->search->search(WikiApiRequestListener::principalOf($request), $query->string('q', 800) ?? '', $query->strings('wiki'), $query->page()));
    }

    public function threads(Request $request, string $wiki, string $page): JsonResponse
    {
        return new JsonResponse($this->comments->threads(WikiApiRequestListener::principalOf($request), $wiki, $page));
    }

    public function thread(Request $request, string $wiki, string $page, string $thread): JsonResponse
    {
        return new JsonResponse($this->comments->thread(WikiApiRequestListener::principalOf($request), $wiki, $page, $thread));
    }
}
