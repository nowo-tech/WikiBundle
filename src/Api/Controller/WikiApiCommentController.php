<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Controller;

use Nowo\WikiBundle\Api\Operation\WikiApiCommentWriteOperations;
use Nowo\WikiBundle\Api\Security\WikiApiIdempotencyListener;
use Nowo\WikiBundle\Api\Security\WikiApiRequestListener;
use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Nowo\WikiBundle\Api\WikiApiInput;
use Nowo\WikiBundle\Entity\WikiPage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 1.24 (spec 218): comment writes. Thin: parse, call one operation, answer.
 */
final readonly class WikiApiCommentController
{
    public function __construct(
        private WikiApiCommentWriteOperations $comments,
        private int $maxBodyBytes,
    ) {
    }

    public function create(Request $request, string $wiki, string $page): JsonResponse
    {
        $input = WikiApiInput::fromRequest($request, $this->maxBodyBytes);

        return $this->answer($request, $input, $this->comments->create(WikiApiRequestListener::principalOf($request), $wiki, $page, $input), Response::HTTP_CREATED);
    }

    public function reply(Request $request, string $wiki, string $page, string $thread): JsonResponse
    {
        $input = WikiApiInput::fromRequest($request, $this->maxBodyBytes);

        return $this->answer($request, $input, $this->comments->reply(WikiApiRequestListener::principalOf($request), $wiki, $page, $thread, $input), Response::HTTP_CREATED);
    }

    public function resolve(Request $request, string $wiki, string $page, string $thread): JsonResponse
    {
        $input = WikiApiInput::fromRequest($request, $this->maxBodyBytes);

        return $this->answer($request, $input, $this->comments->resolve(WikiApiRequestListener::principalOf($request), $wiki, $page, $thread, $input));
    }

    public function edit(Request $request, string $wiki, string $page, string $comment): JsonResponse
    {
        $input = WikiApiInput::fromRequest($request, $this->maxBodyBytes);

        return $this->answer($request, $input, $this->comments->edit(WikiApiRequestListener::principalOf($request), $wiki, $page, $comment, $input));
    }

    public function delete(Request $request, string $wiki, string $page, string $comment): Response
    {
        return $this->gone($request, $this->comments->delete(WikiApiRequestListener::principalOf($request), $wiki, $page, $comment));
    }

    public function deleteThread(Request $request, string $wiki, string $page, string $thread): Response
    {
        return $this->gone($request, $this->comments->deleteThread(WikiApiRequestListener::principalOf($request), $wiki, $page, $thread));
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

    /**
     * @param array{0: WikiApiWiki, 1: WikiPage} $result
     */
    private function gone(Request $request, array $result): Response
    {
        WikiApiIdempotencyListener::target($request, $result[0]->slug, $result[1]->getId());

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
