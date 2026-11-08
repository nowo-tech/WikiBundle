<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Hook;

use Nowo\WikiBundle\Api\Value\WikiApiFile;
use Nowo\WikiBundle\Api\Value\WikiApiUploadedFile;
use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiRefusal;
use Nowo\WikiBundle\Entity\WikiPage;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * 1.24 (spec 218 FR-037 – FR-040, Beacon Q-B1): the host's files (Beacon: `App\Files`). Every upload check is the
 * host's: the target, the sniffed type, Office macros, storage, plan, rate limit. The bundle has checked reach and the
 * level; it adds `nosniff`, `attachment` and a sandboxing CSP to every download.
 */
interface WikiApiFilesInterface
{
    /**
     * The files the page's current revision shows, only those the reader may open.
     *
     * @return list<WikiApiFile>
     */
    public function list(WikiApiPrincipal $principal, WikiApiWiki $wiki, WikiPage $page): array;

    /** @throws WikiApiRefusal a refusal stores nothing */
    public function upload(WikiApiPrincipal $principal, WikiApiWiki $wiki, UploadedFile $file): WikiApiUploadedFile;

    /** The file, for readers of that wiki only; null: not one of this wiki's files (404). */
    public function download(WikiApiPrincipal $principal, WikiApiWiki $wiki, string $fileUuid): ?Response;
}
