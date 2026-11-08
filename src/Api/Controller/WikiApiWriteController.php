<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Controller;

use Nowo\WikiBundle\Api\Operation\WikiApiPageWriteOperations;
use Nowo\WikiBundle\Api\Security\WikiApiIdempotencyListener;
use Nowo\WikiBundle\Api\Security\WikiApiRequestListener;
use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Nowo\WikiBundle\Api\WikiApiInput;
use Nowo\WikiBundle\Entity\WikiPage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 1.24 (spec 218): page writes. Thin: parse, call one operation, answer with the page and the body's `warnings`.
 */
final readonly class WikiApiWriteController
{
    public function __construct(
        private WikiApiPageWriteOperations $pages,
        private int $maxBodyBytes,
    ) {
    }

    public function create(Request $request, string $wiki): JsonResponse
    {
        $input = WikiApiInput::fromRequest($request, $this->maxBodyBytes);

        return $this->answer($request, $input, $this->pages->create(WikiApiRequestListener::principalOf($request), $wiki, $input), Response::HTTP_CREATED);
    }

    public function update(Request $request, string $wiki, string $page): JsonResponse
    {
        $input = WikiApiInput::fromRequest($request, $this->maxBodyBytes);

        return $this->answer($request, $input, $this->pages->update(WikiApiRequestListener::principalOf($request), $wiki, $page, $input));
    }

    public function move(Request $request, string $wiki, string $page): JsonResponse
    {
        $input = WikiApiInput::fromRequest($request, $this->maxBodyBytes);

        return $this->answer($request, $input, $this->pages->move(WikiApiRequestListener::principalOf($request), $wiki, $page, $input));
    }

    public function archive(Request $request, string $wiki, string $page): JsonResponse
    {
        $input = WikiApiInput::fromRequest($request, $this->maxBodyBytes);

        return $this->answer($request, $input, $this->pages->archive(WikiApiRequestListener::principalOf($request), $wiki, $page, true));
    }

    public function unarchive(Request $request, string $wiki, string $page): JsonResponse
    {
        $input = WikiApiInput::fromRequest($request, $this->maxBodyBytes);

        return $this->answer($request, $input, $this->pages->archive(WikiApiRequestListener::principalOf($request), $wiki, $page, false));
    }

    public function restore(Request $request, string $wiki, string $page, string $number): JsonResponse
    {
        $input = WikiApiInput::fromRequest($request, $this->maxBodyBytes);

        return $this->answer($request, $input, $this->pages->restore(WikiApiRequestListener::principalOf($request), $wiki, $page, $number, $input));
    }

    /**
     * @param array{0: array<string, mixed>, 1: WikiApiWiki, 2: WikiPage} $result
     */
    private function answer(Request $request, WikiApiInput $input, array $result, int $status = Response::HTTP_OK): JsonResponse
    {
        [$body, $wiki, $page] = $result;
        WikiApiIdempotencyListener::target($request, $wiki->slug, $page->getId());
        $warnings = $input->warnings();

        return new JsonResponse($warnings === [] ? $body : $body + ['warnings' => $warnings], $status);
    }
}
