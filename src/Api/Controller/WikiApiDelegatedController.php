<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Controller;

use Nowo\WikiBundle\Api\Operation\WikiApiDelegatedOperations;
use Nowo\WikiBundle\Api\Security\WikiApiIdempotencyListener;
use Nowo\WikiBundle\Api\Security\WikiApiRequestListener;
use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Nowo\WikiBundle\Api\WikiApiInput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 1.24 (spec 218): wiki lifecycle and files, over the host's hooks. Thin: parse, call one operation, answer.
 */
final readonly class WikiApiDelegatedController
{
    public function __construct(
        private WikiApiDelegatedOperations $operations,
        private int $maxBodyBytes,
    ) {
    }

    public function createWiki(Request $request): JsonResponse
    {
        $input = WikiApiInput::fromRequest($request, $this->maxBodyBytes);

        return $this->answer($request, $input, $this->operations->createWiki(WikiApiRequestListener::principalOf($request), $input), Response::HTTP_CREATED);
    }

    public function updateWiki(Request $request, string $wiki): JsonResponse
    {
        $input = WikiApiInput::fromRequest($request, $this->maxBodyBytes);

        return $this->answer($request, $input, $this->operations->updateWiki(WikiApiRequestListener::principalOf($request), $wiki, $input));
    }

    public function archiveWiki(Request $request, string $wiki): JsonResponse
    {
        $input = WikiApiInput::fromRequest($request, $this->maxBodyBytes);

        return $this->answer($request, $input, $this->operations->archiveWiki(WikiApiRequestListener::principalOf($request), $wiki, true));
    }

    public function unarchiveWiki(Request $request, string $wiki): JsonResponse
    {
        $input = WikiApiInput::fromRequest($request, $this->maxBodyBytes);

        return $this->answer($request, $input, $this->operations->archiveWiki(WikiApiRequestListener::principalOf($request), $wiki, false));
    }

    public function files(Request $request, string $wiki, string $page): JsonResponse
    {
        return new JsonResponse($this->operations->listFiles(WikiApiRequestListener::principalOf($request), $wiki, $page));
    }

    public function upload(Request $request, string $wiki): JsonResponse
    {
        [$body, $target] = $this->operations->upload(WikiApiRequestListener::principalOf($request), $wiki, $request->files->get('file'));
        WikiApiIdempotencyListener::target($request, $target->slug, null);

        return new JsonResponse($body, Response::HTTP_CREATED);
    }

    public function download(Request $request, string $wiki, string $file): Response
    {
        return $this->operations->download(WikiApiRequestListener::principalOf($request), $wiki, $file);
    }

    /**
     * @param array{0: array<string, mixed>, 1: WikiApiWiki} $result
     */
    private function answer(Request $request, WikiApiInput $input, array $result, int $status = Response::HTTP_OK): JsonResponse
    {
        [$body, $wiki] = $result;
        WikiApiIdempotencyListener::target($request, $wiki->slug, null);
        $warnings = $input->warnings();

        return new JsonResponse($warnings === [] ? $body : $body + ['warnings' => $warnings], $status);
    }
}
